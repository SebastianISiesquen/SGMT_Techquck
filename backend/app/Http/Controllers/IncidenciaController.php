<?php

namespace App\Http\Controllers;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Models\Cotizacion;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\ArchivoService;
use App\Services\Auditor;
use App\Services\IncidenciaService;
use App\Services\InformeEquipoService;
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
            'titulo' => $cotizacion ? "Trabajo cotizado: {$cotizacion->asunto}" : null,
            'descripcion' => $cotizacion ? "Cotización {$cotizacion->numero} aprobada por el cliente.\n".($cotizacion->diagnostico ?? '') : null,
            'cobertura' => $cotizacion ? 'sin_cobertura' : 'garantia',
            'prioridad' => Prioridad::Media,
            'fecha_reporte' => now(),
        ])), 'cotizacion' => $cotizacion,
            // «Reportar falla» desde la ficha de un equipo o la orden de trabajo de una cotización
            'equiposIniciales' => array_values(array_filter([$cotizacion?->equipo_id ?? $request->equipo_id]))]);
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
            // Antecedentes de cada equipo: lo ocurrido antes de esta incidencia.
            ...$incidencia->cargaDeAntecedentes(), 'origen', 'derivadas', 'asignador', 'registrador', 'cerrador',
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

        return view('incidencias.form', [...$this->datosFormulario($incidencia), 'equiposIniciales' => $incidencia->equipos()->pluck('equipos.id')->all()]);
    }

    public function update(Request $request, Incidencia $incidencia)
    {
        $datos = $this->validar($request);
        $equipos = $datos['equipos'];
        unset($datos['equipos']);

        $incidencia->update($datos);
        $cambios = Auditor::cambios($incidencia);
        $this->servicio->actualizarEquipos($incidencia, $equipos);

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

    /** El supervisor fija o cambia la fecha de la próxima visita (p. ej. el cliente pidió otro día). */
    public function reprogramar(Request $request, Incidencia $incidencia)
    {
        abort_unless($incidencia->estaAbierta(), 422, 'La incidencia ya no está abierta.');

        $datos = $request->validate([
            'proxima_visita' => ['required', 'date', 'after_or_equal:today'],
            'motivo_reprogramacion' => ['nullable', 'string', 'max:255'],
        ], ['proxima_visita.after_or_equal' => 'La fecha no puede ser pasada.']);

        $incidencia->update($datos);
        $motivo = $datos['motivo_reprogramacion'] ?? null;
        $detalle = "Próxima visita el {$incidencia->proxima_visita->format('d/m/Y')}".($motivo ? ": $motivo" : '');
        $this->servicio->historial($incidencia, 'reprogramacion', null, null, $detalle);
        Auditor::log('editar', 'incidencias', "{$incidencia->codigo}: $detalle", $incidencia);
        $incidencia->tecnico?->notify(new \App\Notifications\VisitaReprogramada($incidencia));

        return back()->with('ok', 'Visita programada. Se avisó al técnico.');
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

        $incidencia->load(['proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'equipos', 'tecnico', 'atenciones.tecnico', 'atenciones.archivos',
            'archivos', 'historial.usuario', 'hojasServicio' => fn ($q) => $q->with(['tecnico', 'equipos', 'materiales'])]);

        // Fotos de la incidencia, sus atenciones y sus hojas (sin las actas), encuadradas para el PDF
        $fotos = \App\Services\Pdf\Imagenes::paraPdf($incidencia->todasLasEvidencias()
            ->filter(fn ($a) => $a->esImagen() && $a->categoria->value !== 'acta_conformidad')->take(12));

        return Pdf::loadView('pdf.informe-incidencia', [
            'incidencia' => $incidencia,
            'fotos' => $fotos,
            'empresa' => Ajustes::all(),
        ])->setPaper('a4')->stream("Informe-{$incidencia->codigo}.pdf");
    }

    /** Informe técnico de un equipo reparado: su falla, diagnóstico, trabajo, repuestos, fotos y estado final. */
    public function informeEquipo(Incidencia $incidencia, Equipo $equipo)
    {
        Gate::authorize('view', $incidencia);
        abort_unless($incidencia->equipos()->whereKey($equipo->id)->exists(), 404);

        return $this->pdfInformesEquipos($incidencia, collect([$equipo]), "Informe-{$incidencia->codigo}-{$equipo->codigo}.pdf");
    }

    /** Todos los informes técnicos de la incidencia en un solo PDF (una sección por equipo). */
    public function informesEquipos(Incidencia $incidencia)
    {
        Gate::authorize('view', $incidencia);
        abort_if($incidencia->equipos()->doesntExist(), 404);

        return $this->pdfInformesEquipos($incidencia, $incidencia->equipos, "Informes-{$incidencia->codigo}.pdf");
    }

    private function pdfInformesEquipos(Incidencia $incidencia, $equipos, string $archivo)
    {
        $incidencia->load(['proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'tecnico', 'origen']);

        return Pdf::loadView('pdf.informe-equipo', [
            'incidencia' => $incidencia,
            'informes' => InformeEquipoService::informes($incidencia, $equipos),
            'empresa' => Ajustes::all(),
        ])->setPaper('a4')->stream($archivo);
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

        $proyecto = Proyecto::find($request->proyecto_id);
        $permitidos = $proyecto ? ProyectoController::equiposDe($proyecto)->pluck('id') : collect();

        $datos = $request->validate([
            'proyecto_id' => ['required', 'exists:proyectos,id'],
            'garantia_id' => ['nullable', Rule::exists('garantias', 'id')->where('proyecto_id', $request->proyecto_id)],
            // Equipos con falla: uno o varios de los equipos instalados en el proyecto o en el cliente
            'equipos' => ['nullable', 'array', 'max:100'],
            'equipos.*' => ['integer', 'distinct', function ($attr, $valor, $fail) use ($proyecto, $permitidos) {
                if ($proyecto && ! $permitidos->contains((int) $valor)) {
                    $fail('Uno de los equipos no pertenece al proyecto ni al cliente seleccionado.');
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

        return [...$datos, 'equipos' => array_map('intval', $datos['equipos'] ?? []), 'cobertura' => $datos['cobertura'] ?? 'garantia'];
    }
}
