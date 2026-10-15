<?php

namespace App\Http\Controllers;

use App\Enums\EstadoIncidencia;
use App\Enums\ResultadoAtencion;
use App\Http\Requests\AtencionRequest;
use App\Models\Incidencia;
use App\Services\IncidenciaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Bandeja del Técnico en la web: incidencias asignadas y registro de atención (RF09). */
class MisIncidenciasController extends Controller
{
    public function __construct(private IncidenciaService $servicio) {}

    public function index(Request $request)
    {
        $base = Incidencia::where('tecnico_id', $request->user()->id);

        $incidencias = (clone $base)
            ->with('proyecto.entidad')
            ->filtrar(['estado' => $request->input('estado', 'abiertas'), 'buscar' => $request->buscar])
            ->orderByRaw("FIELD(prioridad, 'critica', 'alta', 'media', 'baja')")
            ->latest('fecha_reporte')
            ->paginate(15)->withQueryString();

        $resumen = [
            'asignadas' => (clone $base)->where('estado', EstadoIncidencia::Asignada)->count(),
            'en_proceso' => (clone $base)->where('estado', EstadoIncidencia::EnProceso)->count(),
            'resueltas_mes' => (clone $base)->whereIn('estado', ['resuelta', 'cerrada'])->where('fecha_resolucion', '>=', now()->startOfMonth())->count(),
        ];

        return view('mis-incidencias.index', compact('incidencias', 'resumen'));
    }

    public function show(Incidencia $incidencia)
    {
        Gate::authorize('view', $incidencia);

        $incidencia->load(['proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'hojasServicio', 'atenciones.tecnico', 'atenciones.archivos', 'archivos', 'historial.usuario',
            'equipo.eventos' => fn ($q) => $q->where(fn ($q) => $q->where('incidencia_id', '!=', $incidencia->id)->orWhereNull('incidencia_id'))
                ->whereIn('tipo', ['falla', 'diagnostico', 'correctivo', 'cambio_componente', 'mantenimiento', 'observacion'])->limit(6)]);

        return view('mis-incidencias.show', [
            'incidencia' => $incidencia,
            'resultados' => ResultadoAtencion::options(),
            'puedeAtender' => request()->user()->can('atender', $incidencia)
                && in_array($incidencia->estado, [EstadoIncidencia::Asignada, EstadoIncidencia::EnProceso]),
        ]);
    }

    public function iniciar(Incidencia $incidencia)
    {
        Gate::authorize('atender', $incidencia);
        $this->servicio->iniciar($incidencia);

        return back()->with('ok', 'Atención iniciada.');
    }

    public function atender(AtencionRequest $request, Incidencia $incidencia)
    {
        Gate::authorize('atender', $incidencia);

        $this->servicio->registrarAtencion($incidencia, $request->datosAtencion(), $request->archivosAdjuntos(), 'web');

        return redirect()->route('mis-incidencias.show', $incidencia)->with('ok', 'Atención registrada correctamente.');
    }
}
