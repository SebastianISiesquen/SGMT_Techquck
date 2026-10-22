<?php

namespace App\Services\Pdf;

use App\Models\Archivo;
use App\Services\ArchivoService;
use App\Support\Ajustes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Prepara imágenes para los PDF: corrige la orientación de las fotos del celular,
 * las reduce (el PDF pesa menos) y calcula el tamaño para que entren en un recuadro
 * fijo sin deformarse, centradas.
 */
class Imagenes
{
    /** Recuadro de cada foto en el registro fotográfico (en puntos del PDF). */
    public const ANCHO = 245;

    public const ALTO = 175;

    /**
     * @param  iterable<Archivo>  $archivos
     * @return Collection<int, array{src: string, ancho: int, alto: int, margen: int, leyenda: string}>
     */
    public static function paraPdf(iterable $archivos, int $anchoCaja = self::ANCHO, int $altoCaja = self::ALTO): Collection
    {
        return collect($archivos)
            ->map(function (Archivo $a) use ($anchoCaja, $altoCaja) {
                $foto = self::preparar($a);
                if (! $foto) {
                    return null;
                }
                [$src, $w, $h] = $foto;
                $escala = min($anchoCaja / $w, $altoCaja / $h);
                $ancho = (int) round($w * $escala);
                $alto = (int) round($h * $escala);

                return [
                    'src' => $src,
                    'ancho' => $ancho,
                    'alto' => $alto,
                    'margen' => (int) floor(($altoCaja - $alto) / 2),
                    'leyenda' => $a->descripcion ?: pathinfo($a->nombre_original, PATHINFO_FILENAME),
                ];
            })
            ->filter()
            ->values();
    }

    /** Logo de la empresa (Configuración) como data URI, o null si no se subió. */
    public static function logo(): ?string
    {
        $ruta = Ajustes::get('empresa_logo');
        $disk = Storage::disk(ArchivoService::disco());
        if (! $ruta || ! $disk->exists($ruta)) {
            return null;
        }

        $mime = str_ends_with(strtolower($ruta), '.png') ? 'image/png' : 'image/jpeg';

        return "data:$mime;base64,".base64_encode($disk->get($ruta));
    }

    /** @return array{0: string, 1: int, 2: int}|null  data URI JPEG, ancho y alto */
    private static function preparar(Archivo $a, int $maximo = 1000): ?array
    {
        $disk = Storage::disk(ArchivoService::disco());
        if (! $a->esImagen() || ! $disk->exists($a->ruta)) {
            return null;
        }

        $binario = $disk->get($a->ruta);
        $img = @imagecreatefromstring($binario);
        if (! $img) {
            return null;
        }

        // Las fotos del celular guardan la rotación en EXIF: se aplica para que no salgan de costado
        if (function_exists('exif_read_data') && in_array($a->mime, ['image/jpeg', 'image/jpg'])) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binario));
            $img = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        }

        $w = imagesx($img);
        $h = imagesy($img);
        // Se copia sobre fondo blanco (un PNG transparente pasado a JPEG quedaría negro) y se reduce si es grande
        $escala = min(1, $maximo / max($w, $h));
        $nw = (int) round($w * $escala);
        $nh = (int) round($h * $escala);
        $lienzo = imagecreatetruecolor($nw, $nh);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        [$img, $w, $h] = [$lienzo, $nw, $nh];

        ob_start();
        imagejpeg($img, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($img);

        return ['data:image/jpeg;base64,'.base64_encode($jpeg), $w, $h];
    }
}
