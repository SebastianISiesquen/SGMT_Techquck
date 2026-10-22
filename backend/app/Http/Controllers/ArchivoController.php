<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaArchivo;
use App\Models\Archivo;
use App\Models\Atencion;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\HojaServicio;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Services\ArchivoService;
use App\Services\Auditor;
use App\Services\IncidenciaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Subida y descarga controlada de documentos y evidencias (RF10, RNF08). */
class ArchivoController extends Controller
{
    public function store(Request $request, string $tipo, int $id, IncidenciaService $incidencias)
    {
        $padre = match ($tipo) {
            'proyecto' => Proyecto::findOrFail($id),
            'garantia' => Garantia::findOrFail($id),
            'incidencia' => Incidencia::findOrFail($id),
            'atencion' => Atencion::findOrFail($id),
            'hoja_servicio' => HojaServicio::findOrFail($id),
            'equipo' => Equipo::findOrFail($id),
        };

        $this->autorizarSubida($request, $padre);

        $request->validate([
            'archivos' => ['required', 'array', 'max:15'],
            'archivos.*' => ArchivoService::reglas(),
            'categoria' => ['required', Rule::enum(CategoriaArchivo::class)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($request->file('archivos') as $file) {
            ArchivoService::guardar($file, $padre, ArchivoService::categoriaPara($file, $request->categoria), $request->descripcion);
        }

        $cantidad = count($request->file('archivos'));
        $categoria = CategoriaArchivo::from($request->categoria)->label();
        Auditor::log('adjuntar', $tipo.'s', "Adjuntó $cantidad archivo(s) [$categoria] a $tipo #$id", $padre);

        // El acta firmada por el cliente deja la hoja de servicio conforme
        if ($padre instanceof HojaServicio && $request->categoria === 'acta_conformidad') {
            app(\App\Services\HojaServicioService::class)->registrarActa($padre);
        }

        $incidencia = $padre instanceof Atencion ? $padre->incidencia : ($padre instanceof Incidencia ? $padre : null);
        if ($incidencia) {
            $incidencias->historial($incidencia, 'evidencia', null, null, "$cantidad archivo(s): $categoria");
        }

        return back()->with('ok', 'Archivo(s) subido(s) correctamente.');
    }

    /** Muestra el archivo en el navegador (imágenes y PDF). */
    public function ver(Archivo $archivo)
    {
        Gate::authorize('view', $archivo);
        $disk = Storage::disk(ArchivoService::disco());
        abort_unless($disk->exists($archivo->ruta), 404, 'El archivo no se encuentra en el servidor.');

        return $disk->response($archivo->ruta, $archivo->nombre_original, [
            'Content-Type' => $archivo->mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function descargar(Archivo $archivo)
    {
        Gate::authorize('view', $archivo);
        $disk = Storage::disk(ArchivoService::disco());
        abort_unless($disk->exists($archivo->ruta), 404, 'El archivo no se encuentra en el servidor.');

        return $disk->download($archivo->ruta, $archivo->nombre_original);
    }

    public function destroy(Archivo $archivo)
    {
        Gate::authorize('delete', $archivo);

        $padre = $archivo->archivable;
        ArchivoService::eliminar($archivo);

        // Si se quitó la única acta de una hoja, vuelve a quedar pendiente de acta
        if ($padre instanceof HojaServicio && $archivo->categoria->value === 'acta_conformidad'
            && $padre->estado->value === 'conforme' && ! $padre->archivos()->where('categoria', 'acta_conformidad')->exists()) {
            $padre->update(['estado' => 'registrada', 'conforme' => false, 'fecha_firma' => null]);
        }
        Auditor::log('eliminar', 'archivos', "Eliminó el archivo {$archivo->nombre_original}", $archivo->archivable);

        return back()->with('ok', 'Archivo eliminado.');
    }

    private function autorizarSubida(Request $request, $padre): void
    {
        $user = $request->user();

        $permitido = match (true) {
            $padre instanceof Proyecto => $user->can('proyectos.gestionar'),
            $padre instanceof Garantia => $user->can('garantias.gestionar'),
            $padre instanceof Incidencia => $user->can('adjuntar', $padre),
            $padre instanceof Atencion => $user->can('adjuntar', $padre->incidencia),
            $padre instanceof HojaServicio => $user->can('view', $padre) && $padre->estado->value !== 'anulada'
                && ($padre->tecnico_id === $user->id || $user->can('hojas.ver')),
            $padre instanceof Equipo => $user->can('equipos.gestionar'),
        };

        abort_unless($permitido, 403);
    }
}
