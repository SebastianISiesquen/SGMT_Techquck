<?php

namespace App\Models;

use App\Models\Concerns\GeneraCodigo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Proyecto de implementación (cámaras, servidores, cableado…) o servicio
 * (mantenimiento, soporte) contratado por un cliente. Si es un subcontrato,
 * el cliente es quien contrata a la empresa y el cliente final es quien recibe el servicio.
 */
class Proyecto extends Model
{
    use GeneraCodigo, SoftDeletes;

    public const PREFIJO_CODIGO = 'PRY';

    public const CLASES = [
        'implementacion' => 'Proyecto de implementación',
        'servicio' => 'Contrato de servicio',
    ];

    public const ESTADOS = [
        'operativo' => 'Operativo',
        'suspendido' => 'Suspendido',
        'finalizado' => 'Finalizado',
    ];

    protected $fillable = [
        'codigo', 'nombre', 'clase', 'entidad_id', 'cliente_final_id', 'tipo', 'descripcion', 'numero_contrato', 'orden_compra',
        'monto_contrato', 'ubicacion', 'fecha_inicio', 'fecha_entrega', 'fecha_fin', 'responsable_id', 'estado', 'informe_mensual',
    ];

    protected $attributes = [
        'clase' => 'implementacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_entrega' => 'date',
            'fecha_fin' => 'date',
            'monto_contrato' => 'decimal:2',
            'informe_mensual' => 'boolean',
        ];
    }

    /** Cliente que contrata a la empresa (a quien se dirigen informes y cartas). */
    public function entidad(): BelongsTo
    {
        return $this->belongsTo(Entidad::class)->withTrashed();
    }

    /** Cliente final que recibe el servicio cuando el proyecto es un subcontrato. */
    public function clienteFinal(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'cliente_final_id')->withTrashed();
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function garantias(): HasMany
    {
        return $this->hasMany(Garantia::class);
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class);
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(Equipo::class);
    }

    public function planesMantenimiento(): HasMany
    {
        return $this->hasMany(PlanMantenimiento::class);
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->latest();
    }

    /** Proyectos que siguen en curso (los finalizados no se muestran por defecto). */
    public function scopeEnCurso(Builder $q): Builder
    {
        return $q->where('estado', '!=', 'finalizado');
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function claseLabel(): string
    {
        return self::CLASES[$this->clase] ?? $this->clase;
    }

    public function esServicio(): bool
    {
        return $this->clase === 'servicio';
    }

    /** «Cliente» o «Cliente → Cliente final» para fichas y reportes. */
    public function clientes(): string
    {
        return $this->entidad->nombre.($this->clienteFinal ? ' → '.$this->clienteFinal->nombre : '');
    }

    /** Quien recibe físicamente el servicio: el cliente final, o el cliente si es directo. */
    public function lugarDeServicio(): Entidad
    {
        return $this->clienteFinal ?? $this->entidad;
    }
}
