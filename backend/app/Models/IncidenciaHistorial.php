<?php

namespace App\Models;

use App\Enums\EstadoIncidencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidenciaHistorial extends Model
{
    protected $table = 'incidencia_historial';

    public const UPDATED_AT = null;

    public const ACCIONES = [
        'registro' => 'Registro',
        'edicion' => 'Edición',
        'asignacion' => 'Asignación',
        'cambio_estado' => 'Cambio de estado',
        'atencion' => 'Atención técnica',
        'evidencia' => 'Evidencia adjunta',
        'cierre' => 'Cierre',
        'reapertura' => 'Reapertura',
        'anulacion' => 'Anulación',
        'hoja_servicio' => 'Hoja de servicio',
        'reprogramacion' => 'Próxima visita',
    ];

    protected $fillable = ['incidencia_id', 'user_id', 'accion', 'estado_anterior', 'estado_nuevo', 'detalle'];

    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoIncidencia::class,
            'estado_nuevo' => EstadoIncidencia::class,
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function accionLabel(): string
    {
        return self::ACCIONES[$this->accion] ?? $this->accion;
    }
}
