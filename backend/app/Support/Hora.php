<?php

namespace App\Support;

/** Horas en formato de 12 horas para informes y reportes: «03:45 PM». */
class Hora
{
    public static function doce(?string $hora): string
    {
        if (! $hora) {
            return '';
        }

        $t = strtotime($hora);

        return $t === false ? $hora : date('h:i A', $t);
    }
}
