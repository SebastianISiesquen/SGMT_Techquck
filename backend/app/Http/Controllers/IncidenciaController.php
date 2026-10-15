<?php

namespace App\Http\Controllers;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Models\Cotizacion;
use App\Models\Entidad;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\ArchivoService;
use App\Services\Auditor;
use App\Services\IncidenciaService;
use App\Support\Ajustes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IncidenciaController extends Controller
{
    public function __construct(private IncidenciaService $servicio) {}

    public function index(Request $request)
    {
        $incidencias = Incidencia::query()
            ->with(['proyecto.entidad', 'tecnico'])
            ->filtrar($request->all())
            ->latest('fecha_reporte')
            ->paginate(15)->withQueryString();

        return view('incidencias.index', [
            'incidencias' => $incidencias,
            'estados' => ['abiertas' => 'Abiertas (pendientes)'] + EstadoIncidencia::options(),
            'prioridades' => Prioridad::options(),
            'tecnicos' => User::tecnicos()->pluck('name', 'id'),
            'entidades' => Entidad::orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }

    public function create(Request $request)
    {
        // Orden de trabajo a partir de una cotización aprobada
        $cotizacion = $request->cotizacion_id ? Cotizacion::where('estado', 'aprobada')->find($request->cotizacion_id) : null;

        return view('incidencias.form', [...$this->datosFormulario(new Incidencia([
            'proyecto_id' => $cotizacion?->proyecto_id ?? $request->proyecto_id,
            'garantia_id' => $request->garantia_id,
            'equipo_id' => $cotizacion?->equipo_id ?? $request->equipo_id,
            'titulo' => $cotizacion ? "Trabajo cotizado: {$cotizacion->asunto}" : null,
            'descripcion' => $cotizacion ? "Cotización {$cotizacion->numero} aprobada por el cliente.\n".($cotizacion->diagnostico ?? '') : null,
            'cobertura' => $cotizacion ? 'sin_cobertura' : 'garantia',
            'prioridad' => Prioridad::Media,
            'fecha_reporte' => now(),
        ])), 'cotizacion' => $cotizacion]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);
        $datos['tecnico_id'] = $request->user()->can('incidencias.asignar') ? $request->tecnico_id : null;

        $incidencia = $this->servicio->registrar($datos);

        if ($request->cotizacion_id) {
            Cotizacion::whereKey($request->cotizacion_id)->whereNull('incidencia_id')->update(['incidencia_id' => $incidencia->id]);
        }

        foreach ($request->file('archivos', []) as $file) {
            ArchivoService::guardar($file, $incidencia, ArchivoService::categoriaPara($file, 'documento'));
        }

        return redirect()->route('incidencias.show', $incidencia)->with('ok', "Incidencia {$incidencia->codigo} registrada.");
    }

    public function show(Incidencia $incidencia)
    {
        Gate::authorize('view', $incidencia);

        // El técnico sin permisos de supervisión usa su propia vista.
        if (! request()->user()->can('incidencias.ver')) {
            return redirect()->route('mis-incidencias.show', $incidencia);
        }

        $incidencia->load(['proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'cotizaciones', 'hojasServicio.tecnico', 'tecnico',
            // Antecedentes del equipo: lo ocurrido antes de esta incidencia.
            'equipo.eventos' => fn ($q) => $q->where(fn ($q) => $q->where('incidencia_id', '!=', $incidencia->id)->orWhereNull('incidencia_id'))
                ->whereIn('tipo', ['falla', 'diagnostico', 'correctivo', 'cambio_componente', 'mantenimiento', 'observacion'])->limit(6), 'asignador', 'registrador', 'cerrador',
            'atenciones.tecnico', 'atenciones.archivos', 'archivos.autor', 'historial.usuario']);

        return view('incidencias.show', [
            'incidencia' => $incidencia,
            'tecnicos' => User::tecnicos()->withCount(['incidenciasAsignadas as carga' => fn ($q) => $q->abiertas()])->get(),
            'requiereActa' => (bool) Ajustes::int('requerir_acta_cierre'),
        ]);
    }

    public function edit(Incidencia $incidencia)
    {
        if (! $incidencia->estaAbierta()) {
            return back()->withErrors(['general' => 'Solo se pueden editar incidencias abiertas.']);
        }

        return view('incidencias.form', $this->datosFormulario($incidencia));
    }

    public function update(Request $request, Incidencia $incidencia)
    {
        $incidencia->update($this->validar($request));
        $cambios = Auditor::cambios($incidencia);

        if ($cambios) {
            $this->servicio->historial($incidencia, 'edicion', null, null, 'Campos editados: '.implode(', ', array_keys($cambios)));
            Auditor::log('editar', 'incidencias', "Editó la incidencia {$incidencia->codigo}", $incidencia, $cambios);
        }

        return redirect()->route('incidencias.show', $incidencia)->with('ok', 'Incidencia actualizada.');
    }

    public function asignar(Request $request, Incidencia $incidencia)
    {
        $datos = $request->validate([
            'tecnico_id' => ['required', 'exists:users,id'],
            'fecha_limite' => ['nullable', 'date'],
            'comentario' => ['nullable', 'string', 'max:500'],
        ]);

        $this->servicio->asignar($incidencia, User::findOrFail($datos['tecnico_id']), $datos['fecha_limite'] ?? null, $datos['comentario'] ?? null);

        return back()->with('ok', 'Incidencia asignada. Se notificó al técnico.');
    }

    public function cerrar(Request $request, Incidencia $incidencia)
    {
        $request->validate(['comentario' => ['nullable', 'string', 'max:500']]);
        $this->servicio->cerrar($incidencia, $request->comentario);

        return back()->with('ok', 'Incidencia cerrada.');
    }

    public function reabrir(Request $request, Incidencia $incidencia)
    {
        $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $this->servicio->reabrir($incidencia, $request->motivo);

        return back()->with('ok', 'Incidencia reabierta y devuelta al técnico.');
    }

    public function anular(Request $request, Incidencia $incidencia)
    {
        $request->validate(['motivo' => ['required', 'string', 'max:500']]);
        $this->servicio->anular($incidencia, $request->motivo);

        return back()->with('ok', 'Incidencia anulada.');
    }

    /** Informe técnico en PDF con atenciones y fotografías (para adjuntar al expediente). */
    public function informe(Incidencia $incidencia)
    {
        Gate::authorize('view', $incidencia);

        $incidencia->load(['proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'equipo', 'tecnico', 'atenciones.tecnico', 'atenciones.archivos', 'archivos', 'historial.usuario']);

        $fotos = $incidencia->todasLasEvidencias()
            ->filter(fn ($a) => $a->esImagen())
            ->take(12)
            ->map(fn ($a) => ['src' => ArchivoService::contenidoBase64($a), 'nombre' => $a->descripcion ?: $a->nombre_original])
            ->filter(fn ($f) => $f['src']);

        return Pdf::loadView('pdf.informe-incidencia', [
            'incidencia' => $incidencia,
            'fotos' => $fotos,
            'empresa' => Ajustes::all(),
        ])->setPaper('a4')->stream("Informe-{$incidencia->codigo}.pdf");
    }

    private function datosFormulario(Incidencia $incidencia): array
    {
        $proyectos = Proyecto::with('entidad')->where('estado', '!=', 'finalizado')->orWhere('id', $incidencia->proyecto_id)
            ->orderBy('nombre')->get()
            ->mapWithKeys(fn ($p) => [$p->id => "{$p->codigo} · {$p->nombre} ({$p->entidad->nombre})"]);

        return [
            'incidencia' => $incidencia,
            'proyectos' => $proyectos,
            'prioridades' => Prioridad::options(),
            'canales' => Ajustes::catalogo('canales_reporte'),
            'tecnicos' => User::tecnicos()->pluck('name', 'id'),
        ];
    }

    private function validar(Request $request): array
    {
        $request->validate([
            'archivos' => ['nullable', 'array', 'max:10'],
            'archivos.*' => ArchivoService::reglas(),
            'tecnico_id' => ['nullable', 'exists:users,id'],
        ]);

        $datos = $request->validate([
            'proyecto_id' => ['required', 'exists:proyectos,id'],
            'garantia_id' => ['nullable', Rule::exists('garantias', 'id')->where('proyecto_id', $request->proyecto_id)],
            'equipo_id' => ['nullable', 'integer', function ($attr, $valor, $fail) use ($request) {
                $proyecto = Proyecto::find($request->proyecto_id);
                if ($proyecto && ! ProyectoController::equiposDe($proyecto)->contains('id', (int) $valor)) {
                    $fail('El equipo no pertenece al proyecto ni al cliente seleccionado.');
                }
            }],
            'cobertura' => ['nullable', Rule::in(array_keys(Incidencia::COBERTURAS))],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string'],
            'prioridad' => ['required', Rule::enum(Prioridad::class)],
            'canal' => ['nullable', 'string', 'max:20'],
            'reportado_por' => ['nullable', 'string', 'max:255'],
            'ubicacion_detalle' => ['nullable', 'string', 'max:255'],
            'fecha_reporte' => ['required', 'date', 'before_or_equal:now'],
            'fecha_limite' => ['nullable', 'date', 'after:fecha_reporte'],
        ], [
            'garantia_id.exists' => 'La garantía seleccionada no pertenece al proyecto.',
            'fecha_reporte.before_or_equal' => 'La fecha de reporte no puede ser posterior a la hora actual.',
        ]);

        return [...$datos, 'cobertura' => $datos['cobertura'] ?? 'garantia'];
    }
}
