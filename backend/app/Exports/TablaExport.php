<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta a Excel la misma tabla que se usa en el PDF, para que ambos formatos coincidan. */
class TablaExport implements FromView, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private string $vista, private array $datos, private string $titulo) {}

    public function view(): View
    {
        return view($this->vista, [...$this->datos, 'excel' => true]);
    }

    public function title(): string
    {
        return mb_substr($this->titulo, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 13]]];
    }
}
