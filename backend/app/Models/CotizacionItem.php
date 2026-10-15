<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['descripcion', 'cantidad', 'unidad', 'precio_unitario'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:2', 'precio_unitario' => 'decimal:2'];
    }

    public function importe(): float
    {
        return round((float) $this->cantidad * (float) $this->precio_unitario, 2);
    }
}
