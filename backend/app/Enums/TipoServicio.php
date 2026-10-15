<?php

namespace App\Enums;

enum TipoServicio: string
{
    case Correctivo = 'correctivo';
    case Preventivo = 'preventivo';
    case Instalacion = 'instalacion';
    case Inspeccion = 'inspeccion';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Correctivo => 'Mantenimiento correctivo',
            self::Preventivo => 'Mantenimiento preventivo',
            self::Instalacion => 'Instalación / reposición',
            self::Inspeccion => 'Inspección técnica',
            self::Otro => 'Otro',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
