<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoriaArchivo;
use App\Enums\EstadoIncidencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\AtencionRequest;
use App\Http\Resources\IncidenciaResource;
use App\Models\Archivo;
use App\Models\Incidencia;
use App\Services\ArchivoService;
use App\Services\Auditor;
use App\Services\IncidenciaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IncidenciaApiController extends Controller
{
    public function __construct(private IncidenciaService $servicio) {}

    public function resumen(Request $request)
    {
        $base = Incidencia::where('tecnico_id', $request->user()->id);

        return [
            'asignadas' => (clone $base)->where('estado', EstadoIncidencia::Asignada)->count(),
            'en_proceso' => (clone $base)->where('estado', EstadoIncidencia::EnProceso)->count(),
            'resueltas_mes' => (clone $base)->whereNotNull('fecha_resolucion')->where('fecha_resolucion', '>=', now()->startOfMonth())->count(),
            'criticas' => (clone $base)->abiertas()->whereIn('prioridad', ['critica', 'alta'])->count(),
        ];
    }

    /** Incidencias asignadas al técnico autenticado. ?estado=abiertas|resuelta|cerrada|... */
    public function index(Request $request)
    {
        $incidencias = Incidencia::where('tecnico_id', $request->user()->id)
            ->with(['proyecto.entidad', 'proyecto.clienteFinal'])
            ->filtrar(['estado' => $request->input('estado', 'abiertas'), 'buscar' => $request->buscar])
            ->orderByRaw("FIELD(prioridad, 'critica', 'alta', 'media', 'baja')")
            ->latest('fecha_reporte')
            ->paginate(20);

        return IncidenciaResource::collection($incidencias);
    }

    public function show(Incidencia $incidencia)
    {
        Gate::authorize('view', $incidencia);

        $incidencia->load(['proyecto.entidad', 'proyecto.clienteFinal', 'garantia', 'hojasServicio',
            'equipo.eventos' => fn ($q) => $q->where(fn ($q) => $q->where('incidencia_id', '!=', $incidencia->id)->orWhereNull('incidencia_id'))
                ->whereIn('tipo', ['falla', 'diagnostico', 'correctivo', 'cambio_componente', 'mantenimiento', 'observacion'])->limit(6), 'tecnico', 'atenciones.tecnico', 'atenciones.archivos', 'archivos', 'historial.usuario']);

        return new IncidenciaResource($incidencia);
    }

    public function iniciar(Incidencia $incidencia)
    {
        Gate::authorize('atender', $incidencia);

        return new IncidenciaResource($this->servicio->iniciar($incidencia)->fresh(['proyecto.entidad']));
    }

    /** multipart/form-data: campos de la atención + archivos[] + categorias[] + descripciones[] */
    public function atender(AtencionRequest $request, Incidencia $incidencia)
    {
        Gate::authorize('atender', $incidencia);

        $this->servicio->registrarAtencion($incidencia, $request->datosAtencion(), $request->archivosAdjuntos(), 'movil');

        return $this->show($incidencia->fresh())->response()->setStatusCode(201);
    }

    /** Sube una evidencia suelta (ej. el acta firmada escaneada después). */
    public function subirArchivo(Request $request, Incidencia $incidencia)
    {
        Gate::authorize('adjuntar', $incidencia);

        $request->validate([
            'archivo' => ArchivoService::reglas(),
            'categoria' => ['required', Rule::enum(CategoriaArchivo::class)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        // Si la incidencia ya tiene atenciones, la evidencia se asocia a la última.
        $destino = $incidencia->atenciones()->first() ?? $incidencia;
        $archivo = ArchivoService::guardar($request->file('archivo'), $destino,
            ArchivoService::categoriaPara($request->file('archivo'), $request->categoria), $request->descripcion);

        $this->servicio->historial($incidencia, 'evidencia', null, null, $archivo->categoria->label().' (app móvil)');
        Auditor::log('adjuntar', 'incidencias', "Adjuntó {$archivo->categoria->label()} a {$incidencia->codigo} desde la app", $incidencia);

        return response()->json(IncidenciaResource::archivo($archivo), 201);
    }

    public function archivo(Archivo $archivo)
    {
        Gate::authorize('view', $archivo);
        $disk = Storage::disk(ArchivoService::disco());
        abort_unless($disk->exists($archivo->ruta), 404);

        return $disk->response($archivo->ruta, $archivo->nombre_original, ['Content-Type' => $archivo->mime]);
    }
}
