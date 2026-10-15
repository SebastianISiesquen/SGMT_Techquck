<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Plan de mantenimiento preventivo: N visitas cada X meses (p. ej. 4 al año, cada 3 meses). */
class PlanMantenimiento extends Model
{
    protected $table = 'planes_mantenimiento';

    protected $fillable = [
        'proyecto_id', 'garantia_id', 'nombre', 'frecuencia_meses', 'total_visitas', 'fecha_inicio', 'tecnico_id', 'alcance', 'activo',
    ];

    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'activo' => 'boolean'];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class)->withTrashed();
    }

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class)->withTrashed();
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function mantenimientos(): HasMany
    {
        return $this->hasMany(Mantenimiento::class, 'plan_id')->orderBy('numero');
    }

    /**
     * Genera las visitas que aún no existen. Con $recalcular, las visitas pendientes
     * vuelven a tomar la fecha según el inicio y la frecuencia (las realizadas no cambian).
     */
    public function programarVisitas(bool $recalcular = false): void
    {
        $existentes = $this->mantenimientos()->get()->keyBy('numero');

        for ($n = 1; $n <= $this->total_visitas; $n++) {
            $fecha = $this->fecha_inicio->copy()->addMonthsNoOverflow(($n - 1) * $this->frecuencia_meses);
            $visita = $existentes->get($n);

            if (! $visita) {
                $this->mantenimientos()->create(['numero' => $n, 'fecha_programada' => $fecha, 'tecnico_id' => $this->tecnico_id]);
            } elseif ($recalcular && $visita->estado === 'programado') {
                $visita->update(['fecha_programada' => $fecha, 'tecnico_id' => $visita->tecnico_id ?? $this->tecnico_id, 'alerta_enviada' => false]);
            }
        }

        // Si se redujo el número de visitas, se quitan las sobrantes que aún no se realizaron.
        $this->mantenimientos()->where('numero', '>', $this->total_visitas)->where('estado', 'programado')->delete();
    }

    public function realizadas(): int
    {
        return $this->mantenimientos->where('estado', 'realizado')->count();
    }
}
