<?php

namespace App\Enums;

enum EstadoIncidencia: string
{
    case Registrada = 'registrada';
    case Asignada = 'asignada';
    case EnProceso = 'en_proceso';
    case Resuelta = 'resuelta';
    case Cerrada = 'cerrada';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Registrada => 'Registrada',
            self::Asignada => 'Asignada',
            self::EnProceso => 'En proceso',
            self::Resuelta => 'Resuelta',
            self::Cerrada => 'Cerrada',
            self::Anulada => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Registrada => 'slate',
            self::Asignada => 'blue',
            self::EnProceso => 'amber',
            self::Resuelta => 'violet',
            self::Cerrada => 'green',
            self::Anulada => 'red',
        };
    }

    /** Estados en los que la incidencia sigue requiriendo trabajo. */
    public static function abiertos(): array
    {
        return [self::Registrada->value, self::Asignada->value, self::EnProceso->value];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
