<?php

namespace Database\Seeders;

use App\Models\HojaServicio;
use App\Models\Incidencia;
use App\Services\HojaServicioService;
use Illuminate\Database\Seeder;

/** Hojas de servicio de demostración (se puede ejecutar sobre una BD ya poblada). */
class DemoHojasSeeder extends Seeder
{
    public function run(): void
    {
        if (HojaServicio::exists()) {
            return;
        }

        $servicio = app(HojaServicioService::class);
        $firma = json_encode(['w' => 300, 'h' => 120, 'trazos' => [
            [[20, 80], [40, 40], [60, 90], [80, 30], [100, 85], [120, 50]],
            [[140, 70], [170, 60], [200, 75], [230, 55], [260, 70]],
        ]]);

        // Hoja firmada con conformidad para una incidencia resuelta, y otra pendiente de firma
        $casos = Incidencia::with(['tecnico', 'proyecto.entidad'])->whereIn('estado', ['resuelta', 'en_proceso'])->whereNotNull('tecnico_id')->limit(2)->get();

        foreach ($casos as $n => $inc) {
            auth()->setUser($inc->tecnico);
            $servicio->crear(
                [
                    'incidencia_id' => $inc->id,
                    'tipo_servicio' => 'correctivo',
                    'fecha' => today()->subDays($n + 1)->toDateString(),
                    'hora_llegada' => '09:15',
                    'hora_salida' => '11:40',
                    'trabajo_realizado' => "Diagnóstico en sitio de: {$inc->titulo}.\nReemplazo del componente defectuoso y pruebas de funcionamiento con el usuario.",
                    'recomendaciones' => 'Mantener el gabinete cerrado y con ventilación.',
                ],
                [
                    ['equipo' => 'Cámara IP PTZ', 'marca' => 'Hikvision', 'modelo' => 'DS-2DE4425IW', 'serie' => 'HK'.random_int(100000, 999999), 'ubicacion' => 'Poste 14', 'accion' => 'reemplazado', 'estado_final' => 'operativo'],
                    ['equipo' => 'Switch PoE 8p', 'marca' => 'Cisco', 'modelo' => 'CBS250-8P', 'serie' => 'FOC'.random_int(1000000, 9999999), 'ubicacion' => 'Gabinete exterior', 'accion' => 'revisado', 'estado_final' => 'operativo'],
                ],
                [['descripcion' => 'Conector RJ45 Cat6 blindado', 'cantidad' => 4, 'unidad' => 'und'], ['descripcion' => 'Cinta aislante', 'cantidad' => 1, 'unidad' => 'rollo']],
                [],
                $n === 0 ? [
                    'receptor_nombre' => $inc->proyecto->entidad->contacto_nombre ?? 'Responsable de la entidad',
                    'receptor_dni' => (string) random_int(40000000, 49999999),
                    'receptor_cargo' => $inc->proyecto->entidad->contacto_cargo,
                    'firma_receptor' => $firma,
                    'firma_tecnico' => $firma,
                    'conforme' => true,
                ] : null,
                'movil',
            );
        }

        auth()->forgetUser();
    }
}
