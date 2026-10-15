<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;

/** Registro de auditoría de las principales acciones de los usuarios (RF16 / RNF07). */
class Auditor
{
    public static function log(string $accion, string $modulo, string $descripcion, ?Model $modelo = null, ?array $datos = null): void
    {
        $request = request();

        Auditoria::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'modulo' => $modulo,
            'auditable_type' => $modelo?->getMorphClass(),
            'auditable_id' => $modelo?->getKey(),
            'descripcion' => $descripcion,
            'datos' => $datos,
            'ip' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
        ]);
    }

    /** Solo los campos que realmente cambiaron, para no llenar la auditoría de ruido. */
    public static function cambios(Model $modelo): array
    {
        $cambios = [];

        foreach ($modelo->getChanges() as $campo => $nuevo) {
            if ($campo === 'updated_at') {
                continue;
            }
            $anterior = $modelo->getOriginal($campo);
            $cambios[$campo] = [
                'antes' => $anterior instanceof \BackedEnum ? $anterior->value : (string) $anterior,
                'despues' => $nuevo instanceof \BackedEnum ? $nuevo->value : (string) $nuevo,
            ];
        }

        return $cambios;
    }
}
