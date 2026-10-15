<?php

namespace App\Support;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso a la configuración editable por el Administrador, con valores
 * por defecto en config/sggp.php y caché para no consultar la BD en cada uso.
 */
class Ajustes
{
    private const CACHE_KEY = 'sggp.ajustes';

    public static function all(): array
    {
        $defaults = config('sggp.ajustes');

        $guardados = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('configuraciones')) {
                return [];
            }

            return Configuracion::pluck('valor', 'clave')->all();
        });

        return array_merge($defaults, $guardados);
    }

    public static function get(string $clave, mixed $default = null): mixed
    {
        return self::all()[$clave] ?? $default;
    }

    public static function int(string $clave): int
    {
        return (int) self::get($clave, 0);
    }

    /** Catálogo guardado como texto de una opción por línea. */
    public static function catalogo(string $clave): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) self::get($clave, '')))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();
    }

    public static function set(array $valores): void
    {
        foreach ($valores as $clave => $valor) {
            Configuracion::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
