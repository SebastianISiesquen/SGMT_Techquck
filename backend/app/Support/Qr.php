<?php

namespace App\Support;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

/** Códigos QR para los stickers de los equipos (PNG con GD, válido en web y en PDF). */
class Qr
{
    public static function png(string $contenido, int $tamano = 240): string
    {
        return (new Writer(new GDLibRenderer($tamano, margin: 1)))->writeString($contenido);
    }

    public static function dataUri(string $contenido, int $tamano = 240): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($contenido, $tamano));
    }
}
