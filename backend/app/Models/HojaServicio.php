<?php

namespace App\Models;

use App\Enums\EstadoHoja;
use App\Enums\ResultadoAtencion;
use App\Enums\TipoServicio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Hoja de servicio: el registro de cada visita del técnico. La conformidad del cliente
 * es el acta firmada (foto o PDF) que se sube después; no hay firmas digitales.
 */
class HojaServicio extends Model
{
    protected $table = 'hojas_servicio';

    protected $fillable = [
        'numero', 'proyecto_id', 'garantia_id', 'incidencia_id', 'mantenimiento_id', 'atencion_id', 'tecnico_id', 'diagnostico', 'resultado', 'pendientes', 'proxima_visita', 'tipo_servicio', 'fecha',
        'hora_llegada', 'hora_salida', 'verificaciones', 'trabajo_realizado', 'observaciones', 'recomendaciones', 'mediciones', 'estado', 'conforme',
        'receptor_nombre', 'receptor_dni', 'receptor_cargo', 'firma_receptor', 'firma_tecnico', 'fecha_firma',
        'latitud', 'longitud', 'origen', 'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'tipo_servicio' => TipoServicio::class,
            'estado' => EstadoHoja::class,
            'fecha' => 'date',
            'proxima_visita' => 'date',
            'verificaciones' => 'array',
            'mediciones' => 'array',
            'fecha_firma' => 'datetime',
            'conforme' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Correlativo por año: HS-2026-00001
        static::created(function (HojaServicio $hoja) {
            if (! $hoja->numero) {
                $hoja->numero = sprintf('HS-%s-%05d', $hoja->fecha->year, $hoja->id);
                $hoja->saveQuietly();
            }
        });
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class)->withTrashed();
    }

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class)->withTrashed();
    }

    public function incidencia(): BelongsTo
    {
        return $this->belongsTo(Incidencia::class)->withTrashed();
    }

    /** Atención técnica que esta hoja registró en la incidencia. */
    public function atencion(): BelongsTo
    {
        return $this->belongsTo(Atencion::class);
    }

    public function resultadoLabel(): ?string
    {
        return $this->resultado ? ResultadoAtencion::tryFrom($this->resultado)?->label() : null;
    }

    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(Mantenimiento::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(HojaServicioEquipo::class);
    }

    public function materiales(): HasMany
    {
        return $this->hasMany(HojaServicioMaterial::class);
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->latest();
    }

    /** Ya tiene el acta de conformidad firmada por el cliente. */
    public function tieneActa(): bool
    {
        return $this->estado === EstadoHoja::Conforme;
    }

    /** Se puede corregir mientras no tenga el acta ni esté anulada. */
    public function esEditable(): bool
    {
        return $this->estado === EstadoHoja::Registrada;
    }

    public function actas()
    {
        return $this->archivos->filter(fn ($a) => $a->categoria->value === 'acta_conformidad');
    }

    /** El técnico dejó el trabajo pendiente y debe volver. */
    public function requiereVisita(): bool
    {
        return (bool) ($this->resultado && ResultadoAtencion::tryFrom($this->resultado)?->requiereVisita());
    }

    /** Duración del servicio en campo, ej. "2 h 15 min". */
    public function duracion(): ?string
    {
        if (! $this->hora_salida) {
            return null;
        }
        $min = (strtotime($this->hora_salida) - strtotime($this->hora_llegada)) / 60;
        if ($min <= 0) {
            return null;
        }

        return intdiv((int) $min, 60).' h '.((int) $min % 60).' min';
    }

    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['buscar'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q
                ->where('numero', 'like', "%$v%")->orWhere('trabajo_realizado', 'like', "%$v%")->orWhere('receptor_nombre', 'like', "%$v%")))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($f['tipo_servicio'] ?? null, fn ($q, $v) => $q->where('tipo_servicio', $v))
            ->when($f['tecnico_id'] ?? null, fn ($q, $v) => $q->where('tecnico_id', $v))
            ->when($f['proyecto_id'] ?? null, fn ($q, $v) => $q->where('proyecto_id', $v))
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->whereHas('proyecto', fn ($q) => $q->where('entidad_id', $v)))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha', '<=', $v));
    }
}
