<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kardex del almacén: entradas (compras), salidas (uso en servicios) y ajustes de inventario. */
class AlmacenMovimiento extends Model
{
    protected $table = 'almacen_movimientos';

    public $timestamps = false;

    public const TIPOS = [
        'entrada' => 'Entrada',
        'salida' => 'Salida',
        'ajuste' => 'Ajuste de inventario',
    ];

    protected $fillable = [
        'articulo_id', 'tipo', 'cantidad', 'stock_resultante', 'costo_unitario', 'motivo', 'documento', 'hoja_servicio_id', 'proyecto_id', 'user_id', 'fecha',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'float', 'stock_resultante' => 'float', 'costo_unitario' => 'float', 'fecha' => 'datetime'];
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(AlmacenArticulo::class, 'articulo_id')->withTrashed();
    }

    public function hojaServicio(): BelongsTo
    {
        return $this->belongsTo(HojaServicio::class);
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /** Variación real del stock: las salidas restan; un ajuste lleva su signo. */
    public function variacion(): float
    {
        return $this->tipo === 'salida' ? -$this->cantidad : $this->cantidad;
    }

    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($f['tecnico_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->whereHas('proyecto', fn ($q) => $q->where('entidad_id', $v)->orWhere('cliente_final_id', $v)))
            ->when($f['categoria'] ?? null, fn ($q, $v) => $q->whereHas('articulo', fn ($q) => $q->where('categoria', $v)))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v));
    }
}
