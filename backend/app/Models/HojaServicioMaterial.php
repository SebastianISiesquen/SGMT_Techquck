<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HojaServicioMaterial extends Model
{
    protected $table = 'hoja_servicio_materiales';

    public $timestamps = false;

    protected $fillable = ['articulo_id', 'descripcion', 'cantidad', 'unidad'];

    protected function casts(): array
    {
        return ['cantidad' => 'float'];
    }

    /** Artículo del almacén del que salió (si se tomó del almacén). */
    public function articulo(): BelongsTo
    {
        return $this->belongsTo(AlmacenArticulo::class, 'articulo_id')->withTrashed();
    }
}
