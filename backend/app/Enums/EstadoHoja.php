<?php

namespace App\Enums;

/**
 * La conformidad del cliente es el acta firmada (foto o PDF) que se sube a la hoja:
 * Registrada (falta el acta) → Conforme (acta recibida). También puede anularse.
 */
enum EstadoHoja: string
{
    case Registrada = 'registrada';
    case Conforme = 'conforme';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Registrada => 'Pendiente de acta',
            self::Conforme => 'Con acta de conformidad',
            self::Anulada => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Registrada => 'amber',
            self::Conforme => 'green',
            self::Anulada => 'red',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
