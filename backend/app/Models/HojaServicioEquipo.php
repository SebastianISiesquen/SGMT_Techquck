<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HojaServicioEquipo extends Model
{
    public $timestamps = false;

    public const ACCIONES = [
        'revisado' => 'Revisado',
        'configurado' => 'Configurado',
        'reparado' => 'Reparado',
        'cambio_componente' => 'Cambio de componente',
        'reemplazado' => 'Reemplazado (equipo nuevo)',
        'instalado' => 'Instalado',
        'retirado' => 'Retirado',
    ];

    public const ESTADOS = [
        'operativo' => 'Operativo',
        'observado' => 'Operativo con observaciones',
        'inoperativo' => 'Inoperativo',
    ];

    protected $fillable = ['equipo_id', 'checklist', 'equipo', 'marca', 'modelo', 'serie', 'ubicacion', 'accion', 'estado_final', 'observacion'];

    protected function casts(): array
    {
        return ['checklist' => 'array'];
    }

    /** Puntos del checklist con resultado «observado» o «falla». */
    public function hallazgos(): array
    {
        return collect($this->checklist ?? [])->filter(fn ($p) => in_array($p['resultado'] ?? '', ['observado', 'falla']))->values()->all();
    }

    /** Equipo del inventario del cliente al que corresponde esta fila (si se identificó). */
    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Equipo::class, 'equipo_id')->withTrashed();
    }

    public function accionLabel(): string
    {
        return self::ACCIONES[$this->accion] ?? $this->accion;
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado_final] ?? $this->estado_final;
    }
}
