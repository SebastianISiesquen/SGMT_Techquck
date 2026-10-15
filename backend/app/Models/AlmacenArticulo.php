<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Artículo del almacén: consumible (pasta térmica, conectores…) o repuesto (discos, fuentes, cámaras de reemplazo). */
class AlmacenArticulo extends Model
{
    use SoftDeletes;

    protected $table = 'almacen_articulos';

    public const TIPOS = [
        'consumible' => 'Consumible',
        'repuesto' => 'Equipo / repuesto',
    ];

    protected $fillable = [
        'codigo', 'tipo', 'nombre', 'categoria', 'marca', 'modelo', 'unidad', 'stock', 'stock_minimo', 'costo_unitario', 'ubicacion', 'activo',
    ];

    protected $attributes = ['activo' => true, 'stock' => 0, 'stock_minimo' => 0];

    protected function casts(): array
    {
        return ['stock' => 'float', 'stock_minimo' => 'float', 'costo_unitario' => 'float', 'activo' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Código correlativo según el tipo: CON-0001 / REP-0001
        static::created(function (AlmacenArticulo $a) {
            if (! $a->codigo) {
                $a->codigo = sprintf('%s-%04d', $a->tipo === 'consumible' ? 'CON' : 'REP', $a->id);
                $a->saveQuietly();
            }
        });
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(AlmacenMovimiento::class, 'articulo_id')->latest('fecha')->latest('id');
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function estaBajoMinimo(): bool
    {
        return $this->stock_minimo > 0 && $this->stock <= $this->stock_minimo;
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre.' '.collect([$this->marca, $this->modelo])->filter()->implode(' '));
    }

    /** Cantidad sin ceros sobrantes: 2, 1.5 */
    public static function cantidad(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    public function scopeBajoMinimo(Builder $q): Builder
    {
        return $q->where('stock_minimo', '>', 0)->whereColumn('stock', '<=', 'stock_minimo');
    }

    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['buscar'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('nombre', 'like', "%$v%")->orWhere('codigo', 'like', "%$v%")
                ->orWhere('marca', 'like', "%$v%")->orWhere('modelo', 'like', "%$v%")))
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($f['categoria'] ?? null, fn ($q, $v) => $q->where('categoria', $v))
            ->when(($f['stock'] ?? null) === 'bajo', fn ($q) => $q->bajoMinimo());
    }
}
