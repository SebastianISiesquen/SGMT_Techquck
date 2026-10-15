<?php

namespace App\Enums;

enum EstadoHoja: string
{
    case PendienteFirma = 'pendiente_firma';
    case Firmada = 'firmada';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::PendienteFirma => 'Pendiente de firma',
            self::Firmada => 'Firmada',
            self::Anulada => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendienteFirma => 'amber',
            self::Firmada => 'green',
            self::Anulada => 'red',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
