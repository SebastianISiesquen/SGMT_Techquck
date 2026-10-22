<?php

namespace App\Models;

use App\Enums\EstadoIncidencia;
use App\Enums\Prioridad;
use App\Models\Concerns\GeneraCodigo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incidencia extends Model
{
    use GeneraCodigo, SoftDeletes;

    public const PREFIJO_CODIGO = 'INC';

    /** Qué respalda la atención: una garantía, un contrato de servicio o nada (se cotiza). */
    public const COBERTURAS = [
        'garantia' => 'Cubierta por garantía',
        'contrato' => 'Contrato de mantenimiento / soporte',
        'sin_cobertura' => 'Sin cobertura (diagnóstico y cotización)',
    ];

    protected $fillable = [
        'codigo', 'proyecto_id', 'garantia_id', 'equipo_id', 'cobertura', 'titulo', 'descripcion', 'prioridad', 'estado',
        'canal', 'reportado_por', 'ubicacion_detalle', 'fecha_reporte', 'fecha_limite', 'proxima_visita', 'motivo_reprogramacion',
        'tecnico_id', 'asignado_por', 'fecha_asignacion', 'fecha_resolucion', 'fecha_cierre',
        'cerrado_por', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoIncidencia::class,
            'prioridad' => Prioridad::class,
            'fecha_reporte' => 'datetime',
            'fecha_limite' => 'datetime',
            'proxima_visita' => 'date',
            'fecha_asignacion' => 'datetime',
            'fecha_resolucion' => 'datetime',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class)->withTrashed();
    }

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class)->withTrashed();
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class)->withTrashed();
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function coberturaLabel(): string
    {
        return self::COBERTURAS[$this->cobertura] ?? $this->cobertura;
    }

    public function hojasServicio(): HasMany
    {
        return $this->hasMany(HojaServicio::class)->latest('id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function asignador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function atenciones(): HasMany
    {
        return $this->hasMany(Atencion::class)->latest('fecha_inicio');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(IncidenciaHistorial::class)->latest('id');
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->latest();
    }

    /** Evidencias de la incidencia y de todas sus atenciones. */
    public function todasLasEvidencias(): Collection
    {
        return Archivo::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('archivable_type', $this->getMorphClass())->where('archivable_id', $this->id))
                ->orWhere(fn ($q) => $q->where('archivable_type', (new Atencion)->getMorphClass())
                    ->whereIn('archivable_id', Atencion::where('incidencia_id', $this->id)->select('id')))
                // Las fotos de las hojas de servicio de la incidencia también son evidencias
                ->orWhere(fn ($q) => $q->where('archivable_type', (new HojaServicio)->getMorphClass())
                    ->whereIn('archivable_id', HojaServicio::where('incidencia_id', $this->id)->where('estado', '!=', 'anulada')->select('id'))))
            ->latest()
            ->get();
    }

    /** Acta de conformidad firmada adjunta a la incidencia, a una atención o a una de sus hojas de servicio. */
    public function tieneActaConformidad(): bool
    {
        return $this->hojasServicio()->where('estado', 'conforme')->exists()
            || $this->todasLasEvidencias()->contains(fn ($a) => $a->categoria->value === 'acta_conformidad');
    }

    public function estaAbierta(): bool
    {
        return in_array($this->estado->value, EstadoIncidencia::abiertos());
    }

    public function estaVencidaSla(): bool
    {
        return $this->fecha_limite && $this->estaAbierta() && $this->fecha_limite->isPast();
    }

    public function scopeAbiertas(Builder $q): Builder
    {
        return $q->whereIn($q->qualifyColumn('estado'), EstadoIncidencia::abiertos());
    }

    /** Filtro común de listados, API y reportes (RF13). */
    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['buscar'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q
                ->where('codigo', 'like', "%$v%")
                ->orWhere('titulo', 'like', "%$v%")
                ->orWhere('descripcion', 'like', "%$v%")))
            ->when($f['estado'] ?? null, fn ($q, $v) => $v === 'abiertas' ? $q->abiertas() : $q->where('estado', $v))
            ->when($f['prioridad'] ?? null, fn ($q, $v) => $q->where('prioridad', $v))
            ->when($f['tecnico_id'] ?? null, fn ($q, $v) => $q->where('tecnico_id', $v))
            ->when($f['proyecto_id'] ?? null, fn ($q, $v) => $q->where('proyecto_id', $v))
            ->when($f['equipo_id'] ?? null, fn ($q, $v) => $q->where('equipo_id', $v))
            ->when($f['cobertura'] ?? null, fn ($q, $v) => $q->where('cobertura', $v))
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->whereHas('proyecto', fn ($q) => $q->where('entidad_id', $v)->orWhere('cliente_final_id', $v)))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_reporte', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_reporte', '<=', $v));
    }
}
