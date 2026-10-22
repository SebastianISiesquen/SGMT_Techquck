<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\HojaServicio;
use App\Models\Mantenimiento;
use App\Models\PlanMantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Notifications\AlertaGarantia;
use App\Notifications\AvisoVencimientoCliente;
use App\Notifications\MantenimientoProximo;
use App\Services\HojaServicioService;
use App\Services\IncidenciaService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Clientes públicos y privados, subcontratos, equipos con historial, mantenimientos, alertas y cotizaciones. */
class EquiposYMantenimientosTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;
    private User $tecnico;
    private Entidad $cliente;
    private Proyecto $proyecto;
    private Garantia $garantia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->tecnico = User::factory()->create()->assignRole('Técnico');

        $this->cliente = Entidad::create([
            'nombre' => 'Clínica Privada SAC', 'naturaleza' => 'privado', 'sector' => 'Salud',
            'contacto_email' => 'ti@clinica.pe', 'correos_alertas' => 'gerencia@clinica.pe, logistica@clinica.pe',
        ]);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Mantenimiento de laptops', 'clase' => 'servicio', 'entidad_id' => $this->cliente->id,
            'tipo' => 'Servidores y centro de datos', 'orden_compra' => 'OS-2026-0088',
            'fecha_entrega' => today()->subMonth(), 'estado' => 'operativo', 'responsable_id' => $this->supervisor->id,
        ]);
        $this->garantia = Garantia::create([
            'proyecto_id' => $this->proyecto->id, 'tipo' => 'Garantía del servicio', 'descripcion' => 'Garantía del servicio',
            'fecha_inicio' => today()->subMonth(), 'duracion_meses' => 3, 'fecha_vencimiento' => today()->subMonth()->addMonths(3),
        ]);
    }

    public function test_clientes_privados_y_subcontrato_con_cliente_final(): void
    {
        $abc = Entidad::create(['nombre' => 'ABC Integradores', 'naturaleza' => 'privado', 'sector' => 'Telecomunicaciones']);
        $ministerio = Entidad::create(['nombre' => 'Ministerio de Justicia', 'naturaleza' => 'publico', 'sector' => 'Gobierno nacional / Ministerios']);

        $datos = [
            'nombre' => 'Cámaras MINJUS', 'clase' => 'implementacion', 'entidad_id' => $abc->id, 'tipo' => 'Videovigilancia',
            'orden_compra' => 'OC-ABC-412', 'fecha_entrega' => today()->subMonth()->toDateString(), 'estado' => 'operativo',
        ];

        // El cliente final no puede ser el mismo que contrata
        $this->actingAs($this->supervisor)->post('/proyectos', [...$datos, 'cliente_final_id' => $abc->id])->assertSessionHasErrors('cliente_final_id');

        $this->actingAs($this->supervisor)->post('/proyectos', [...$datos, 'cliente_final_id' => $ministerio->id])->assertSessionHasNoErrors();
        $proyecto = Proyecto::where('nombre', 'Cámaras MINJUS')->firstOrFail();

        $this->assertSame('ABC Integradores → Ministerio de Justicia', $proyecto->clientes());
        $this->assertSame($ministerio->id, $proyecto->lugarDeServicio()->id);
        $this->actingAs($this->supervisor)->get('/entidades?naturaleza=privado')->assertSee('ABC Integradores')->assertDontSee('Ministerio de Justicia');
        $this->actingAs($this->supervisor)->get("/entidades/{$ministerio->id}")->assertSee('Como cliente final')->assertSee('Cámaras MINJUS');
    }

    public function test_los_equipos_se_registran_dentro_del_proyecto(): void
    {
        $datos = [
            'nombre' => 'Videovigilancia Chiclayo', 'clase' => 'implementacion', 'entidad_id' => $this->cliente->id, 'tipo' => 'Videovigilancia',
            'fecha_entrega' => today()->subMonth()->toDateString(), 'estado' => 'operativo',
        ];

        // Serie ya usada en el cliente: no se crea nada (ni el proyecto)
        Equipo::create(['entidad_id' => $this->cliente->id, 'categoria' => 'NVR', 'serie' => 'NVR-001']);
        $this->actingAs($this->supervisor)->post('/proyectos', [...$datos, 'equipos' => [['categoria' => 'NVR / DVR', 'serie' => 'NVR-001']]])
            ->assertSessionHasErrors('equipos.0.serie');
        $this->assertDatabaseMissing('proyectos', ['nombre' => 'Videovigilancia Chiclayo']);

        // 120 cámaras iguales en una fila + 1 NVR con serie; las filas vacías se ignoran
        $this->actingAs($this->supervisor)->post('/proyectos', [...$datos, 'equipos' => [
            ['categoria' => 'Cámara PTZ', 'marca' => 'Hikvision', 'modelo' => 'DS-2DE4425', 'cantidad' => 120, 'ubicacion' => 'Centro histórico'],
            ['categoria' => 'NVR / DVR', 'marca' => 'Hikvision', 'serie' => 'NVR-777', 'cantidad' => 5],
            ['categoria' => '', 'marca' => 'vacía'],
        ]])->assertSessionHasNoErrors();

        $proyecto = Proyecto::where('nombre', 'Videovigilancia Chiclayo')->firstOrFail();
        $this->assertSame(120, $proyecto->equipos()->where('categoria', 'Cámara PTZ')->count());
        $this->assertSame(1, $proyecto->equipos()->where('serie', 'NVR-777')->count());
        $this->assertSame(121, $proyecto->equipos()->whereHas('eventos', fn ($q) => $q->where('tipo', 'instalacion'))->count());

        // Agregar más desde la ficha del proyecto
        $this->actingAs($this->supervisor)->post("/proyectos/{$proyecto->id}/equipos", ['equipos' => [['categoria' => 'Switch', 'cantidad' => 2]]])
            ->assertSessionHasNoErrors();
        $this->assertSame(123, $proyecto->equipos()->count());
        $this->actingAs($this->supervisor)->get("/proyectos/{$proyecto->id}")->assertOk()->assertSee('Equipos instalados (123)');

        // La garantía que se registra después cubre los equipos del proyecto
        $this->actingAs($this->supervisor)->post('/garantias', [
            'proyecto_id' => $proyecto->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Cámaras y NVR',
            'fecha_inicio' => today()->subMonth()->toDateString(), 'duracion_meses' => 60, 'fecha_vencimiento' => today()->addYears(5)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $proyecto->equipos()->whereNull('garantia_id')->count());

        // El técnico no puede registrar equipos en el proyecto
        $this->actingAs($this->tecnico)->post("/proyectos/{$proyecto->id}/equipos", ['equipos' => [['categoria' => 'PC']]])->assertForbidden();
    }

    public function test_contrato_de_servicio_crea_garantia_plan_y_equipos(): void
    {
        $datos = [
            'nombre' => 'Mantenimiento de 20 laptops 2026', 'clase' => 'servicio', 'entidad_id' => $this->cliente->id,
            'tipo' => 'Mantenimiento preventivo', 'orden_compra' => 'OS-2026-0099', 'fecha_entrega' => '2026-01-15', 'estado' => 'operativo',
        ];

        // Un servicio exige fecha de fin del contrato
        $this->actingAs($this->supervisor)->post('/proyectos', $datos)->assertSessionHasErrors('fecha_fin');

        $this->actingAs($this->supervisor)->post('/proyectos', [...$datos, 'fecha_fin' => '2027-01-14',
            'visitas_total' => 4, 'visitas_frecuencia' => 3, 'garantia_meses' => 3,
            'equipos' => [['categoria' => 'Laptop', 'marca' => 'Lenovo', 'cantidad' => 20]],
        ])->assertSessionHasNoErrors();

        $servicio = Proyecto::where('nombre', 'Mantenimiento de 20 laptops 2026')->firstOrFail();
        $garantia = $servicio->garantias()->sole();
        $this->assertSame('2027-04-14', $garantia->fecha_vencimiento->toDateString());

        $plan = $servicio->planesMantenimiento()->sole();
        $this->assertSame($garantia->id, $plan->garantia_id);
        $this->assertSame(['2026-01-15', '2026-04-15', '2026-07-15', '2026-10-15'],
            $plan->mantenimientos->map(fn ($m) => $m->fecha_programada->toDateString())->all());

        $this->assertSame(20, $servicio->equipos()->where('garantia_id', $garantia->id)->count());
        $this->actingAs($this->supervisor)->get("/proyectos/{$servicio->id}/edit")->assertOk()->assertSee('4 visitas, cada 3 mes(es)');
    }

    public function test_avisos_de_vencimiento_al_supervisor_y_al_cliente_sin_repetirse(): void
    {
        Notification::fake();
        $this->garantia->update(['fecha_vencimiento' => today()->addDays(25)]);

        $this->artisan('sggp:alertas')->assertSuccessful();

        Notification::assertSentTo($this->supervisor, AlertaGarantia::class);
        Notification::assertSentOnDemand(AvisoVencimientoCliente::class, function ($n, $canales, $destino) {
            return $destino->routes['mail'] === ['ti@clinica.pe', 'gerencia@clinica.pe', 'logistica@clinica.pe'] && $n->dias === 25;
        });
        $this->assertDatabaseHas('garantia_alertas', ['garantia_id' => $this->garantia->id, 'dias' => 30]);

        // El mismo hito no se vuelve a enviar
        Notification::fake();
        $this->artisan('sggp:alertas')->assertSuccessful();
        Notification::assertNothingSent();

        // Garantía configurada para no avisar al cliente: solo aviso interno
        Notification::fake();
        $this->garantia->update(['fecha_vencimiento' => today()->addDays(10), 'notificar_cliente' => false]);
        $this->artisan('sggp:alertas')->assertSuccessful();
        Notification::assertSentTo($this->supervisor, AlertaGarantia::class);
        Notification::assertSentOnDemandTimes(AvisoVencimientoCliente::class, 0);
    }

    public function test_carta_de_garantia_en_pdf(): void
    {
        $this->actingAs($this->supervisor)->get("/garantias/{$this->garantia->id}/carta")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame('3 meses', $this->garantia->duracionTexto());
    }

    public function test_historial_del_equipo_se_arma_con_incidencias_atenciones_y_hojas(): void
    {
        $this->actingAs($this->supervisor)->post('/equipos', [
            'entidad_id' => $this->cliente->id, 'proyecto_id' => $this->proyecto->id, 'categoria' => 'Laptop',
            'marca' => 'Lenovo', 'modelo' => 'E14', 'serie' => 'PF3ABC12', 'responsable' => 'Ana Torres', 'estado' => 'operativo',
        ])->assertSessionHasNoErrors();
        $laptop = Equipo::firstOrFail();
        $this->assertMatchesRegularExpression('/^EQ-\d{6}$/', $laptop->codigo);

        // Serie repetida en el mismo cliente: rechazada
        $this->actingAs($this->supervisor)->post('/equipos', [
            'entidad_id' => $this->cliente->id, 'categoria' => 'Laptop', 'serie' => 'PF3ABC12', 'estado' => 'operativo',
        ])->assertSessionHasErrors('serie');

        // Falla reportada sobre el equipo y atención del técnico
        $incidencias = app(IncidenciaService::class);
        $inc = $incidencias->registrar([
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id, 'equipo_id' => $laptop->id, 'cobertura' => 'contrato',
            'titulo' => 'Laptop lenta', 'descripcion' => 'Errores de disco', 'prioridad' => 'alta',
            'fecha_reporte' => now()->subHours(3), 'tecnico_id' => $this->tecnico->id,
        ]);
        $this->assertSame('con_falla', $laptop->fresh()->estado);

        $this->actingAs($this->tecnico);
        $incidencias->registrarAtencion($inc, [
            'fecha_inicio' => now()->subHour(), 'diagnostico' => 'Disco HDD dañado', 'actividades' => 'Cambio por SSD', 'resultado' => 'resuelto',
        ]);
        $this->assertSame('operativo', $laptop->fresh()->estado);

        // Hoja de servicio: cambio de componente del equipo del inventario y un equipo nuevo por serie
        app(HojaServicioService::class)->crear(
            ['incidencia_id' => $inc->id, 'tipo_servicio' => 'correctivo', 'fecha' => today()->toDateString(), 'hora_llegada' => '09:00', 'trabajo_realizado' => 'Cambio de disco'],
            [
                ['equipo_id' => $laptop->id, 'accion' => 'cambio_componente', 'estado_final' => 'operativo', 'observacion' => 'Disco duro'],
                ['equipo' => 'Impresora', 'marca' => 'Epson', 'serie' => 'X5NEW99', 'accion' => 'revisado', 'estado_final' => 'observado'],
            ],
        );

        $tipos = $laptop->eventos()->pluck('tipo')->all();
        $this->assertEqualsCanonicalizing(['registro', 'falla', 'diagnostico', 'cambio_componente'], $tipos);
        $this->assertSame('Disco duro', $laptop->eventos()->where('tipo', 'cambio_componente')->value('componente'));

        $impresora = Equipo::where('serie', 'X5NEW99')->firstOrFail();
        $this->assertSame($this->cliente->id, $impresora->entidad_id);
        $this->assertSame('observado', $impresora->estado);

        // El técnico ve el historial; buscar por serie abre la ficha; el QR redirige a la ficha
        $this->actingAs($this->tecnico)->get("/equipos/{$laptop->id}")->assertOk()->assertSee('Antecedentes')->assertSee('Disco HDD dañado');
        $this->actingAs($this->tecnico)->get('/equipos?buscar=PF3ABC12')->assertRedirect("/equipos/{$laptop->id}");
        $this->actingAs($this->tecnico)->get("/equipos/qr/{$laptop->codigo}")->assertRedirect("/equipos/{$laptop->id}");
        $this->actingAs($this->tecnico)->post('/equipos', ['entidad_id' => $this->cliente->id, 'categoria' => 'PC', 'estado' => 'operativo'])->assertForbidden();

        // Hoja de vida y stickers en PDF
        $this->actingAs($this->supervisor)->get("/equipos/{$laptop->id}/hoja-de-vida")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->supervisor)->get("/equipos/etiquetas?ids[]={$laptop->id}")->assertOk()->assertHeader('content-type', 'application/pdf');

        // App: escanear el QR (URL completa) devuelve el equipo con su historial
        auth()->forgetUser();
        $token = $this->tecnico->createToken('app')->plainTextToken;
        $this->getJson('/api/equipos/codigo/'.urlencode($laptop->contenidoQr()), ['Authorization' => "Bearer $token"])
            ->assertOk()->assertJsonPath('data.codigo', $laptop->codigo)->assertJsonCount(4, 'data.eventos');
        $this->getJson("/api/incidencias/{$inc->id}", ['Authorization' => "Bearer $token"])
            ->assertOk()->assertJsonPath('data.equipo.codigo', $laptop->codigo)->assertJsonPath('data.cobertura', 'contrato');
    }

    public function test_las_visitas_del_plan_no_pasan_la_vigencia_de_la_garantia(): void
    {
        // La garantía del setUp vence dentro de 2 meses: con visitas mensuales caben 3 (hoy, +1 y +2 meses).
        $vence = $this->garantia->fecha_vencimiento->format('d/m/Y');
        $cuarta = today()->addMonthsNoOverflow(3)->format('d/m/Y');
        $datos = [
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id, 'nombre' => 'Preventivo mensual',
            'frecuencia_meses' => 1, 'total_visitas' => 4, 'fecha_inicio' => today()->toDateString(),
        ];

        $this->actingAs($this->supervisor)->post('/planes-mantenimiento', $datos)
            ->assertSessionHasErrors(['total_visitas' => "La garantía {$this->garantia->codigo} vence el $vence: con visitas cada 1 mes solo caben 3 dentro de la vigencia (la visita 4 caería el $cuarta)."]);
        $this->assertSame(0, PlanMantenimiento::count());

        $this->post('/planes-mantenimiento', ['fecha_inicio' => today()->addMonths(3)->toDateString()] + $datos)
            ->assertSessionHasErrors('fecha_inicio');

        $this->post('/planes-mantenimiento', ['total_visitas' => 3] + $datos)->assertSessionHasNoErrors();
        $this->assertTrue(PlanMantenimiento::sole()->mantenimientos->every(fn ($m) => $m->fecha_programada->lte($this->garantia->fecha_vencimiento)));
    }

    public function test_plan_de_mantenimiento_programa_visitas_y_la_hoja_las_cierra(): void
    {
        $this->actingAs($this->supervisor)->post('/planes-mantenimiento', [
            'proyecto_id' => $this->proyecto->id, 'nombre' => 'Preventivo trimestral', 'frecuencia_meses' => 3, 'total_visitas' => 4,
            'fecha_inicio' => '2026-01-31', 'tecnico_id' => $this->tecnico->id,
        ])->assertSessionHasNoErrors();

        $plan = PlanMantenimiento::firstOrFail();
        $this->assertSame(['2026-01-31', '2026-04-30', '2026-07-31', '2026-10-31'],
            $plan->mantenimientos->map(fn ($m) => $m->fecha_programada->toDateString())->all());
        $this->assertSame('Mantenimiento 2 de 4', $plan->mantenimientos[1]->etiqueta());

        // Visita próxima: se avisa al técnico y al supervisor
        Notification::fake();
        $visita = $plan->mantenimientos[1];
        $visita->update(['fecha_programada' => today()->addDays(3)]);
        $this->artisan('sggp:alertas')->assertSuccessful();
        Notification::assertSentTo([$this->tecnico, $this->supervisor], MantenimientoProximo::class);

        // El técnico registra la hoja de esa visita: queda realizada; si se anula, vuelve a pendiente
        $this->actingAs($this->tecnico)->post('/hojas-servicio', [
            'mantenimiento_id' => $visita->id, 'proyecto_id' => $this->proyecto->id, 'tipo_servicio' => 'preventivo',
            'fecha' => today()->toDateString(), 'hora_llegada' => '08:00', 'trabajo_realizado' => 'Limpieza y actualización',
        ])->assertSessionHasNoErrors();
        $hoja = HojaServicio::firstOrFail();
        $this->assertSame('realizado', $visita->fresh()->estado);
        $this->assertSame($hoja->id, $visita->fresh()->hoja_servicio_id);

        app(HojaServicioService::class)->anular($hoja, 'Error de registro');
        $this->assertSame('programado', $visita->fresh()->estado);

        $this->actingAs($this->tecnico)->get('/mantenimientos')->assertOk()->assertSee('Mantenimiento 1 de 4');
    }

    public function test_servicio_vencido_se_finaliza_y_deja_de_listarse(): void
    {
        $this->proyecto->update(['fecha_fin' => today()->subDay()]);
        $this->artisan('sggp:alertas')->assertSuccessful();

        $this->assertSame('finalizado', $this->proyecto->fresh()->estado);
        $this->actingAs($this->supervisor)->get('/proyectos')->assertDontSee('Mantenimiento de laptops');
        $this->actingAs($this->supervisor)->get('/proyectos?estado=finalizado')->assertSee('Mantenimiento de laptops');
    }

    public function test_cotizacion_calcula_igv_y_genera_pdf(): void
    {
        $this->actingAs($this->supervisor)->post('/cotizaciones', [
            'entidad_id' => $this->cliente->id, 'fecha' => today()->toDateString(), 'validez_dias' => 15, 'moneda' => 'PEN',
            'asunto' => 'Diagnóstico de radioenlace',
            'items' => [
                ['descripcion' => 'Diagnóstico', 'cantidad' => 1, 'unidad' => 'servicio', 'precio_unitario' => 350],
                ['descripcion' => 'Protector Ethernet', 'cantidad' => 2, 'unidad' => 'und', 'precio_unitario' => 95],
                ['descripcion' => '', 'cantidad' => 1, 'precio_unitario' => 999],
            ],
        ])->assertSessionHasNoErrors();

        $c = Cotizacion::firstOrFail();
        $this->assertMatchesRegularExpression('/^COT-\d{4}-\d{5}$/', $c->numero);
        $this->assertCount(2, $c->items);
        $this->assertEquals([540.00, 97.20, 637.20], [(float) $c->subtotal, (float) $c->igv, (float) $c->total]);
        $this->actingAs($this->supervisor)->get("/cotizaciones/{$c->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->tecnico)->get('/cotizaciones')->assertForbidden();
    }

    public function test_importar_inventario_desde_excel_sin_duplicar_series(): void
    {
        Equipo::create(['entidad_id' => $this->cliente->id, 'categoria' => 'Laptop', 'serie' => 'YAEXISTE']);

        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray([
            ['categoria', 'marca', 'modelo', 'serie', 'descripcion', 'ubicacion', 'area', 'responsable', 'fecha_instalacion', 'observaciones'],
            ['Laptop', 'HP', 'ProBook 440', 'SN001', '', 'Piso 2', 'Admisión', 'Luis', '15/03/2026', ''],
            ['Laptop', 'HP', 'ProBook 440', 'yaexiste', '', '', '', '', '', ''],
            ['Impresora', 'Epson', 'L3250', '', '', 'Piso 1', 'Farmacia', '', '2026-01-10', ''],
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'eq').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        $this->actingAs($this->supervisor)->post('/equipos/importar', [
            'entidad_id' => $this->cliente->id, 'proyecto_id' => $this->proyecto->id,
            'archivo' => new UploadedFile($ruta, 'inventario.xlsx', null, null, true),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(3, Equipo::count());
        $nueva = Equipo::where('serie', 'SN001')->firstOrFail();
        $this->assertSame('2026-03-15', $nueva->fecha_instalacion->toDateString());
        $this->assertSame($this->garantia->id, $nueva->garantia_id);
        $this->assertSame(1, $nueva->eventos()->count());
    }

    public function test_el_qr_abre_la_ficha_publica_sin_iniciar_sesion(): void
    {
        $equipo = Equipo::create(['entidad_id' => $this->cliente->id, 'proyecto_id' => $this->proyecto->id, 'categoria' => 'UPS', 'marca' => 'Riello', 'serie' => 'DMSTK20']);
        \App\Services\EquipoService::registrarEvento($equipo, 'mantenimiento', 'Mantenimiento preventivo del banco de baterías', ['user_id' => $this->tecnico->id]);

        // El QR usa la dirección pública configurada y un código aleatorio (no el correlativo)
        \App\Support\Ajustes::set(['url_publica' => 'https://sggp.techquk.com.pe']);
        $this->assertMatchesRegularExpression('#^https://sggp\.techquk\.com\.pe/e/[a-z0-9]{20}$#', $equipo->contenidoQr());

        $this->get("/e/{$equipo->token_publico}")->assertOk()
            ->assertSee($equipo->codigo)->assertSee('Mantenimiento preventivo del banco de baterías')->assertSee('Clínica Privada SAC');
        $this->get('/e/codigoinexistente123')->assertNotFound();

        // La app reconoce el QR nuevo
        $token = $this->tecnico->createToken('app')->plainTextToken;
        $this->getJson('/api/equipos/codigo/'.urlencode($equipo->contenidoQr()), ['Authorization' => "Bearer $token"])
            ->assertOk()->assertJsonPath('data.codigo', $equipo->codigo);
    }

    public function test_el_correo_se_configura_desde_la_web(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador');

        $this->actingAs($admin)->get('/admin/configuracion')->assertOk()->assertSee('Correo saliente (SMTP)');

        \App\Support\Ajustes::set([
            'correo_host' => 'smtp.gmail.com', 'correo_puerto' => 587, 'correo_cifrado' => 'tls',
            'correo_usuario' => 'avisos@techquk.com.pe', 'correo_clave' => \App\Support\Correo::cifrarClave('clave-de-aplicacion'),
        ]);
        \App\Support\Correo::aplicar();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame('clave-de-aplicacion', config('mail.mailers.smtp.password'));
        $this->assertSame('avisos@techquk.com.pe', config('mail.from.address'));
        $this->assertTrue(\App\Support\Correo::activo());
    }

    public function test_editar_serie_y_ubicacion_y_subir_fei_desde_el_proyecto(): void
    {
        $this->actingAs($this->supervisor)->post(route('proyectos.equipos.store', $this->proyecto), [
            'equipos' => [['categoria' => 'Cámara IP', 'cantidad' => 2, 'ubicacion' => 'Almacén']],
        ]);
        [$a, $b] = $this->proyecto->equipos()->orderBy('id')->get()->all();

        // Serie repetida entre filas: no se guarda nada
        $this->put(route('proyectos.equipos.update', $this->proyecto), ['editar' => [
            $a->id => ['serie' => 'SN-1', 'ubicacion' => 'Poste 14'],
            $b->id => ['serie' => 'SN-1', 'ubicacion' => 'Almacén'],
        ]])->assertSessionHasErrors("editar.{$b->id}.serie");
        $this->assertNull($a->fresh()->serie);

        $this->put(route('proyectos.equipos.update', $this->proyecto), ['editar' => [
            $a->id => ['serie' => 'SN-1', 'ubicacion' => 'Poste 14'],
            $b->id => ['serie' => 'SN-2', 'ubicacion' => 'Almacén'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['SN-1', 'Poste 14'], [$a->fresh()->serie, $a->fresh()->ubicacion]);
        $this->assertDatabaseHas('equipo_eventos', ['equipo_id' => $a->id, 'tipo' => 'traslado']);

        $this->post(route('archivos.store', ['tipo' => 'equipo', 'id' => $a->id]), [
            'categoria' => 'foto_instalacion', 'archivos' => [UploadedFile::fake()->image('poste.jpg')],
        ])->assertSessionHasNoErrors();
        $this->assertSame('foto_instalacion', $a->fresh()->fotoInstalacion->categoria->value);

        $this->get(route('proyectos.show', $this->proyecto))->assertOk()
            ->assertSee('FEI')->assertSee('Guardar serie y ubicación')->assertSee('value="SN-1"', false);

        // El informe de un proyecto de implementación usa las fechas de la ficha y lista los equipos con su FEI
        $this->proyecto->update(['clase' => 'implementacion', 'fecha_inicio' => today()->subMonths(2), 'fecha_entrega' => today()->subMonth()]);
        $datos = app(\App\Services\InformeClienteService::class)->datos($this->proyecto->fresh(), today()->subMonths(2), now());
        $this->assertCount(2, $datos['equipos']);
        $this->assertStringStartsWith('data:image/', $datos['feis'][$a->id]['src']);
        $this->get(route('proyectos.informe', $this->proyecto))->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
