<?php

namespace App\Models;

use App\Enums\ResultadoAtencion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Atencion extends Model
{
    protected $table = 'atenciones';

    protected $fillable = [
        'incidencia_id', 'tecnico_id', 'fecha_inicio', 'fecha_fin', 'diagnostico', 'actividades',
        'observaciones', 'resultado', 'latitud', 'longitud', 'origen',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'resultado' => ResultadoAtencion::class,
        ];
    }

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->latest();
    }
}
