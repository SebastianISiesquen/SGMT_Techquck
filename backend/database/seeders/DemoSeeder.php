<?php

namespace Database\Seeders;

use App\Enums\EstadoIncidencia;
use App\Models\Entidad;
use App\Models\Garantia;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Datos de ejemplo para demostrar el sistema (prueba piloto / presentación). */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Entidad::exists()) {
            return;
        }

        $supervisor = $this->usuario('Robert Ordóñez Bautista', 'supervisor@techquk.com.pe', 'Supervisor', 'Jefe de Proyectos');
        $tec1 = $this->usuario('Luis Paredes Chapoñán', 'tecnico1@techquk.com.pe', 'Técnico', 'Técnico de campo');
        $tec2 = $this->usuario('Jorge Vílchez Santisteban', 'tecnico2@techquk.com.pe', 'Técnico', 'Técnico especialista en redes');

        $entidades = collect([
            ['Municipalidad Provincial de Chiclayo', '20175639391', 'Gobierno local / Municipalidades', 'Av. Balta 820, Chiclayo', 'Ing. María Távara', 'Gerente de Seguridad Ciudadana'],
            ['Gobierno Regional de Lambayeque', '20479569861', 'Gobierno regional', 'Av. Juan Tomis Stack 975, Chiclayo', 'Lic. Carlos Ruiz', 'Jefe de Tecnologías de la Información'],
            ['Hospital Regional Lambayeque', '20479397904', 'Salud', 'Prolong. Augusto B. Leguía, Chiclayo', 'Ing. Rosa Delgado', 'Jefa de Informática'],
            ['Universidad Nacional Pedro Ruiz Gallo', '20148238641', 'Educación', 'Calle Juan XXIII 391, Lambayeque', 'Mg. Pedro Sánchez', 'Director de TIC'],
            ['Municipalidad Distrital de José Leonardo Ortiz', '20175637704', 'Gobierno local / Municipalidades', 'Av. Sáenz Peña 2151, JLO', 'Sr. Juan Cieza', 'Sub Gerente de Serenazgo'],
        ])->map(fn ($e) => Entidad::create([
            'nombre' => $e[0], 'ruc' => $e[1], 'naturaleza' => 'publico', 'sector' => $e[2], 'direccion' => $e[3], 'departamento' => 'Lambayeque',
            'contacto_nombre' => $e[4], 'contacto_cargo' => $e[5], 'contacto_telefono' => '9'.random_int(10000000, 99999999),
            'contacto_email' => 'contacto'.random_int(10, 99).'@gob.pe',
        ]));

        // [entidad, nombre, tipo, meses desde la entrega, años de garantía]
        $proyectos = [
            [0, 'Sistema de videovigilancia urbana – Centro de Chiclayo (120 cámaras)', 'Videovigilancia', 50, 5],
            [0, 'Central de monitoreo de Serenazgo y enlaces de fibra óptica', 'Telecomunicaciones / Radioenlaces', 22, 5],
            [1, 'Renovación del centro de datos de la sede regional', 'Servidores y centro de datos', 34, 3],
            [1, 'Red WAN de radioenlaces para agencias agrarias', 'Telecomunicaciones / Radioenlaces', 57, 5],
            [2, 'Cableado estructurado Cat 6A y red inalámbrica hospitalaria', 'Redes y cableado estructurado', 13, 3],
            [2, 'Videovigilancia de áreas críticas y control de accesos', 'Seguridad electrónica', 28, 3],
            [3, 'Red de campus y equipamiento de laboratorios de cómputo', 'Redes y cableado estructurado', 9, 5],
            [4, 'Sistema de videovigilancia distrital (64 cámaras PTZ)', 'Videovigilancia', 40, 4],
        ];

        foreach ($proyectos as $n => [$ent, $nombre, $tipo, $meses, $anios]) {
            $entrega = today()->subMonths($meses);
            $proyecto = Proyecto::create([
                'nombre' => $nombre,
                'entidad_id' => $entidades[$ent]->id,
                'tipo' => $tipo,
                'descripcion' => "Suministro, instalación y puesta en funcionamiento: $nombre.",
                'numero_contrato' => sprintf('CONTRATO N° %03d-%d', 10 + $n, $entrega->year),
                'orden_compra' => sprintf('OC-%d-%04d', $entrega->year, 120 + $n * 7),
                'monto_contrato' => random_int(180, 2400) * 1000,
                'ubicacion' => $entidades[$ent]->direccion,
                'fecha_inicio' => $entrega->copy()->subMonths(5),
                'fecha_entrega' => $entrega,
                'responsable_id' => $supervisor->id,
                'estado' => 'operativo',
            ]);

            $principal = Garantia::create([
                'proyecto_id' => $proyecto->id,
                'tipo' => 'Garantía de equipos',
                'descripcion' => 'Garantía comercial de equipos suministrados',
                'cobertura' => 'Reposición y reparación de equipos por fallas de fabricación (cámaras, switches, servidores, UPS).',
                'condiciones' => 'Atención en sitio en un máximo de 48 horas. No cubre daños por vandalismo, descargas eléctricas o manipulación de terceros.',
                'proveedor' => collect(['Hikvision Perú', 'Cisco Systems', 'Dell Technologies', 'Huawei', 'Ubiquiti'])->random(),
                'numero_documento' => 'CG-'.random_int(1000, 9999),
                'orden_compra' => $proyecto->orden_compra,
                'fecha_inicio' => $entrega,
                'duracion_meses' => $anios * 12,
                'fecha_vencimiento' => $entrega->copy()->addYears($anios),
            ]);

            Garantia::create([
                'proyecto_id' => $proyecto->id,
                'tipo' => 'Garantía de instalación',
                'descripcion' => 'Garantía de instalación y configuración',
                'cobertura' => 'Fallas atribuibles a la instalación: cableado, conectores, configuración y montaje.',
                'condiciones' => 'Mesa de ayuda L–V 08:00–18:00.',
                'fecha_inicio' => $entrega,
                'duracion_meses' => min($anios, 2) * 12,
                'fecha_vencimiento' => $entrega->copy()->addYears(min($anios, 2)),
            ]);

            $this->incidencias($proyecto, $principal, $supervisor, [$tec1, $tec2]);
        }
    }

    private function usuario(string $nombre, string $email, string $rol, string $cargo): User
    {
        $u = User::firstOrCreate(['email' => $email], [
            'name' => $nombre, 'password' => 'Techquk2026*', 'cargo' => $cargo, 'telefono' => '9'.random_int(10000000, 99999999),
        ]);
        $u->syncRoles($rol);

        return $u;
    }

    protected function incidencias(Proyecto $proyecto, Garantia $garantia, User $supervisor, array $tecnicos): void
    {
        $casos = [
            ['Cámara sin señal de video', 'La cámara del cruce indicado no muestra imagen en el centro de monitoreo.', 'alta'],
            ['Switch PoE con puertos inoperativos', 'Cuatro puertos del switch de distribución no entregan energía PoE.', 'media'],
            ['Falla de disco en NVR / servidor', 'El equipo reporta disco degradado en el arreglo RAID.', 'critica'],
            ['Intermitencia en enlace de fibra', 'Se reportan cortes intermitentes en el enlace hacia el nodo principal.', 'alta'],
            ['UPS no mantiene carga', 'El UPS del gabinete se apaga ante cortes de energía.', 'baja'],
        ];

        $estados = [EstadoIncidencia::Cerrada, EstadoIncidencia::Resuelta, EstadoIncidencia::EnProceso, EstadoIncidencia::Asignada, EstadoIncidencia::Registrada];

        foreach (collect($casos)->random(random_int(1, 4)) as $k => [$titulo, $desc, $prioridad]) {
            $estado = $estados[array_rand($estados)];
            $tecnico = $tecnicos[array_rand($tecnicos)];
            $reporte = Carbon::now()->subDays(random_int(1, 120))->setTime(random_int(8, 17), random_int(0, 59));

            $inc = Incidencia::create([
                'proyecto_id' => $proyecto->id,
                'garantia_id' => $garantia->id,
                'titulo' => $titulo,
                'descripcion' => $desc,
                'prioridad' => $prioridad,
                'estado' => $estado,
                'canal' => collect(['Teléfono', 'Correo electrónico', 'Oficio', 'WhatsApp'])->random(),
                'reportado_por' => $proyecto->entidad->contacto_nombre,
                'fecha_reporte' => $reporte,
                'fecha_limite' => $reporte->copy()->addDays(3),
                'registrado_por' => $supervisor->id,
            ]);
            $inc->historial()->create(['user_id' => $supervisor->id, 'accion' => 'registro', 'estado_nuevo' => 'registrada', 'detalle' => 'Incidencia registrada', 'created_at' => $reporte]);

            if ($estado === EstadoIncidencia::Registrada) {
                continue;
            }

            $asignacion = $reporte->copy()->addHours(random_int(1, 20));
            $inc->update(['tecnico_id' => $tecnico->id, 'asignado_por' => $supervisor->id, 'fecha_asignacion' => $asignacion]);
            $inc->historial()->create(['user_id' => $supervisor->id, 'accion' => 'asignacion', 'estado_anterior' => 'registrada', 'estado_nuevo' => 'asignada', 'detalle' => "Asignada a {$tecnico->name}", 'created_at' => $asignacion]);

            if (in_array($estado, [EstadoIncidencia::Resuelta, EstadoIncidencia::Cerrada, EstadoIncidencia::EnProceso])) {
                $inicio = $asignacion->copy()->addHours(random_int(2, 30));
                $resuelto = $estado !== EstadoIncidencia::EnProceso;
                $inc->atenciones()->create([
                    'tecnico_id' => $tecnico->id,
                    'fecha_inicio' => $inicio,
                    'fecha_fin' => $inicio->copy()->addHours(random_int(1, 4)),
                    'diagnostico' => 'Se verificó en sitio la falla reportada. '.($resuelto ? 'Componente defectuoso identificado.' : 'Se requiere repuesto del fabricante.'),
                    'actividades' => "Inspección visual y pruebas de conectividad.\nRevisión de configuración y logs.\n".($resuelto ? 'Reemplazo del componente y pruebas de funcionamiento.' : 'Solicitud de RMA al proveedor.'),
                    'observaciones' => $resuelto ? 'Equipo operativo, se firmó acta de conformidad con el usuario.' : null,
                    'resultado' => $resuelto ? 'resuelto' : 'pendiente_repuesto',
                    'origen' => 'movil',
                ]);
                $inc->historial()->create(['user_id' => $tecnico->id, 'accion' => 'atencion', 'estado_anterior' => 'asignada', 'estado_nuevo' => $resuelto ? 'resuelta' : 'en_proceso', 'detalle' => 'Atención registrada', 'created_at' => $inicio]);

                if ($resuelto) {
                    $inc->update(['fecha_resolucion' => $inicio->copy()->addHours(3)]);
                }
                if ($estado === EstadoIncidencia::Cerrada) {
                    $inc->update(['fecha_cierre' => $inicio->copy()->addDay(), 'cerrado_por' => $supervisor->id]);
                    $inc->historial()->create(['user_id' => $supervisor->id, 'accion' => 'cierre', 'estado_anterior' => 'resuelta', 'estado_nuevo' => 'cerrada', 'detalle' => 'Conformidad verificada', 'created_at' => $inicio->copy()->addDay()]);
                }
            }
        }

    }
}
