<?php

namespace App\Http\Controllers;

use App\Exports\PlantillaEquiposExport;
use App\Imports\EquiposImport;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\EquipoEvento;
use App\Models\Garantia;
use App\Models\Proyecto;
use App\Services\Auditor;
use App\Services\EquipoService;
use App\Support\Ajustes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Inventario de equipos de los clientes con su hoja de vida: permite buscar un equipo
 * por serie o por el código del sticker y conocer todo lo que se le hizo.
 */
class EquipoController extends Controller
{
    public function index(Request $request)
    {
        $equipos = Equipo::query()
            ->with(['entidad', 'proyecto', 'garantia'])
            ->withCount(['incidencias'])
            ->withMax('eventos as ultimo_evento', 'fecha')
            ->filtrar($request->all())
            ->orderBy('entidad_id')->orderBy('categoria')->orderBy('codigo')
            ->paginate(25)->withQueryString();

        // Búsqueda exacta por código o serie: se abre directamente la hoja de vida.
        if ($request->buscar && $equipos->total() === 1 && in_array(strtoupper($request->buscar), [strtoupper($equipos->first()->codigo), strtoupper((string) $equipos->first()->serie)])) {
            return redirect()->route('equipos.show', $equipos->first());
        }

        return view('equipos.index', [
            'equipos' => $equipos,
            'entidades' => Entidad::orderBy('nombre')->pluck('nombre', 'id'),
            'proyectos' => $request->entidad_id
                ? Proyecto::where(fn ($q) => $q->where('entidad_id', $request->entidad_id)->orWhere('cliente_final_id', $request->entidad_id))->orderBy('nombre')->pluck('nombre', 'id')
                : collect(),
            'categorias' => Ajustes::catalogo('categorias_equipo'),
            'resumen' => Equipo::filtrar($request->except('estado'))->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado'),
        ]);
    }

    public function create(Request $request)
    {
        $proyecto = Proyecto::find($request->proyecto_id);

        return view('equipos.form', $this->datosFormulario(new Equipo([
            'entidad_id' => $proyecto?->lugarDeServicio()->id ?? $request->entidad_id,
            'proyecto_id' => $proyecto?->id,
            'garantia_id' => $proyecto?->garantias()->activas()->orderByDesc('fecha_vencimiento')->value('id'),
            'fecha_instalacion' => $proyecto && ! $proyecto->esServicio() ? $proyecto->fecha_entrega : null,
            'estado' => 'operativo',
        ])));
    }

    public function store(Request $request)
    {
        $equipo = DB::transaction(function () use ($request) {
            $equipo = Equipo::create($this->validar($request));
            EquipoService::registrarEvento($equipo, $equipo->fecha_instalacion ? 'instalacion' : 'registro',
                $equipo->fecha_instalacion ? 'Equipo instalado' : 'Equipo registrado en el inventario', [
                    'fecha' => $equipo->fecha_instalacion ?? now(),
                    'detalle' => $equipo->observaciones,
                ]);

            return $equipo;
        });

        Auditor::log('crear', 'equipos', "Registró el equipo {$equipo->codigo} ({$equipo->nombre()})", $equipo);

        return redirect()->route('equipos.show', $equipo)->with('ok', "Equipo registrado con el código {$equipo->codigo}.");
    }

    public function show(Equipo $equipo)
    {
        $equipo->load(['entidad', 'proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'archivos.autor',
            'eventos' => fn ($q) => $q->with(['usuario', 'incidencia', 'hojaServicio']),
            'incidencias' => fn ($q) => $q->with('tecnico')->latest('fecha_reporte'),
            'cotizaciones' => fn ($q) => $q->latest('fecha')]);

        $eventos = $equipo->eventos;
        $resumen = [
            'fallas' => $eventos->where('tipo', 'falla')->count(),
            'mantenimientos' => $eventos->where('tipo', 'mantenimiento')->count(),
            'componentes' => $eventos->where('tipo', 'cambio_componente')->count(),
            'ultimo_mantenimiento' => $eventos->where('tipo', 'mantenimiento')->first()?->fecha,
            'ultima_falla' => $eventos->where('tipo', 'falla')->first()?->fecha,
        ];

        // Antecedentes que el técnico debe conocer: fallas del último año y observaciones registradas.
        $antecedentes = $eventos->filter(fn ($e) => in_array($e->tipo, ['falla', 'diagnostico', 'cambio_componente', 'observacion'])
            && $e->fecha->gte(now()->subYear()))->take(5);

        return view('equipos.show', [
            'equipo' => $equipo,
            'resumen' => $resumen,
            'antecedentes' => $antecedentes,
            'garantiaVigente' => $equipo->garantiaVigente(),
            'tiposEvento' => EquipoEvento::opcionesManuales(),
        ]);
    }

    public function edit(Equipo $equipo)
    {
        return view('equipos.form', $this->datosFormulario($equipo));
    }

    public function update(Request $request, Equipo $equipo)
    {
        $antes = $equipo->only(['responsable', 'ubicacion', 'area', 'estado']);
        $equipo->update($this->validar($request, $equipo));

        // Cambios de responsable o de lugar quedan en la hoja de vida.
        if ($antes['responsable'] !== $equipo->responsable) {
            EquipoService::registrarEvento($equipo, 'entrega', 'Cambio de responsable', [
                'detalle' => ($antes['responsable'] ?: 'Sin responsable').' → '.($equipo->responsable ?: 'Sin responsable'),
            ]);
        }
        if ($antes['ubicacion'] !== $equipo->ubicacion || $antes['area'] !== $equipo->area) {
            EquipoService::registrarEvento($equipo, 'traslado', 'Cambio de ubicación', [
                'detalle' => trim(($antes['area'] ? $antes['area'].' · ' : '').$antes['ubicacion']).' → '.trim(($equipo->area ? $equipo->area.' · ' : '').$equipo->ubicacion),
            ]);
        }

        Auditor::log('editar', 'equipos', "Editó el equipo {$equipo->codigo}", $equipo, Auditor::cambios($equipo));

        return redirect()->route('equipos.show', $equipo)->with('ok', 'Equipo actualizado.');
    }

    public function destroy(Equipo $equipo)
    {
        if ($equipo->incidencias()->exists() || $equipo->eventos()->whereNotNull('hoja_servicio_id')->exists()) {
            return back()->withErrors(['general' => 'El equipo tiene incidencias o servicios registrados; no se elimina para conservar su historial. Puede darlo de baja.']);
        }

        $equipo->delete();
        Auditor::log('eliminar', 'equipos', "Eliminó el equipo {$equipo->codigo}", $equipo);

        return redirect()->route('equipos.index', ['entidad_id' => $equipo->entidad_id])->with('ok', 'Equipo eliminado.');
    }

    /** Registro manual en la hoja de vida (diagnóstico, cambio de componente, entrega, baja…). */
    public function registrarEvento(Request $request, Equipo $equipo)
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(EquipoEvento::MANUALES)],
            'fecha' => ['required', 'date', 'before_or_equal:now'],
            'titulo' => ['required', 'string', 'max:255'],
            'detalle' => ['nullable', 'string', 'max:3000'],
            'componente' => ['nullable', 'string', 'max:100'],
            'estado_equipo' => ['nullable', Rule::in(array_keys(Equipo::ESTADOS))],
        ], ['fecha.before_or_equal' => 'La fecha no puede ser futura.']);

        if ($datos['tipo'] === 'baja') {
            $datos['estado_equipo'] = 'de_baja';
        }

        EquipoService::registrarEvento($equipo, $datos['tipo'], $datos['titulo'], $datos);
        Auditor::log('editar', 'equipos', "Registró «{$datos['titulo']}» en el historial de {$equipo->codigo}", $equipo);

        return back()->with('ok', 'Registro agregado al historial del equipo.');
    }

    /** Hoja de vida del equipo en PDF: datos, garantía e historial completo. */
    public function hojaDeVida(Equipo $equipo)
    {
        $equipo->load(['entidad', 'proyecto.entidad', 'proyecto.clienteFinal', 'garantia',
            'eventos' => fn ($q) => $q->with(['usuario', 'incidencia', 'hojaServicio'])]);
        Auditor::log('exportar', 'equipos', "Generó la hoja de vida del equipo {$equipo->codigo}", $equipo);

        return Pdf::loadView('pdf.hoja-vida-equipo', ['equipo' => $equipo, 'garantia' => $equipo->garantiaVigente()])
            ->setPaper('a4')
            ->stream("Hoja-de-vida-{$equipo->codigo}.pdf");
    }

    /** Stickers con QR para pegar en los equipos (de los seleccionados o de los filtrados). */
    public function etiquetas(Request $request)
    {
        $equipos = Equipo::with('entidad')
            ->when($request->ids, fn ($q, $ids) => $q->whereIn('id', (array) $ids), fn ($q) => $q->filtrar($request->all()))
            ->orderBy('codigo')
            ->limit(200)
            ->get();

        abort_if($equipos->isEmpty(), 404, 'No hay equipos para imprimir.');

        return Pdf::loadView('pdf.etiquetas-equipos', ['equipos' => $equipos])
            ->setPaper('a4')
            ->stream('Etiquetas-equipos.pdf');
    }

    /** Destino del QR del sticker: abre la hoja de vida del equipo. */
    public function qr(string $codigo)
    {
        $equipo = Equipo::where('codigo', strtoupper($codigo))->firstOrFail();

        return redirect()->route('equipos.show', $equipo);
    }

    /** Equipos de un cliente o proyecto para los selectores (hoja de servicio, incidencias, cotizaciones). */
    public function buscar(Request $request)
    {
        $u = $request->user();
        abort_unless($u->can('equipos.ver') || $u->can('hojas.registrar') || $u->can('incidencias.gestionar') || $u->can('cotizaciones.gestionar'), 403);

        $proyecto = Proyecto::find($request->proyecto_id);

        return Equipo::query()
            ->when($proyecto, fn ($q) => $q->where(fn ($q) => $q->where('proyecto_id', $proyecto->id)->orWhere('entidad_id', $proyecto->lugarDeServicio()->id)))
            ->when($request->entidad_id, fn ($q, $v) => $q->where('entidad_id', $v))
            ->buscar($request->q)
            ->where('estado', '!=', 'de_baja')
            ->orderBy('codigo')
            ->limit(50)
            ->get()
            ->map(fn (Equipo $e) => [
                'id' => $e->id,
                'codigo' => $e->codigo,
                'texto' => "{$e->codigo} · {$e->nombre()}".($e->ubicacion ? " · {$e->ubicacion}" : ''),
                'categoria' => $e->categoria,
                'marca' => $e->marca,
                'modelo' => $e->modelo,
                'serie' => $e->serie,
                'ubicacion' => $e->ubicacion,
                'estado' => $e->estado,
            ]);
    }

    public function importarForm(Request $request)
    {
        return view('equipos.importar', [
            'entidades' => Entidad::where('activo', true)->orderBy('nombre')->pluck('nombre', 'id'),
            'proyectos' => Proyecto::with('entidad')->enCurso()->orderBy('nombre')->get()
                ->mapWithKeys(fn ($p) => [$p->id => "{$p->codigo} · {$p->nombre} ({$p->entidad->nombre})"]),
            'entidadId' => $request->entidad_id,
            'proyectoId' => $request->proyecto_id,
        ]);
    }

    /** Carga masiva desde Excel (p. ej. las 20 laptops de un cliente). */
    public function importar(Request $request)
    {
        $request->validate([
            'entidad_id' => ['required', 'exists:entidades,id'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $proyecto = Proyecto::find($request->proyecto_id);
        $import = new EquiposImport(
            (int) $request->entidad_id,
            $proyecto?->id,
            $proyecto?->garantias()->activas()->orderByDesc('fecha_vencimiento')->value('id'),
        );

        DB::transaction(fn () => Excel::import($import, $request->file('archivo')));

        Auditor::log('crear', 'equipos', "Importó {$import->creados} equipo(s) desde Excel", Entidad::find($request->entidad_id));

        $mensaje = "Se importaron {$import->creados} equipo(s).";
        if ($import->omitidos) {
            $mensaje .= " Se omitieron {$import->omitidos} fila(s) porque la serie ya estaba registrada o faltaba la categoría.";
        }

        return redirect()->route('equipos.index', ['entidad_id' => $request->entidad_id])->with('ok', $mensaje);
    }

    public function plantilla()
    {
        return Excel::download(new PlantillaEquiposExport, 'Plantilla-equipos.xlsx');
    }

    private function datosFormulario(Equipo $equipo): array
    {
        $proyectos = Proyecto::with(['entidad', 'clienteFinal'])->enCurso()->orWhere('id', $equipo->proyecto_id)->orderBy('nombre')->get();

        return [
            'equipo' => $equipo,
            'entidades' => Entidad::where('activo', true)->orWhere('id', $equipo->entidad_id)->orderBy('nombre')->pluck('nombre', 'id'),
            'proyectos' => $proyectos->mapWithKeys(fn ($p) => [$p->id => "{$p->codigo} · {$p->nombre} ({$p->clientes()})"]),
            'garantias' => Garantia::with('proyecto')->whereIn('proyecto_id', $proyectos->pluck('id'))->orWhere('id', $equipo->garantia_id)->get()
                ->map(fn ($g) => ['id' => $g->id, 'proyecto_id' => $g->proyecto_id, 'texto' => "{$g->codigo} · {$g->tipo} (vence {$g->fecha_vencimiento->format('d/m/Y')})"]),
            'categorias' => Ajustes::catalogo('categorias_equipo'),
        ];
    }

    private function validar(Request $request, ?Equipo $equipo = null): array
    {
        return $request->validate([
            'entidad_id' => ['required', 'exists:entidades,id'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
            'garantia_id' => ['nullable', Rule::exists('garantias', 'id')->where('proyecto_id', $request->proyecto_id)],
            'categoria' => ['required', 'string', 'max:60'],
            'marca' => ['nullable', 'string', 'max:80'],
            'modelo' => ['nullable', 'string', 'max:80'],
            'serie' => ['nullable', 'string', 'max:80',
                Rule::unique('equipos')->where('entidad_id', $request->entidad_id)->whereNull('deleted_at')->ignore($equipo?->id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:100'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'fecha_instalacion' => ['nullable', 'date', 'before_or_equal:today'],
            'estado' => ['required', Rule::in(array_keys(Equipo::ESTADOS))],
            'observaciones' => ['nullable', 'string', 'max:3000'],
        ], [
            'serie.unique' => 'Ya existe un equipo con esa serie en este cliente.',
            'garantia_id.exists' => 'La garantía no pertenece al proyecto seleccionado.',
        ]);
    }
}
