<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente de la empresa: entidad del Estado o empresa privada.
 * (La tabla conserva el nombre «entidades» por compatibilidad.)
 */
class Entidad extends Model
{
    use SoftDeletes;

    protected $table = 'entidades';

    public const NATURALEZAS = [
        'publico' => 'Público',
        'privado' => 'Privado',
    ];

    protected $fillable = [
        'nombre', 'ruc', 'naturaleza', 'sector', 'direccion', 'departamento',
        'contacto_nombre', 'contacto_cargo', 'contacto_telefono', 'contacto_email', 'correos_alertas', 'activo',
    ];

    protected $attributes = [
        'naturaleza' => 'publico',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class);
    }

    /** Proyectos de otros contratistas en los que este cliente es el cliente final. */
    public function proyectosComoClienteFinal(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'cliente_final_id');
    }

    public function garantias(): HasManyThrough
    {
        return $this->hasManyThrough(Garantia::class, Proyecto::class);
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    public function naturalezaLabel(): string
    {
        return self::NATURALEZAS[$this->naturaleza] ?? $this->naturaleza;
    }

    /** Correos que reciben los avisos de vencimiento: el del contacto y los adicionales. */
    public function correosAlerta(): array
    {
        return collect([$this->contacto_email, ...preg_split('/[\s,;]+/', (string) $this->correos_alertas)])
            ->map(fn ($c) => strtolower(trim((string) $c)))
            ->filter(fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
