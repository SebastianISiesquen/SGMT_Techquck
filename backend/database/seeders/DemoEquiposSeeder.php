<?php

namespace Database\Seeders;

use App\Models\Cotizacion;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\PlanMantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\EquipoService;
use App\Services\IncidenciaService;
use Illuminate\Database\Seeder;

/**
 * Demostración de clientes privados, subcontratos, equipos con historial,
 * planes de mantenimiento y cotizaciones (se puede ejecutar sobre una BD ya poblada).
 */
class DemoEquiposSeeder extends Seeder
{
    public function run(): void
    {
        $supervisor = User::where('email', 'supervisor@techquk.com.pe')->first();
        $tecnico = User::where('email', 'tecnico1@techquk.com.pe')->first();
        if (Equipo::exists() || ! $supervisor || ! $tecnico) {
            return;
        }

        auth()->setUser($supervisor);

        // Clientes privados y un contratista que subcontrata a la empresa
        $minera = $this->cliente('Compañía Minera Lambayeque S.A.C.', '20601234567', 'privado', 'Minería', 'Ing. Hugo Bravo', 'Jefe de Sistemas');
        $clinica = $this->cliente('Clínica San Juan de Dios Chiclayo', '20488765432', 'privado', 'Salud', 'Lic. Patricia Medina', 'Coordinadora de TI');
        $abc = $this->cliente('ABC Integradores S.A.C.', '20555123456', 'privado', 'Telecomunicaciones', 'Ing. Mario León', 'Gerente de Proyectos');
        $minjus = $this->cliente('Ministerio de Justicia y Derechos Humanos', '20131371617', 'publico', 'Gobierno nacional / Ministerios', 'Ing. Elena Ríos', 'Oficina de Tecnologías de la Información');

        // Servicio de mantenimiento para un cliente privado: 20 laptops, 4 visitas al año
        $servicio = Proyecto::create([
            'nombre' => 'Mantenimiento preventivo y soporte de equipos de cómputo 2026',
            'clase' => 'servicio', 'entidad_id' => $clinica->id, 'tipo' => 'Servidores y centro de datos',
            'orden_compra' => 'OS-2026-0088', 'monto_contrato' => 18500, 'ubicacion' => 'Av. Grau 1255, Chiclayo',
            'fecha_entrega' => today()->subMonths(5), 'fecha_fin' => today()->addMonths(7),
            'responsable_id' => $supervisor->id, 'estado' => 'operativo',
            'descripcion' => '4 mantenimientos preventivos al año de 20 laptops y 2 servidores, con soporte correctivo.',
        ]);
        $garServicio = Garantia::create([
            'proyecto_id' => $servicio->id, 'tipo' => 'Garantía del servicio', 'descripcion' => 'Garantía del servicio de mantenimiento',
            'orden_compra' => 'OS-2026-0088', 'fecha_inicio' => $servicio->fecha_entrega, 'duracion_meses' => 3,
            'fecha_vencimiento' => $servicio->fecha_entrega->copy()->addMonthsNoOverflow(3)->addMonthsNoOverflow(3),
            'cobertura' => 'Garantía de 3 meses sobre cada mantenimiento realizado.',
            'observaciones' => 'Incluye 4 mantenimientos preventivos; este periodo cubre el 2.º de 4.',
        ]);

        $plan = PlanMantenimiento::create([
            'proyecto_id' => $servicio->id, 'garantia_id' => $garServicio->id, 'nombre' => 'Mantenimiento preventivo de laptops y servidores',
            'frecuencia_meses' => 3, 'total_visitas' => 4, 'fecha_inicio' => $servicio->fecha_entrega->copy()->addMonth(),
            'tecnico_id' => $tecnico->id, 'alcance' => "Limpieza interna, cambio de pasta térmica, actualización de sistema y antivirus,\nrevisión de discos (SMART) y batería.",
        ]);
        $plan->programarVisitas();
        $plan->mantenimientos()->where('numero', 1)->update(['estado' => 'realizado', 'fecha_ejecucion' => $plan->fecha_inicio]);

        $areas = ['Gerencia', 'Admisión', 'Farmacia', 'Laboratorio', 'Contabilidad', 'Logística', 'Emergencia', 'Recursos Humanos'];
        $laptops = collect(range(1, 20))->map(fn ($i) => Equipo::create([
            'entidad_id' => $clinica->id, 'proyecto_id' => $servicio->id, 'garantia_id' => $garServicio->id,
            'categoria' => 'Laptop', 'marca' => $i % 3 ? 'Lenovo' : 'HP', 'modelo' => $i % 3 ? 'ThinkPad E14 Gen 4' : 'ProBook 440 G9',
            'serie' => ($i % 3 ? 'PF3' : '5CG').strtoupper(substr(md5("lap$i"), 0, 6)),
            'area' => $areas[$i % count($areas)], 'ubicacion' => 'Sede principal, '.(1 + $i % 3).'.º piso',
            'responsable' => collect(['Ana Torres', 'Luis Quispe', 'Carmen Vera', 'José Díaz', 'Rosa Chávez', 'Miguel Sosa'])[$i % 6],
            'fecha_instalacion' => today()->subYears(2)->subDays($i * 9),
        ]));

        // Historial de ejemplo: la laptop que cambió de disco (el caso que pidió el jefe de proyectos)
        $laptops->each(fn (Equipo $e) => EquipoService::registrarEvento($e, 'registro', 'Equipo registrado en el inventario', ['fecha' => $e->fecha_instalacion]));
        $laptops->each(fn (Equipo $e) => EquipoService::registrarEvento($e, 'mantenimiento', 'Mantenimiento 1 de 4 · limpieza y actualización', [
            'fecha' => $plan->fecha_inicio->copy()->setTime(10, 0), 'user_id' => $tecnico->id, 'estado_equipo' => 'operativo',
            'detalle' => 'Limpieza interna, pasta térmica, actualizaciones. Disco en buen estado.',
        ]));

        $critica = $laptops[3];
        EquipoService::registrarEvento($critica, 'observacion', 'Disco con sectores reasignados (SMART)', [
            'fecha' => $plan->fecha_inicio->copy()->setTime(10, 30), 'user_id' => $tecnico->id, 'estado_equipo' => 'observado',
            'detalle' => 'En el mantenimiento se detectó el disco con 12 sectores reasignados. Se recomendó al usuario respaldar y programar el cambio.',
        ]);
        $incidencias = app(IncidenciaService::class);
        $inc = $incidencias->registrar([
            'proyecto_id' => $servicio->id, 'garantia_id' => $garServicio->id, 'equipo_id' => $critica->id, 'cobertura' => 'contrato',
            'titulo' => 'Laptop muy lenta y con errores al iniciar', 'descripcion' => 'La usuaria indica que la laptop demora más de 10 minutos en encender y muestra errores de disco.',
            'prioridad' => 'alta', 'canal' => 'WhatsApp', 'reportado_por' => $critica->responsable,
            'fecha_reporte' => $plan->fecha_inicio->copy()->addMonths(2)->setTime(9, 15), 'tecnico_id' => $tecnico->id,
        ]);
        auth()->setUser($tecnico);
        $incidencias->iniciar($inc);
        $incidencias->registrarAtencion($inc, [
            'fecha_inicio' => $inc->fecha_reporte->copy()->addHours(5), 'fecha_fin' => $inc->fecha_reporte->copy()->addHours(7),
            'diagnostico' => 'Disco duro HDD con falla (sectores dañados en aumento, ya observado en el mantenimiento anterior).',
            'actividades' => "Respaldo de información del usuario.\nCambio de disco HDD 1 TB por SSD 512 GB.\nClonación del sistema y pruebas.",
            'resultado' => 'resuelto',
        ]);
        EquipoService::registrarEvento($critica, 'cambio_componente', 'Cambio de disco HDD 1 TB por SSD 512 GB', [
            'fecha' => $inc->fecha_reporte->copy()->addHours(7), 'componente' => 'Disco duro', 'incidencia_id' => $inc->id, 'estado_equipo' => 'operativo',
            'detalle' => 'El disco retirado se entregó al área de logística del cliente.',
        ]);
        auth()->setUser($supervisor);

        // Subcontrato: ABC contrata a la empresa para el Ministerio de Justicia
        $sub = Proyecto::create([
            'nombre' => 'Implementación de videovigilancia – Sede central del MINJUSDH (subcontrato)',
            'clase' => 'implementacion', 'entidad_id' => $abc->id, 'cliente_final_id' => $minjus->id,
            'tipo' => 'Videovigilancia', 'orden_compra' => 'OC-ABC-2025-0412', 'numero_contrato' => 'SUBCONTRATO 015-2025',
            'monto_contrato' => 640000, 'ubicacion' => 'Sede MINJUSDH, Chiclayo', 'fecha_entrega' => today()->subMonths(10),
            'responsable_id' => $supervisor->id, 'estado' => 'operativo',
            'descripcion' => 'Suministro e instalación de 48 cámaras, 2 NVR y red PoE. Los informes se emiten a ABC Integradores.',
        ]);
        $garSub = Garantia::create([
            'proyecto_id' => $sub->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Garantía de cámaras, NVR y switches',
            'orden_compra' => 'OC-ABC-2025-0412', 'fecha_inicio' => $sub->fecha_entrega, 'duracion_meses' => 60,
            'fecha_vencimiento' => $sub->fecha_entrega->copy()->addYears(5), 'proveedor' => 'Hikvision Perú',
            'cobertura' => 'Reposición de cámaras, NVR y switches por fallas de fabricación.',
        ]);
        foreach (range(1, 6) as $i) {
            $cam = Equipo::create([
                'entidad_id' => $minjus->id, 'proyecto_id' => $sub->id, 'garantia_id' => $garSub->id,
                'categoria' => $i <= 5 ? 'Cámara IP fija' : 'NVR / DVR', 'marca' => 'Hikvision',
                'modelo' => $i <= 5 ? 'DS-2CD2143G2-I' : 'DS-7732NI-K4', 'serie' => 'K'.strtoupper(substr(md5("cam$i"), 0, 8)),
                'ubicacion' => $i <= 5 ? "Pasillo {$i}, 1.er piso" : 'Cuarto de comunicaciones', 'area' => 'Seguridad',
                'fecha_instalacion' => $sub->fecha_entrega,
            ]);
            EquipoService::registrarEvento($cam, 'instalacion', 'Equipo instalado', ['fecha' => $sub->fecha_entrega]);
        }

        // Proyecto con garantía vencida para el cliente minero: diagnóstico a pedido y cotización
        $antiguo = Proyecto::create([
            'nombre' => 'Red de radioenlaces campamento minero', 'clase' => 'implementacion', 'entidad_id' => $minera->id,
            'tipo' => 'Telecomunicaciones / Radioenlaces', 'orden_compra' => 'OC-ML-2021-033', 'fecha_entrega' => today()->subYears(4)->subMonths(2),
            'responsable_id' => $supervisor->id, 'estado' => 'operativo',
        ]);
        Garantia::create([
            'proyecto_id' => $antiguo->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Garantía de radios y antenas',
            'fecha_inicio' => $antiguo->fecha_entrega, 'duracion_meses' => 36, 'fecha_vencimiento' => $antiguo->fecha_entrega->copy()->addYears(3),
        ]);
        $radio = Equipo::create([
            'entidad_id' => $minera->id, 'proyecto_id' => $antiguo->id, 'categoria' => 'Radioenlace', 'marca' => 'Ubiquiti',
            'modelo' => 'airFiber 5XHD', 'serie' => 'UBNT7845A1', 'ubicacion' => 'Torre 2 - Campamento', 'fecha_instalacion' => $antiguo->fecha_entrega,
        ]);
        EquipoService::registrarEvento($radio, 'instalacion', 'Equipo instalado', ['fecha' => $antiguo->fecha_entrega]);

        $cotizacion = Cotizacion::create([
            'entidad_id' => $minera->id, 'proyecto_id' => $antiguo->id, 'equipo_id' => $radio->id, 'fecha' => today()->subDays(3),
            'validez_dias' => 15, 'asunto' => 'Diagnóstico y reparación de radioenlace Torre 2',
            'diagnostico' => 'El radio presenta pérdida de enlace por daño en el puerto Ethernet (descarga eléctrica). Garantía vencida.',
            'condiciones' => \App\Support\Ajustes::get('condiciones_cotizacion'), 'estado' => 'enviada', 'user_id' => $supervisor->id,
        ]);
        $cotizacion->items()->createMany([
            ['descripcion' => 'Diagnóstico técnico en sitio', 'cantidad' => 1, 'unidad' => 'servicio', 'precio_unitario' => 350],
            ['descripcion' => 'Radio Ubiquiti airFiber 5XHD (reemplazo)', 'cantidad' => 1, 'unidad' => 'und', 'precio_unitario' => 2890],
            ['descripcion' => 'Protector de descargas Ethernet', 'cantidad' => 2, 'unidad' => 'und', 'precio_unitario' => 95],
            ['descripcion' => 'Mano de obra de instalación y alineamiento', 'cantidad' => 1, 'unidad' => 'servicio', 'precio_unitario' => 600],
        ]);
        $cotizacion->recalcular();

        // Equipos de los proyectos municipales para el inventario
        Proyecto::where('tipo', 'Videovigilancia')->whereNull('cliente_final_id')->with('garantias')->get()->each(function (Proyecto $p) {
            foreach (range(1, 4) as $i) {
                $cam = Equipo::create([
                    'entidad_id' => $p->entidad_id, 'proyecto_id' => $p->id, 'garantia_id' => $p->garantias->first()?->id,
                    'categoria' => 'Cámara PTZ', 'marca' => 'Hikvision', 'modelo' => 'DS-2DE4425IW-DE',
                    'serie' => 'HK'.strtoupper(substr(md5("ptz{$p->id}-$i"), 0, 8)), 'ubicacion' => "Poste {$i}0 - ".($p->ubicacion ?? 'vía pública'),
                    'area' => 'Seguridad ciudadana', 'fecha_instalacion' => $p->fecha_entrega,
                ]);
                EquipoService::registrarEvento($cam, 'instalacion', 'Equipo instalado', ['fecha' => $p->fecha_entrega]);
            }
        });

        auth()->forgetUser();
    }

    private function cliente(string $nombre, string $ruc, string $naturaleza, string $sector, string $contacto, string $cargo): Entidad
    {
        return Entidad::firstOrCreate(['ruc' => $ruc], [
            'nombre' => $nombre, 'naturaleza' => $naturaleza, 'sector' => $sector, 'direccion' => 'Chiclayo', 'departamento' => 'Lambayeque',
            'contacto_nombre' => $contacto, 'contacto_cargo' => $cargo, 'contacto_telefono' => '9'.random_int(10000000, 99999999),
            'contacto_email' => 'ti@'.str($nombre)->slug()->limit(18, '').'.com.pe',
        ]);
    }
}
