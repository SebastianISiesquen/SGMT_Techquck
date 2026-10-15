<?php

namespace App\Models;

use App\Support\Ajustes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cotización de un servicio sin cobertura (p. ej. diagnóstico de un equipo fuera de garantía). */
class Cotizacion extends Model
{
    use SoftDeletes;

    protected $table = 'cotizaciones';

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'enviada' => 'Enviada',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
    ];

    public const COLORES = ['borrador' => 'slate', 'enviada' => 'blue', 'aprobada' => 'green', 'rechazada' => 'red'];

    public const MONEDAS = ['PEN' => 'S/', 'USD' => 'US$'];

    protected $fillable = [
        'numero', 'entidad_id', 'proyecto_id', 'incidencia_id', 'equipo_id', 'fecha', 'validez_dias', 'moneda',
        'asunto', 'diagnostico', 'condiciones', 'subtotal', 'igv', 'total', 'estado', 'user_id',
    ];

    protected $attributes = [
        'estado' => 'borrador',
        'moneda' => 'PEN',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'subtotal' => 'decimal:2', 'igv' => 'decimal:2', 'total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::created(function (Cotizacion $c) {
            if (! $c->numero) {
                $c->numero = sprintf('COT-%s-%05d', $c->fecha->year, $c->id);
                $c->saveQuietly();
            }
        });
    }

    public function entidad(): BelongsTo
    {
        return $this->belongsTo(Entidad::class)->withTrashed();
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class)->withTrashed();
    }

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class)->withTrashed();
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class)->withTrashed();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CotizacionItem::class)->orderBy('id');
    }

    /** Recalcula subtotal, IGV y total a partir de los ítems. */
    public function recalcular(): void
    {
        $subtotal = round($this->items()->get()->sum(fn ($i) => $i->importe()), 2);
        $igv = round($subtotal * Ajustes::int('igv') / 100, 2);

        $this->update(['subtotal' => $subtotal, 'igv' => $igv, 'total' => $subtotal + $igv]);
    }

    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['buscar'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('numero', 'like', "%$v%")->orWhere('asunto', 'like', "%$v%")))
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->where('entidad_id', $v))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v));
    }

    public function simbolo(): string
    {
        return self::MONEDAS[$this->moneda] ?? $this->moneda;
    }

    public function monto(float|string|null $valor): string
    {
        return $this->simbolo().' '.number_format((float) $valor, 2);
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function estadoColor(): string
    {
        return self::COLORES[$this->estado] ?? 'slate';
    }

    public function venceEl(): \Carbon\CarbonInterface
    {
        return $this->fecha->copy()->addDays($this->validez_dias);
    }
}
