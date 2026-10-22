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
            self::Parcial => 'No se terminó: continúa otro día',
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

    /** Resultados que obligan a programar una nueva visita. */
    public function requiereVisita(): bool
    {
        return in_array($this, [self::Parcial, self::PendienteRepuesto]);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
