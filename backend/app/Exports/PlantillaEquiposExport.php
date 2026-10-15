<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Plantilla de Excel para cargar el inventario de equipos de un cliente. */
class PlantillaEquiposExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return ['categoria', 'marca', 'modelo', 'serie', 'descripcion', 'ubicacion', 'area', 'responsable', 'fecha_instalacion', 'observaciones'];
    }

    public function array(): array
    {
        return [
            ['Laptop', 'Lenovo', 'ThinkPad E14 Gen 5', 'PF4ABC12', 'Laptop de gerencia', 'Sede central, 2.º piso', 'Gerencia', 'Ana Torres', '2026-03-15', ''],
            ['Cámara IP fija', 'Hikvision', 'DS-2CD2143G2-I', 'K12345678', 'Cámara ingreso principal', 'Poste 14 - Av. Balta', 'Seguridad', '', '2025-11-20', 'Ejemplo: borre estas filas'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
