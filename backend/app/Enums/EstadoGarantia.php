<?php

namespace App\Enums;

/** Estado calculado a partir de las fechas de vigencia (no se guarda en BD). */
enum EstadoGarantia: string
{
    case Vigente = 'vigente';
    case PorVencer = 'por_vencer';
    case Vencida = 'vencida';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::PorVencer => 'Próxima a vencer',
            self::Vencida => 'Vencida',
            self::Anulada => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Vigente => 'green',
            self::PorVencer => 'amber',
            self::Vencida => 'red',
            self::Anulada => 'slate',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
