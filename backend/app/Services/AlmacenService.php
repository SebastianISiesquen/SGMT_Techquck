<?php

namespace App\Services;

use App\Models\AlmacenArticulo;
use App\Models\AlmacenMovimiento;
use App\Models\HojaServicio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Movimientos del almacén. El stock solo cambia a través de aquí, para que el kardex
 * y el stock del artículo siempre coincidan.
 */
class AlmacenService
{
    /**
     * @param  string  $tipo  entrada | salida | ajuste (en un ajuste, $cantidad puede ser negativa)
     */
    public static function movimiento(AlmacenArticulo $articulo, string $tipo, float $cantidad, array $extra = []): AlmacenMovimiento
    {
        return DB::transaction(function () use ($articulo, $tipo, $cantidad, $extra) {
            // Bloqueo del registro: dos técnicos no pueden descontar el mismo stock a la vez.
            $articulo = AlmacenArticulo::whereKey($articulo->id)->lockForUpdate()->firstOrFail();
            $variacion = $tipo === 'salida' ? -abs($cantidad) : ($tipo === 'entrada' ? abs($cantidad) : $cantidad);
            $nuevo = round($articulo->stock + $variacion, 2);

            if ($nuevo < 0) {
                throw ValidationException::withMessages([
                    'cantidad' => "Stock insuficiente de «{$articulo->nombre}»: hay ".AlmacenArticulo::cantidad($articulo->stock)." {$articulo->unidad}.",
                ]);
            }

            $articulo->update(['stock' => $nuevo] + ($tipo === 'entrada' && ! empty($extra['costo_unitario']) ? ['costo_unitario' => $extra['costo_unitario']] : []));

            return AlmacenMovimiento::create([
                'articulo_id' => $articulo->id,
                'tipo' => $tipo,
                'cantidad' => $tipo === 'ajuste' ? $cantidad : abs($cantidad),
                'stock_resultante' => $nuevo,
                'costo_unitario' => $extra['costo_unitario'] ?? $articulo->costo_unitario,
                'motivo' => $extra['motivo'] ?? null,
                'documento' => $extra['documento'] ?? null,
                'hoja_servicio_id' => $extra['hoja_servicio_id'] ?? null,
                'proyecto_id' => $extra['proyecto_id'] ?? null,
                'user_id' => $extra['user_id'] ?? auth()->id(),
                'fecha' => $extra['fecha'] ?? now(),
            ]);
        });
    }

    /** Descuenta del almacén los materiales de la hoja que se tomaron de él (antes devuelve lo que la hoja ya había descontado). */
    public static function descontarPorHoja(HojaServicio $hoja): void
    {
        self::devolverPorHoja($hoja, 'Edición de la hoja');

        foreach ($hoja->materiales()->whereNotNull('articulo_id')->with('articulo')->get() as $m) {
            if (! $m->articulo || $m->cantidad <= 0) {
                continue;
            }
            try {
                self::movimiento($m->articulo, 'salida', $m->cantidad, [
                    'motivo' => "Usado en la hoja de servicio {$hoja->numero}",
                    'hoja_servicio_id' => $hoja->id,
                    'proyecto_id' => $hoja->proyecto_id,
                    'user_id' => $hoja->tecnico_id,
                ]);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['materiales' => $e->errors()['cantidad'][0]]);
            }
        }
    }

    /** Devuelve al stock lo que una hoja había descontado (al editarla o anularla). */
    public static function devolverPorHoja(HojaServicio $hoja, string $motivo): void
    {
        $salidas = AlmacenMovimiento::where('hoja_servicio_id', $hoja->id)->with('articulo')->get()
            ->groupBy('articulo_id')
            ->map(fn ($movs) => [$movs->first()->articulo, $movs->sum(fn ($m) => $m->variacion())]);

        foreach ($salidas as [$articulo, $neto]) {
            if ($articulo && $neto < 0) {
                self::movimiento($articulo, 'entrada', -$neto, [
                    'motivo' => "Devolución: $motivo (hoja {$hoja->numero})",
                    'hoja_servicio_id' => $hoja->id,
                    'proyecto_id' => $hoja->proyecto_id,
                ]);
            }
        }
    }
}
