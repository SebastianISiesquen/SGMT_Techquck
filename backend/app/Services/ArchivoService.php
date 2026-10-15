<?php

namespace App\Services;

use App\Models\Archivo;
use App\Support\Ajustes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda documentos y evidencias en un disco privado (no accesible por URL pública).
 * Solo se descargan a través de rutas que validan permisos (RNF08).
 */
class ArchivoService
{
    public static function disco(): string
    {
        return config('filesystems.archivos_disk', 'local');
    }

    public static function reglas(bool $requerido = true): array
    {
        return [
            $requerido ? 'required' : 'nullable',
            'file',
            'max:'.(Ajustes::int('max_archivo_mb') * 1024),
            'extensions:'.implode(',', config('sggp.extensiones_permitidas')),
        ];
    }

    public static function guardar(UploadedFile $file, Model $archivable, string $categoria, ?string $descripcion = null): Archivo
    {
        $carpeta = sprintf('archivos/%s/%s', $archivable->getMorphClass(), now()->format('Y/m'));
        $nombre = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());

        $ruta = $file->storeAs($carpeta, $nombre, self::disco());

        return Archivo::create([
            'archivable_type' => $archivable->getMorphClass(),
            'archivable_id' => $archivable->getKey(),
            'categoria' => $categoria,
            'nombre_original' => Str::limit($file->getClientOriginalName(), 250, ''),
            'ruta' => $ruta,
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'tamano' => $file->getSize(),
            'descripcion' => $descripcion,
            'subido_por' => auth()->id(),
        ]);
    }

    /** Las fotos se clasifican automáticamente aunque el usuario elija otra categoría genérica. */
    public static function categoriaPara(UploadedFile $file, ?string $categoria): string
    {
        if ($categoria && $categoria !== 'documento') {
            return $categoria;
        }

        return str_starts_with((string) $file->getMimeType(), 'image/') ? 'foto' : ($categoria ?: 'documento');
    }

    public static function eliminar(Archivo $archivo): void
    {
        Storage::disk(self::disco())->delete($archivo->ruta);
        $archivo->delete();
    }

    public static function contenidoBase64(Archivo $archivo): ?string
    {
        $disk = Storage::disk(self::disco());

        if (! $archivo->esImagen() || ! $disk->exists($archivo->ruta)) {
            return null;
        }

        return 'data:'.$archivo->mime.';base64,'.base64_encode($disk->get($archivo->ruta));
    }
}
