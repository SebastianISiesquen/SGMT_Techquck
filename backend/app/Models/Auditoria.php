<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'accion', 'modulo', 'auditable_type', 'auditable_id', 'descripcion', 'datos', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['datos' => 'array', 'created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
