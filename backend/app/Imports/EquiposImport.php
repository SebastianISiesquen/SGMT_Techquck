<?php

namespace App\Imports;

use App\Models\Equipo;
use App\Services\EquipoService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Importa el inventario de equipos de un cliente desde la plantilla de Excel.
 * Se omiten las filas sin categoría y las series que el cliente ya tiene registradas.
 */
class EquiposImport implements ToCollection, WithHeadingRow
{
    public int $creados = 0;

    public int $omitidos = 0;

    public function __construct(private int $entidadId, private ?int $proyectoId, private ?int $garantiaId) {}

    public function collection(Collection $filas): void
    {
        $series = Equipo::where('entidad_id', $this->entidadId)->whereNotNull('serie')->pluck('serie')->map(fn ($s) => strtoupper($s))->flip();

        foreach ($filas as $fila) {
            $categoria = trim((string) ($fila['categoria'] ?? ''));
            $serie = trim((string) ($fila['serie'] ?? '')) ?: null;

            if ($categoria === '' || ($serie && $series->has(strtoupper($serie)))) {
                $this->omitidos += $categoria === '' && ! $serie && ! filled($fila['marca'] ?? null) ? 0 : 1;

                continue;
            }

            $equipo = Equipo::create([
                'entidad_id' => $this->entidadId,
                'proyecto_id' => $this->proyectoId,
                'garantia_id' => $this->garantiaId,
                'categoria' => mb_substr($categoria, 0, 60),
                'marca' => $this->texto($fila['marca'] ?? null, 80),
                'modelo' => $this->texto($fila['modelo'] ?? null, 80),
                'serie' => $serie ? mb_substr($serie, 0, 80) : null,
                'descripcion' => $this->texto($fila['descripcion'] ?? null, 255),
                'ubicacion' => $this->texto($fila['ubicacion'] ?? null, 255),
                'area' => $this->texto($fila['area'] ?? null, 100),
                'responsable' => $this->texto($fila['responsable'] ?? null, 255),
                'fecha_instalacion' => $this->fecha($fila['fecha_instalacion'] ?? null),
                'observaciones' => $this->texto($fila['observaciones'] ?? null, 3000),
            ]);

            EquipoService::registrarEvento($equipo, 'registro', 'Equipo registrado por importación de inventario', [
                'fecha' => $equipo->fecha_instalacion ?? now(),
            ]);

            if ($serie) {
                $series->put(strtoupper($serie), true);
            }
            $this->creados++;
        }
    }

    private function texto(mixed $valor, int $max): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : mb_substr($valor, 0, $max);
    }

    /** Acepta fechas de Excel (número de serie) o texto (2026-03-15, 15/03/2026). */
    private function fecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            $fecha = is_numeric($valor)
                ? Carbon::instance(Date::excelToDateTimeObject($valor))
                : (str_contains($valor, '/') ? Carbon::createFromFormat('d/m/Y', trim($valor)) : Carbon::parse($valor));

            return $fecha->isFuture() ? null : $fecha->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
