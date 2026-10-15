<?php

namespace App\Models\Concerns;

/**
 * Asigna un código legible (ej. INC-2026-00012) después de crear el registro,
 * basado en el id para que nunca se repita.
 */
trait GeneraCodigo
{
    protected static function bootGeneraCodigo(): void
    {
        static::created(function ($model) {
            if (empty($model->codigo)) {
                $model->codigo = sprintf('%s-%s-%05d', static::PREFIJO_CODIGO, now()->year, $model->id);
                $model->saveQuietly();
            }
        });
    }
}
