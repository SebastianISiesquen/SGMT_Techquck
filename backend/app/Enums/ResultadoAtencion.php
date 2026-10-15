<?php

namespace App\Enums;

enum ResultadoAtencion: string
{
    case Resuelto = 'resuelto';
    case Parcial = 'parcial';
    case PendienteRepuesto = 'pendiente_repuesto';
    case NoProcede = 'no_procede';

    public function label(): string
    {
        return match ($this) {
            self::Resuelto => 'Resuelto',
            self::Parcial => 'Atención parcial',
            self::PendienteRepuesto => 'Pendiente de repuesto',
            self::NoProcede => 'No procede (fuera de garantía)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Resuelto => 'green',
            self::Parcial => 'amber',
            self::PendienteRepuesto => 'blue',
            self::NoProcede => 'red',
        };
    }

    /** Resultados que dejan la incidencia lista para cierre del supervisor. */
    public function finaliza(): bool
    {
        return in_array($this, [self::Resuelto, self::NoProcede]);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
