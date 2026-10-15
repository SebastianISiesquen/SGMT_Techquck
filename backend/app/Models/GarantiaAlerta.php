<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de cada aviso de vencimiento enviado (evita repetirlo). */
class GarantiaAlerta extends Model
{
    public $timestamps = false;

    protected $fillable = ['garantia_id', 'dias', 'destinatarios', 'enviada_en'];

    protected function casts(): array
    {
        return ['enviada_en' => 'datetime'];
    }

    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class);
    }
}
