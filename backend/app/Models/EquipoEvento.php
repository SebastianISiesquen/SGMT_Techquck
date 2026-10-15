<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrada de la hoja de vida de un equipo. */
class EquipoEvento extends Model
{
    /** tipo => [etiqueta, color, icono] */
    public const TIPOS = [
        'registro' => ['Registro en inventario', 'slate', 'plus'],
        'instalacion' => ['Instalación', 'blue', 'download'],
        'mantenimiento' => ['Mantenimiento preventivo', 'green', 'wrench'],
        'falla' => ['Falla reportada', 'red', 'alert'],
        'diagnostico' => ['Diagnóstico', 'amber', 'search'],
        'correctivo' => ['Mantenimiento correctivo', 'blue', 'wrench'],
        'cambio_componente' => ['Cambio de componente', 'violet', 'refresh'],
        'inspeccion' => ['Inspección', 'slate', 'eye'],
        'entrega' => ['Entrega / cambio de responsable', 'slate', 'users'],
        'traslado' => ['Traslado', 'slate', 'map-pin'],
        'baja' => ['Baja', 'slate', 'trash'],
        'observacion' => ['Observación', 'slate', 'file'],
    ];

    /** Tipos que el usuario puede registrar a mano (los demás los genera el sistema). */
    public const MANUALES = ['diagnostico', 'cambio_componente', 'mantenimiento', 'entrega', 'traslado', 'baja', 'observacion'];

    protected $fillable = [
        'equipo_id', 'tipo', 'fecha', 'titulo', 'detalle', 'componente', 'estado_equipo',
        'user_id', 'incidencia_id', 'hoja_servicio_id',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class)->withTrashed();
    }

    public function hojaServicio(): BelongsTo
    {
        return $this->belongsTo(HojaServicio::class);
    }

    public function tipoLabel(): string
    {
        return self::TIPOS[$this->tipo][0] ?? $this->tipo;
    }

    public function tipoColor(): string
    {
        return self::TIPOS[$this->tipo][1] ?? 'slate';
    }

    public function tipoIcono(): string
    {
        return self::TIPOS[$this->tipo][2] ?? 'file';
    }

    public static function opcionesManuales(): array
    {
        return collect(self::MANUALES)->mapWithKeys(fn ($t) => [$t => self::TIPOS[$t][0]])->all();
    }
}
