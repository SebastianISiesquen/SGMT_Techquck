<?php

namespace Database\Seeders;

use App\Models\Garantia;
use App\Models\Proyecto;
use App\Models\User;

/**
 * Completa una base que ya tiene clientes y proyectos: agrega órdenes de compra,
 * garantías e incidencias a los proyectos que no las tienen, sin borrar nada.
 * Luego carga clientes privados, subcontrato, equipos, mantenimientos y cotizaciones.
 *
 *   php artisan db:seed --class=CompletarDatosSeeder
 */
class CompletarDatosSeeder extends DemoSeeder
{
    public function run(): void
    {
        $supervisor = User::where('email', 'supervisor@techquk.com.pe')->firstOrFail();
        $tecnicos = User::whereIn('email', ['tecnico1@techquk.com.pe', 'tecnico2@techquk.com.pe'])->get()->all();

        foreach (Proyecto::with(['entidad', 'garantias'])->get() as $n => $p) {
            if (! $p->orden_compra) {
                $p->update(['orden_compra' => sprintf('OC-%d-%04d', $p->fecha_entrega->year, 120 + $n * 7)]);
            }

            if ($p->garantias->isNotEmpty()) {
                continue;
            }

            // Garantía principal (3 a 5 años) y soporte de 2 años, como en los contratos reales
            $anios = [5, 5, 3, 5, 3, 3, 5, 4][$n % 8];
            $principal = Garantia::create([
                'proyecto_id' => $p->id,
                'tipo' => 'Garantía de equipos',
                'descripcion' => 'Garantía comercial de equipos suministrados',
                'cobertura' => 'Reposición y reparación de equipos por fallas de fabricación (cámaras, switches, servidores, UPS).',
                'condiciones' => 'Atención en sitio en un máximo de 48 horas. No cubre daños por vandalismo, descargas eléctricas o manipulación de terceros.',
                'proveedor' => ['Hikvision Perú', 'Cisco Systems', 'Dell Technologies', 'Huawei', 'Ubiquiti'][$n % 5],
                'numero_documento' => 'CG-'.(4100 + $n * 37),
                'orden_compra' => $p->orden_compra,
                'fecha_inicio' => $p->fecha_entrega,
                'duracion_meses' => $anios * 12,
                'fecha_vencimiento' => $p->fecha_entrega->copy()->addYears($anios),
            ]);
            Garantia::create([
                'proyecto_id' => $p->id,
                'tipo' => 'Garantía de instalación',
                'descripcion' => 'Garantía de instalación y configuración',
                'cobertura' => 'Fallas atribuibles a la instalación: cableado, conectores, configuración y montaje.',
                'condiciones' => 'Mesa de ayuda L–V 08:00–18:00.',
                'orden_compra' => $p->orden_compra,
                'fecha_inicio' => $p->fecha_entrega,
                'duracion_meses' => 24,
                'fecha_vencimiento' => $p->fecha_entrega->copy()->addYears(2),
            ]);

            $this->incidencias($p, $principal, $supervisor, $tecnicos);
        }

        $this->call(DemoEquiposSeeder::class);
    }
}
