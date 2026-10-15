<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Puntos de control del mantenimiento según la categoría del equipo: todos los técnicos revisan lo mismo. */
class Checklist extends Model
{
    protected $fillable = ['categoria', 'items'];

    public const RESULTADOS = [
        'ok' => 'OK',
        'observado' => 'Observado',
        'falla' => 'Falla',
        'na' => 'No aplica',
    ];

    public function lista(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->items))->map(fn ($l) => trim($l))->filter()->values()->all();
    }

    /** { "laptop": ["Limpieza interna", …] }: la categoría va normalizada para encontrarla sin importar tildes ni mayúsculas. */
    public static function mapa(): array
    {
        return self::all()->mapWithKeys(fn (Checklist $c) => [self::clave($c->categoria) => $c->lista()])->all();
    }

    public static function para(?string $categoria): array
    {
        return $categoria ? (self::mapa()[self::clave($categoria)] ?? []) : [];
    }

    public static function clave(string $categoria): string
    {
        return Str::lower(Str::ascii(trim($categoria)));
    }
}
