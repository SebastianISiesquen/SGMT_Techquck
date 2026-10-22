<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Equipo instalado o atendido en un cliente (cámara, laptop, servidor…).
 * El código interno (EQ-000001) sirve para el sticker con QR cuando la placa de serie ya no se lee.
 */
class Equipo extends Model
{
    use SoftDeletes;

    public const ESTADOS = [
        'operativo' => 'Operativo',
        'observado' => 'Operativo con observaciones',
        'con_falla' => 'Con falla',
        'en_reparacion' => 'En reparación',
        'inoperativo' => 'Inoperativo',
        'de_baja' => 'De baja',
    ];

    public const COLORES = [
        'operativo' => 'green',
        'observado' => 'amber',
        'con_falla' => 'red',
        'en_reparacion' => 'blue',
        'inoperativo' => 'red',
        'de_baja' => 'slate',
    ];

    protected $fillable = [
        'codigo', 'entidad_id', 'proyecto_id', 'garantia_id', 'categoria', 'marca', 'modelo', 'serie', 'descripcion',
        'ubicacion', 'area', 'responsable', 'fecha_instalacion', 'estado', 'observaciones',
    ];

    protected $attributes = [
        'estado' => 'operativo',
    ];

    protected function casts(): array
    {
        return ['fecha_instalacion' => 'date'];
    }

    protected static function booted(): void
    {
        // Enlace público del QR: aleatorio, para que no se puedan adivinar los demás equipos
        static::creating(function (Equipo $equipo) {
            $equipo->token_publico ??= \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(20));
        });

        static::created(function (Equipo $equipo) {
            if (! $equipo->codigo) {
                $equipo->codigo = sprintf('EQ-%06d', $equipo->id);
                $equipo->saveQuietly();
            }
        });
    }

    /** Cliente propietario del equipo (o cliente final, si el proyecto es un subcontrato). */
    public function entidad(): BelongsTo
    {
        return $this->belongsTo(Entidad::class)->withTrashed();
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class)->withTrashed();
    }

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class)->withTrashed();
    }

    /** Hoja de vida: todo lo que le ocurrió al equipo, del más reciente al más antiguo. */
    public function eventos(): HasMany
    {
        return $this->hasMany(EquipoEvento::class)->orderByDesc('fecha')->orderByDesc('id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class);
    }

    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'archivable')->latest();
    }

    /** FEI: la foto más reciente del equipo ya instalado en su lugar. */
    public function fotoInstalacion(): MorphOne
    {
        return $this->morphOne(Archivo::class, 'archivable')
            ->ofMany(['id' => 'max'], fn ($q) => $q->where('categoria', 'foto_instalacion'));
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function scopeBuscar(Builder $q, ?string $texto): Builder
    {
        return $q->when($texto, fn ($q, $v) => $q->where(fn ($q) => $q
            ->where('codigo', 'like', "%$v%")->orWhere('serie', 'like', "%$v%")
            ->orWhere('marca', 'like', "%$v%")->orWhere('modelo', 'like', "%$v%")
            ->orWhere('descripcion', 'like', "%$v%")->orWhere('responsable', 'like', "%$v%")
            ->orWhere('ubicacion', 'like', "%$v%")->orWhere('area', 'like', "%$v%")));
    }

    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q->buscar($f['buscar'] ?? null)
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->where('entidad_id', $v))
            ->when($f['proyecto_id'] ?? null, fn ($q, $v) => $q->where('proyecto_id', $v))
            ->when($f['categoria'] ?? null, fn ($q, $v) => $q->where('categoria', $v))
            ->when($f['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v));
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function estadoColor(): string
    {
        return self::COLORES[$this->estado] ?? 'slate';
    }

    /** «Laptop Lenovo ThinkPad E14 · S/N PF3XK21». */
    public function nombre(): string
    {
        $base = trim($this->categoria.' '.$this->marca.' '.$this->modelo);

        return $base.($this->serie ? " · S/N {$this->serie}" : '');
    }

    /** Garantía que cubre al equipo: la propia o, si no tiene, la vigente de su proyecto. */
    public function garantiaVigente(): ?Garantia
    {
        if ($this->garantia && $this->garantia->fecha_vencimiento->gte(today()) && ! $this->garantia->anulada) {
            return $this->garantia;
        }

        return $this->proyecto?->garantias()->activas()->orderByDesc('fecha_vencimiento')->first();
    }

    /** Texto que codifica el QR del sticker; la app lo reconoce al escanearlo. */
    /**
     * Lo que codifica el QR del sticker: la ficha pública del equipo, que cualquiera puede abrir
     * con la cámara del celular. Usa la dirección pública configurada (no «localhost»).
     */
    public function contenidoQr(): string
    {
        if (! $this->token_publico) {
            $this->forceFill(['token_publico' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(20))])->saveQuietly();
        }

        return self::urlPublica()."/e/{$this->token_publico}";
    }

    public static function urlPublica(): string
    {
        return rtrim(\App\Support\Ajustes::get('url_publica') ?: config('app.url'), '/');
    }
}
