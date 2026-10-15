<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\PlanMantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\ArchivoService;
use App\Services\Auditor;
use App\Services\EquipoService;
use App\Support\Ajustes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProyectoController extends Controller
{
    public function index(Request $request)
    {
        $proyectos = Proyecto::query()
            ->with(['entidad', 'clienteFinal'])
            ->withCount(['garantias', 'equipos', 'incidencias as incidencias_abiertas_count' => fn ($q) => $q->abiertas()])
            ->when($request->buscar, fn ($q, $v) => $q->where(fn ($q) => $q
                ->where('nombre', 'like', "%$v%")->orWhere('codigo', 'like', "%$v%")
                ->orWhere('numero_contrato', 'like', "%$v%")->orWhere('orden_compra', 'like', "%$v%")))
            ->when($request->entidad_id, fn ($q, $v) => $q->where(fn ($q) => $q->where('entidad_id', $v)->orWhere('cliente_final_id', $v)))
            ->when($request->clase, fn ($q, $v) => $q->where('clase', $v))
            ->when($request->tipo, fn ($q, $v) => $q->where('tipo', $v))
            // Por defecto no se listan los finalizados (vencidos); se ven eligiendo ese estado.
            ->when($request->estado, fn ($q, $v) => $q->where('estado', $v), fn ($q) => $q->enCurso())
            ->when($request->anio, fn ($q, $v) => $q->whereYear('fecha_entrega', $v))
            ->orderByDesc('fecha_entrega')
            ->paginate(15)->withQueryString();

        return view('proyectos.index', [
            'proyectos' => $proyectos,
            'entidades' => Entidad::orderBy('nombre')->pluck('nombre', 'id'),
            'tipos' => Ajustes::catalogo('tipos_proyecto'),
        ]);
    }

    public function create(Request $request)
    {
        $cotizacion = $request->cotizacion_id ? Cotizacion::where('estado', 'aprobada')->find($request->cotizacion_id) : null;

        return view('proyectos.form', [...$this->datosFormulario(new Proyecto([
            'estado' => 'operativo',
            'clase' => $request->clase === 'servicio' ? 'servicio' : 'implementacion',
            'entidad_id' => $cotizacion?->entidad_id ?? $request->entidad_id,
            'nombre' => $cotizacion?->asunto,
            'monto_contrato' => $cotizacion?->subtotal,
            'descripcion' => $cotizacion?->diagnostico,
        ])), 'cotizacion' => $cotizacion]);
    }

    public function store(Request $request)
    {
        $proyecto = DB::transaction(function () use ($request) {
            $proyecto = Proyecto::create($this->validar($request));
            if ($proyecto->esServicio()) {
                $this->prepararServicio($request, $proyecto);
            }
            $this->guardarEquipos($request, $proyecto);
            if ($request->cotizacion_id) {
                Cotizacion::whereKey($request->cotizacion_id)->whereNull('proyecto_id')->update(['proyecto_id' => $proyecto->id]);
            }
            foreach ($request->file('archivos', []) as $file) {
                ArchivoService::guardar($file, $proyecto, ArchivoService::categoriaPara($file, $request->input('categoria', 'contrato')));
            }

            return $proyecto;
        });

        Auditor::log('crear', 'proyectos', "Registró el proyecto {$proyecto->codigo}", $proyecto);

        $equipos = $proyecto->equipos()->count();

        return redirect()->route('proyectos.show', $proyecto)
            ->with('ok', 'Proyecto registrado'.($equipos ? " con {$equipos} equipo(s)" : '').'. Ahora registre sus garantías.');
    }

    public function show(Proyecto $proyecto)
    {
        $proyecto->loadCount(['equipos', 'equipos as equipos_con_falla_count' => fn ($q) => $q->whereIn('estado', ['con_falla', 'en_reparacion', 'inoperativo'])]);
        $proyecto->load(['entidad', 'clienteFinal', 'responsable', 'garantias', 'archivos.autor',
            'equipos' => fn ($q) => $q->with('garantia')->withCount('incidencias')->orderBy('categoria')->orderBy('codigo'),
            'planesMantenimiento' => fn ($q) => $q->with(['mantenimientos', 'tecnico'])->where('activo', true),
            'incidencias' => fn ($q) => $q->with('tecnico')->latest('fecha_reporte')->limit(10)]);

        return view('proyectos.show', ['proyecto' => $proyecto, 'categorias' => Ajustes::catalogo('categorias_equipo')]);
    }

    public function edit(Proyecto $proyecto)
    {
        return view('proyectos.form', $this->datosFormulario($proyecto));
    }

    public function update(Request $request, Proyecto $proyecto)
    {
        DB::transaction(function () use ($request, $proyecto) {
            $proyecto->update($this->validar($request, $proyecto));
            $this->guardarEquipos($request, $proyecto);
        });
        Auditor::log('editar', 'proyectos', "Editó el proyecto {$proyecto->codigo}", $proyecto, Auditor::cambios($proyecto));

        return redirect()->route('proyectos.show', $proyecto)->with('ok', 'Proyecto actualizado.');
    }

    /**
     * Contrato de servicio nuevo: crea su garantía (inicio del contrato → fin + N meses)
     * y el plan de mantenimientos con sus visitas programadas.
     */
    private function prepararServicio(Request $request, Proyecto $proyecto): void
    {
        $datos = $request->validate([
            'visitas_total' => ['nullable', 'integer', 'min:1', 'max:52'],
            'visitas_frecuencia' => ['required_with:visitas_total', 'nullable', 'integer', 'min:1', 'max:24'],
            'garantia_meses' => ['nullable', 'integer', 'min:0', 'max:60'],
        ], [], ['visitas_total' => 'número de visitas', 'visitas_frecuencia' => 'frecuencia', 'garantia_meses' => 'meses de garantía']);

        $garantia = null;
        $meses = (int) ($datos['garantia_meses'] ?? 0);
        if ($meses > 0) {
            $vence = $proyecto->fecha_fin->copy()->addMonthsNoOverflow($meses);
            $garantia = Garantia::create([
                'proyecto_id' => $proyecto->id,
                'tipo' => 'Garantía del servicio',
                'descripcion' => "Garantía del servicio ({$meses} meses después del contrato)",
                'cobertura' => "Garantía sobre los trabajos realizados durante el contrato, vigente hasta {$meses} meses después de su término.",
                'orden_compra' => $proyecto->orden_compra,
                'monto' => $proyecto->monto_contrato,
                'fecha_inicio' => $proyecto->fecha_entrega,
                'duracion_meses' => (int) round($proyecto->fecha_entrega->diffInMonths($vence)),
                'fecha_vencimiento' => $vence,
                'notificar_cliente' => true,
            ]);
        }

        if (! empty($datos['visitas_total'])) {
            $plan = PlanMantenimiento::create([
                'proyecto_id' => $proyecto->id,
                'garantia_id' => $garantia?->id,
                'nombre' => $proyecto->tipo,
                'frecuencia_meses' => $datos['visitas_frecuencia'],
                'total_visitas' => $datos['visitas_total'],
                'fecha_inicio' => $proyecto->fecha_entrega,
                'alcance' => $proyecto->descripcion,
            ]);
            $plan->programarVisitas();
        }
    }

    /** Agregar equipos desde la ficha del proyecto. */
    public function agregarEquipos(Request $request, Proyecto $proyecto)
    {
        $creados = DB::transaction(fn () => $this->guardarEquipos($request, $proyecto));

        return back()->with('ok', $creados ? "Se registraron {$creados} equipo(s) en el proyecto." : 'No se ingresó ningún equipo.');
    }

    /** Valida y registra las filas «equipos[i][…]» del formulario. */
    private function guardarEquipos(Request $request, Proyecto $proyecto): int
    {
        $filas = collect($request->input('equipos', []))->filter(fn ($f) => filled($f['categoria'] ?? null))->values()->all();
        if (! $filas) {
            return 0;
        }

        $request->merge(['equipos' => $filas])->validate([
            'equipos' => ['array', 'max:100'],
            'equipos.*.categoria' => ['required', 'string', 'max:60'],
            'equipos.*.marca' => ['nullable', 'string', 'max:80'],
            'equipos.*.modelo' => ['nullable', 'string', 'max:80'],
            'equipos.*.serie' => ['nullable', 'string', 'max:80', 'distinct',
                Rule::unique('equipos', 'serie')->where('entidad_id', $proyecto->lugarDeServicio()->id)->whereNull('deleted_at')],
            'equipos.*.ubicacion' => ['nullable', 'string', 'max:255'],
            'equipos.*.area' => ['nullable', 'string', 'max:100'],
            'equipos.*.cantidad' => ['nullable', 'integer', 'min:1', 'max:500'],
        ], [
            'equipos.*.serie.unique' => 'La serie :input ya está registrada en este cliente.',
            'equipos.*.serie.distinct' => 'La serie :input está repetida en la lista.',
        ], ['equipos.*.categoria' => 'equipo', 'equipos.*.cantidad' => 'cantidad']);

        $creados = EquipoService::registrarEnProyecto($proyecto->fresh(), $filas);
        Auditor::log('crear', 'equipos', "Registró {$creados} equipo(s) en el proyecto {$proyecto->codigo}", $proyecto);

        return $creados;
    }

    public function destroy(Proyecto $proyecto)
    {
        if ($proyecto->incidencias()->exists()) {
            return back()->withErrors(['general' => 'No se puede eliminar un proyecto con incidencias registradas. Cambie su estado a Finalizado.']);
        }

        $proyecto->delete();
        Auditor::log('eliminar', 'proyectos', "Eliminó el proyecto {$proyecto->codigo}", $proyecto);

        return redirect()->route('proyectos.index')->with('ok', 'Proyecto eliminado.');
    }

    /** Garantías y equipos del proyecto, para los formularios de incidencias y hojas de servicio. */
    public function opciones(Request $request, Proyecto $proyecto)
    {
        $u = $request->user();
        abort_unless($u->can('incidencias.gestionar') || $u->can('hojas.registrar') || $u->can('proyectos.ver'), 403);

        return [
            'clase' => $proyecto->clase,
            'contrato_vigente' => $proyecto->esServicio() && $proyecto->estado === 'operativo' && (! $proyecto->fecha_fin || $proyecto->fecha_fin->gte(today())),
            'garantias' => $proyecto->garantias()->orderBy('fecha_vencimiento')->get()->map(fn ($g) => [
                'id' => $g->id,
                'texto' => "{$g->codigo} · {$g->tipo} (vence {$g->fecha_vencimiento->format('d/m/Y')})",
                'estado' => $g->estado->value,
                'estado_label' => $g->estado->label(),
            ]),
            'equipos' => self::equiposDe($proyecto)->map(fn (Equipo $e) => [
                'id' => $e->id,
                'texto' => "{$e->codigo} · {$e->nombre()}".($e->ubicacion ? " · {$e->ubicacion}" : ''),
                'estado' => $e->estado,
                'garantia_id' => $e->garantia_id,
                'categoria' => $e->categoria,
                'marca' => $e->marca,
                'modelo' => $e->modelo,
                'serie' => $e->serie,
                'ubicacion' => $e->ubicacion,
            ]),
        ];
    }

    /** Equipos del proyecto y, además, los del cliente donde se presta el servicio. */
    public static function equiposDe(Proyecto $proyecto)
    {
        return Equipo::where(fn ($q) => $q->where('proyecto_id', $proyecto->id)->orWhere('entidad_id', $proyecto->lugarDeServicio()->id))
            ->where('estado', '!=', 'de_baja')
            ->orderBy('categoria')->orderBy('codigo')
            ->get();
    }

    private function datosFormulario(Proyecto $proyecto): array
    {
        return [
            'proyecto' => $proyecto,
            'entidades' => Entidad::where('activo', true)->orWhereIn('id', array_filter([$proyecto->entidad_id, $proyecto->cliente_final_id]))
                ->orderBy('nombre')->get()->mapWithKeys(fn ($e) => [$e->id => "{$e->nombre} ({$e->naturalezaLabel()})"]),
            'responsables' => User::activos()->permission('proyectos.gestionar')->orderBy('name')->pluck('name', 'id'),
            'tipos' => Ajustes::catalogo('tipos_proyecto'),
            'categorias' => Ajustes::catalogo('categorias_equipo'),
            'tiposServicio' => Ajustes::catalogo('tipos_servicio'),
        ];
    }

    private function validar(Request $request, ?Proyecto $proyecto = null): array
    {
        $anios = Ajustes::int('anios_historial');

        $request->validate([
            'archivos' => ['nullable', 'array', 'max:10'],
            'archivos.*' => ArchivoService::reglas(),
        ]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'clase' => ['required', Rule::in(array_keys(Proyecto::CLASES))],
            'codigo' => ['nullable', 'string', 'max:30', Rule::unique('proyectos')->ignore($proyecto?->id)],
            'entidad_id' => ['required', 'exists:entidades,id'],
            'cliente_final_id' => ['nullable', 'different:entidad_id', 'exists:entidades,id'],
            'tipo' => ['required', 'string', 'max:40'],
            'descripcion' => ['nullable', 'string'],
            'numero_contrato' => \App\Support\Reglas::codigoDocumento(),
            'orden_compra' => \App\Support\Reglas::codigoDocumento(),
            'monto_contrato' => \App\Support\Reglas::monto(),
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'date', 'before_or_equal:fecha_entrega'],
            // Alcance histórico máximo de N años (por defecto 5); al editar no se bloquean proyectos ya registrados
            'fecha_entrega' => ['required', 'date', ...($proyecto ? [] : ['after_or_equal:'.today()->subYears($anios)->toDateString()])],
            'fecha_fin' => ['nullable', 'required_if:clase,servicio', 'date', 'after:fecha_entrega'],
            'responsable_id' => ['nullable', 'exists:users,id'],
            'estado' => ['required', Rule::in(array_keys(Proyecto::ESTADOS))],
        ], [
            'cliente_final_id.different' => 'El cliente final debe ser distinto del cliente que contrata.',
            'fecha_fin.required_if' => 'Indique el fin del contrato de servicio.',
            'fecha_fin.after' => 'El fin del contrato debe ser posterior a la fecha de entrega / inicio del servicio.',
            'fecha_entrega.after_or_equal' => "El sistema gestiona proyectos entregados en los últimos $anios años.",
        ]);

        // Si se desmarca el subcontrato o es una implementación, esos campos quedan vacíos.
        return [
            ...$datos,
            'informe_mensual' => $request->boolean('informe_mensual'),
            'cliente_final_id' => $datos['cliente_final_id'] ?? null,
            'fecha_fin' => $datos['clase'] === 'servicio' ? ($datos['fecha_fin'] ?? null) : null,
        ];
    }
}
