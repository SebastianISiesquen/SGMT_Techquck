<?php

namespace App\Models;

use App\Enums\EstadoGarantia;
use App\Models\Concerns\GeneraCodigo;
use App\Support\Ajustes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Garantia extends Model
{
    use GeneraCodigo, SoftDeletes;

    public const PREFIJO_CODIGO = 'GAR';

    protected $fillable = [
        'codigo', 'proyecto_id', 'tipo', 'descripcion', 'cobertura', 'condiciones', 'proveedor',
        'numero_documento', 'orden_compra', 'monto', 'fecha_inicio', 'duracion_meses', 'fecha_vencimiento',
        'observaciones', 'anulada', 'notificar_cliente',
    ];

    /** Duraciones frecuentes (meses) que el formulario ofrece como atajo. */
    public const DURACIONES = [3 => '3 meses', 6 => '6 meses', 12 => '1 año', 24 => '2 años', 36 => '3 años', 60 => '5 años'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_vencimiento' => 'date',
            'monto' => 'decimal:2',
            'anulada' => 'boolean',
            'notificar_cliente' => 'boolean',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class)->withTrashed();
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class);
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(GarantiaAlerta::class)->orderByDesc('enviada_en');
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->latest();
    }

    /** Estado calculado automáticamente según las fechas de vigencia (RF06). */
    public function getEstadoAttribute(): EstadoGarantia
    {
        if ($this->anulada) {
            return EstadoGarantia::Anulada;
        }

        $hoy = today();

        if ($this->fecha_vencimiento->lt($hoy)) {
            return EstadoGarantia::Vencida;
        }

        if ($this->fecha_vencimiento->lte($hoy->copy()->addDays(Ajustes::int('dias_alerta_garantia')))) {
            return EstadoGarantia::PorVencer;
        }

        return EstadoGarantia::Vigente;
    }

    public function diasRestantes(): int
    {
        return (int) today()->diffInDays($this->fecha_vencimiento, false);
    }

    /** Porcentaje transcurrido del periodo de garantía (0–100). */
    public function avance(): int
    {
        $total = max(1, $this->fecha_inicio->diffInDays($this->fecha_vencimiento));
        $transcurrido = $this->fecha_inicio->diffInDays(today(), false);

        return (int) max(0, min(100, round($transcurrido / $total * 100)));
    }

    public function scopeVigentes(Builder $q): Builder
    {
        return $q->where('anulada', false)
            ->whereDate('fecha_vencimiento', '>', today()->addDays(Ajustes::int('dias_alerta_garantia')));
    }

    public function scopePorVencer(Builder $q): Builder
    {
        return $q->where('anulada', false)
            ->whereDate('fecha_vencimiento', '>=', today())
            ->whereDate('fecha_vencimiento', '<=', today()->addDays(Ajustes::int('dias_alerta_garantia')));
    }

    public function scopeVencidas(Builder $q): Builder
    {
        return $q->where('anulada', false)->whereDate('fecha_vencimiento', '<', today());
    }

    /** Garantías que todavía cubren incidencias (vigentes o por vencer). */
    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('anulada', false)->whereDate('fecha_vencimiento', '>=', today());
    }

    public function scopeEstado(Builder $q, ?string $estado): Builder
    {
        return match ($estado) {
            'vigente' => $q->vigentes(),
            'por_vencer' => $q->porVencer(),
            'vencida' => $q->vencidas(),
            'anulada' => $q->where('anulada', true),
            default => $q,
        };
    }

    /** «5 años», «3 meses» o el periodo calculado entre las fechas. */
    public function duracionTexto(): string
    {
        $meses = $this->duracion_meses ?: (int) round($this->fecha_inicio->diffInMonths($this->fecha_vencimiento));

        return $meses % 12 === 0
            ? ($meses / 12).' año'.($meses === 12 ? '' : 's')
            : $meses.' mes'.($meses === 1 ? '' : 'es');
    }

    public function etiqueta(): string
    {
        return "{$this->codigo} · {$this->tipo}";
    }
}
