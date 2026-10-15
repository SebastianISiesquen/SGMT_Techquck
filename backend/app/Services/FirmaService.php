<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda firmas manuscritas como PNG en el disco privado. Acepta dos formatos:
 *  - Web: data URL "data:image/png;base64,..." generado por un <canvas>.
 *  - App móvil: JSON {"w":ancho,"h":alto,"trazos":[[[x,y],[x,y],...], ...]} que se dibuja aquí con GD,
 *    así la app no necesita librerías nativas para exportar imágenes.
 */
class FirmaService
{
    private const ANCHO = 600;

    public static function guardar(string $valor, string $campo): string
    {
        $png = str_starts_with($valor, 'data:image/png;base64,')
            ? self::desdeDataUrl($valor, $campo)
            : self::desdeTrazos($valor, $campo);

        $ruta = 'firmas/'.now()->format('Y/m').'/'.Str::uuid().'.png';
        Storage::disk(ArchivoService::disco())->put($ruta, $png);

        return $ruta;
    }

    public static function base64(?string $ruta): ?string
    {
        $disk = Storage::disk(ArchivoService::disco());

        return $ruta && $disk->exists($ruta) ? 'data:image/png;base64,'.base64_encode($disk->get($ruta)) : null;
    }

    private static function desdeDataUrl(string $valor, string $campo): string
    {
        $binario = base64_decode(substr($valor, strlen('data:image/png;base64,')), true);

        if (! $binario || strlen($binario) > 1_000_000 || ! @imagecreatefromstring($binario)) {
            throw ValidationException::withMessages([$campo => 'La firma no es una imagen válida.']);
        }

        return $binario;
    }

    private static function desdeTrazos(string $json, string $campo): string
    {
        $datos = json_decode($json, true);
        $trazos = $datos['trazos'] ?? null;
        $w = (float) ($datos['w'] ?? 0);
        $h = (float) ($datos['h'] ?? 0);

        $puntos = is_array($trazos) ? array_sum(array_map(fn ($t) => is_array($t) ? count($t) : 0, $trazos)) : 0;
        if ($w <= 0 || $h <= 0 || $puntos < 5 || $puntos > 20000) {
            throw ValidationException::withMessages([$campo => 'La firma está vacía o no es válida. Firme nuevamente.']);
        }

        $escala = self::ANCHO / $w;
        $img = imagecreatetruecolor(self::ANCHO, max(1, (int) round($h * $escala)));
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $tinta = imagecolorallocate($img, 15, 23, 42);
        imagesetthickness($img, 3);

        foreach ($trazos as $trazo) {
            $previo = null;
            foreach ((array) $trazo as $p) {
                if (! is_array($p) || count($p) < 2) {
                    continue;
                }
                $actual = [(int) round($p[0] * $escala), (int) round($p[1] * $escala)];
                if ($previo) {
                    imageline($img, $previo[0], $previo[1], $actual[0], $actual[1], $tinta);
                } else {
                    imagefilledellipse($img, $actual[0], $actual[1], 3, 3, $tinta);
                }
                $previo = $actual;
            }
        }

        ob_start();
        imagepng($img);
        imagedestroy($img);

        return ob_get_clean();
    }
}
