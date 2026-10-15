<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Visita programada de un plan de mantenimiento («Mantenimiento 2 de 4»). */
class Mantenimiento extends Model
{
    public const ESTADOS = [
        'programado' => 'Programado',
        'realizado' => 'Realizado',
        'cancelado' => 'Cancelado',
    ];

    protected $fillable = [
        'plan_id', 'numero', 'fecha_programada', 'estado', 'tecnico_id', 'hoja_servicio_id', 'fecha_ejecucion', 'observacion', 'alerta_enviada',
    ];

    protected $attributes = [
        'estado' => 'programado',
    ];

    protected function casts(): array
    {
        return ['fecha_programada' => 'date', 'fecha_ejecucion' => 'date', 'alerta_enviada' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanMantenimiento::class, 'plan_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function hojaServicio(): BelongsTo
    {
        return $this->belongsTo(HojaServicio::class);
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('estado', 'programado');
    }

    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['estado'] ?? null, fn ($q, $v) => match ($v) {
                'vencido' => $q->pendientes()->whereDate('fecha_programada', '<', today()),
                'proximo' => $q->pendientes()->whereBetween('fecha_programada', [today(), today()->addDays(30)]),
                default => $q->where('estado', $v),
            })
            ->when($f['tecnico_id'] ?? null, fn ($q, $v) => $q->where('tecnico_id', $v))
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->whereHas('plan.proyecto', fn ($q) => $q->where('entidad_id', $v)))
            ->when($f['proyecto_id'] ?? null, fn ($q, $v) => $q->whereHas('plan', fn ($q) => $q->where('proyecto_id', $v)))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_programada', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_programada', '<=', $v));
    }

    /** «Mantenimiento 2 de 4». */
    public function etiqueta(): string
    {
        return "Mantenimiento {$this->numero} de {$this->plan->total_visitas}";
    }

    public function vencido(): bool
    {
        return $this->estado === 'programado' && $this->fecha_programada->lt(today());
    }

    public function estadoLabel(): string
    {
        return $this->vencido() ? 'Atrasado' : (self::ESTADOS[$this->estado] ?? $this->estado);
    }

    public function estadoColor(): string
    {
        if ($this->vencido()) {
            return 'red';
        }

        return match ($this->estado) {
            'realizado' => 'green',
            'cancelado' => 'slate',
            default => $this->fecha_programada->lte(today()->addDays(7)) ? 'amber' : 'blue',
        };
    }

    public function diasRestantes(): int
    {
        return (int) today()->diffInDays($this->fecha_programada, false);
    }
}
