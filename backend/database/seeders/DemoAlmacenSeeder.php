<?php

namespace Database\Seeders;

use App\Models\AlmacenArticulo;
use App\Services\AlmacenService;
use Illuminate\Database\Seeder;

/** Artículos de ejemplo del almacén (solo si está vacío). */
class DemoAlmacenSeeder extends Seeder
{
    public function run(): void
    {
        if (AlmacenArticulo::exists()) {
            return;
        }

        $articulos = [
            // [tipo, nombre, categoría, marca, unidad, stock, mínimo, costo]
            ['consumible', 'Pasta térmica', 'Limpieza', 'Arctic MX-4', 'tubo', 8, 3, 28],
            ['consumible', 'Limpia contactos', 'Limpieza', 'CRC', 'frasco', 2, 3, 22],
            ['consumible', 'Aire comprimido', 'Limpieza', null, 'frasco', 6, 2, 18],
            ['consumible', 'Conector RJ45 Cat6 blindado', 'Conectividad', 'Dixon', 'und', 150, 50, 1.2],
            ['consumible', 'Cinta aislante', 'Conectividad', '3M', 'rollo', 12, 5, 4.5],
            ['consumible', 'Cintillos de amarre 20 cm', 'Conectividad', null, 'paquete', 4, 2, 9],
            ['repuesto', 'Disco SSD 512 GB', 'Almacenamiento', 'Kingston', 'und', 4, 2, 165],
            ['repuesto', 'Memoria RAM DDR4 8 GB', 'Cómputo', 'Kingston', 'und', 3, 2, 120],
            ['repuesto', 'Fuente 12V 2A para cámara', 'Energía', null, 'und', 10, 4, 25],
            ['repuesto', 'Cámara domo IP 4 MP (reemplazo)', 'Cámaras y CCTV', 'Hikvision', 'und', 2, 1, 340],
            ['repuesto', 'Disco de vigilancia 4 TB', 'Almacenamiento', 'Seagate SkyHawk', 'und', 1, 1, 420],
        ];

        foreach ($articulos as [$tipo, $nombre, $categoria, $marca, $unidad, $stock, $minimo, $costo]) {
            $a = AlmacenArticulo::create([
                'tipo' => $tipo, 'nombre' => $nombre, 'categoria' => $categoria, 'marca' => $marca, 'unidad' => $unidad,
                'stock_minimo' => $minimo, 'costo_unitario' => $costo, 'ubicacion' => $tipo === 'consumible' ? 'Estante A' : 'Estante B',
            ]);
            AlmacenService::movimiento($a, 'entrada', $stock, ['motivo' => 'Inventario inicial', 'costo_unitario' => $costo, 'documento' => 'INV-INICIAL']);
        }
    }
}
