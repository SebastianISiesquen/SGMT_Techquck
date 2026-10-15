<?php

namespace App\Models;

use App\Enums\CategoriaArchivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Archivo extends Model
{
    protected $fillable = [
        'archivable_type', 'archivable_id', 'categoria', 'nombre_original', 'ruta', 'mime',
        'tamano', 'descripcion', 'subido_por',
    ];

    protected function casts(): array
    {
        return ['categoria' => CategoriaArchivo::class];
    }

    public function archivable(): MorphTo
    {
        return $this->morphTo();
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function esImagen(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function esPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->nombre_original, PATHINFO_EXTENSION));
    }

    public function tamanoLegible(): string
    {
        // Sin depender de la extensión intl (no viene activa en XAMPP).
        $unidades = ['B', 'KB', 'MB', 'GB'];
        $tamano = (float) $this->tamano;
        $i = 0;
        while ($tamano >= 1024 && $i < count($unidades) - 1) {
            $tamano /= 1024;
            $i++;
        }

        return round($tamano, 1).' '.$unidades[$i];
    }

    /** La incidencia a la que pertenece (directamente o vía una atención). */
    public function incidencia(): ?Incidencia
    {
        return match (true) {
            $this->archivable instanceof Incidencia => $this->archivable,
            $this->archivable instanceof Atencion => $this->archivable->incidencia,
            $this->archivable instanceof HojaServicio => $this->archivable->incidencia,
            default => null,
        };
    }
}
