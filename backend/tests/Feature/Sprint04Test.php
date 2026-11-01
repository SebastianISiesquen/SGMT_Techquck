<?php

namespace Tests\Feature;

use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\HojaServicio;
use App\Models\PlanMantenimiento;
use App\Models\Proyecto;
use App\Models\User;
use App\Notifications\MantenimientoProximo;
use App\Services\EquipoService;
use App\Services\IncidenciaService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** Casos de prueba del Sprint 04: equipos con QR, historial del equipo, mantenimientos y hoja de servicio (HU19–HU22). */
class Sprint04Test extends TestCase
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

        $this->cliente = Entidad::create(['nombre' => 'Clínica San Juan de Dios', 'naturaleza' => 'privado', 'sector' => 'Salud', 'contacto_email' => 'ti@clinica.pe']);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Mantenimiento de laptops', 'clase' => 'servicio', 'entidad_id' => $this->cliente->id, 'tipo' => 'Mantenimiento preventivo',
            'fecha_entrega' => today()->subMonth(), 'fecha_fin' => today()->addYear(), 'estado' => 'operativo',
        ]);
        $this->garantia = Garantia::create([
            'proyecto_id' => $this->proyecto->id, 'tipo' => 'Garantía del servicio', 'descripcion' => 'Garantía del servicio',
            'fecha_inicio' => today()->subMonth(), 'fecha_vencimiento' => today()->addYears(2),
        ]);
    }

    private function laptop(array $cambios = []): Equipo
    {
        return Equipo::create([
            'entidad_id' => $this->cliente->id, 'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id,
            'categoria' => 'Laptop', 'marca' => 'Lenovo', 'modelo' => 'E14', 'serie' => 'PF3ABC12', 'responsable' => 'Ana Torres', ...$cambios,
        ]);
    }

    /** CP38 – HU19 */
    public function test_cp38_registrar_equipo_con_codigo_y_serie_unica(): void
    {
        $datos = [
            'entidad_id' => $this->cliente->id, 'proyecto_id' => $this->proyecto->id, 'categoria' => 'Laptop', 'marca' => 'Lenovo',
            'modelo' => 'E14', 'serie' => 'PF3ABC12', 'ubicacion' => 'Admisión – piso 1', 'responsable' => 'Ana Torres', 'estado' => 'operativo',
        ];

        $this->actingAs($this->supervisor)->post('/equipos', $datos)->assertSessionHasNoErrors();
        $this->assertMatchesRegularExpression('/^EQ-\d{6}$/', Equipo::sole()->codigo);

        $this->post('/equipos', $datos)->assertSessionHasErrors('serie');
        $this->assertSame(1, Equipo::count());
    }

    /** CP39 – HU19 */
    public function test_cp39_importar_inventario_desde_excel(): void
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray([
            ['categoria', 'marca', 'modelo', 'serie', 'descripcion', 'ubicacion', 'area', 'responsable', 'fecha_instalacion', 'observaciones'],
            ['Laptop', 'HP', 'ProBook 440', 'SN001', '', 'Piso 2', 'Admisión', 'Luis Vera', '15/03/2026', ''],
            ['Impresora', 'Epson', 'L3250', 'SN002', '', 'Piso 1', 'Farmacia', '', '2026-01-10', ''],
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'eq').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        $this->actingAs($this->supervisor)->post('/equipos/importar', [
            'entidad_id' => $this->cliente->id, 'proyecto_id' => $this->proyecto->id,
            'archivo' => new UploadedFile($ruta, 'inventario.xlsx', null, null, true),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Equipo::count());
        $this->assertSame('2026-03-15', Equipo::where('serie', 'SN001')->sole()->fecha_instalacion->toDateString());
    }

    /** CP40 – HU19 */
    public function test_cp40_imprimir_stickers_con_qr(): void
    {
        $a = $this->laptop();
        $b = $this->laptop(['serie' => 'PF3ABC13']);

        $this->actingAs($this->supervisor)->get("/equipos/etiquetas?ids[]={$a->id}&ids[]={$b->id}")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    /** CP41 – HU20 */
    public function test_cp41_buscar_por_serie_abre_la_ficha_con_sus_antecedentes(): void
    {
        $laptop = $this->laptop();
        $this->actingAs($this->supervisor);
        $servicio = app(IncidenciaService::class);
        $inc = $servicio->registrar([
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id, 'equipos' => [$laptop->id],
            'titulo' => 'Laptop lenta', 'descripcion' => 'Errores de disco', 'prioridad' => 'alta',
            'fecha_reporte' => now()->subHours(3), 'tecnico_id' => $this->tecnico->id,
        ]);
        $this->actingAs($this->tecnico);
        $servicio->registrarAtencion($inc, [
            'fecha_inicio' => now()->subHour(), 'diagnostico' => 'Disco HDD dañado', 'actividades' => 'Cambio por SSD', 'resultado' => 'resuelto',
        ]);

        $this->get('/equipos?buscar=PF3ABC12')->assertRedirect("/equipos/{$laptop->id}");
        $this->get("/equipos/{$laptop->id}")->assertOk()->assertSee('Antecedentes')->assertSee('Disco HDD dañado');
    }

    /** CP42 – HU20 */
    public function test_cp42_la_app_lee_el_qr_y_devuelve_el_historial(): void
    {
        $laptop = $this->laptop();
        EquipoService::registrarEvento($laptop, 'mantenimiento', 'Limpieza interna y cambio de pasta térmica', ['user_id' => $this->tecnico->id]);
        $token = $this->tecnico->createToken('app')->plainTextToken;

        $this->getJson('/api/equipos/codigo/'.urlencode($laptop->contenidoQr()), ['Authorization' => "Bearer $token"])
            ->assertOk()->assertJsonPath('data.codigo', $laptop->codigo)->assertJsonPath('data.serie', 'PF3ABC12');
        $this->get("/e/{$laptop->token_publico}")->assertOk()->assertSee('Limpieza interna y cambio de pasta térmica');
    }

    /** CP43 – HU20 */
    public function test_cp43_hoja_de_vida_del_equipo_en_pdf(): void
    {
        $laptop = $this->laptop();

        $this->actingAs($this->supervisor)->get("/equipos/{$laptop->id}/hoja-de-vida")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    /** CP44 – HU21 */
    public function test_cp44_plan_de_cuatro_visitas_cada_tres_meses(): void
    {
        $this->actingAs($this->supervisor)->post('/planes-mantenimiento', [
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id, 'nombre' => 'Preventivo trimestral',
            'frecuencia_meses' => 3, 'total_visitas' => 4, 'fecha_inicio' => today()->addYears(3)->toDateString(), 'tecnico_id' => $this->tecnico->id,
        ])->assertSessionHasErrors('fecha_inicio'); // después del vencimiento de la garantía

        $inicio = today()->addDays(5);
        $this->post('/planes-mantenimiento', [
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id, 'nombre' => 'Preventivo trimestral',
            'frecuencia_meses' => 3, 'total_visitas' => 4, 'fecha_inicio' => $inicio->toDateString(), 'tecnico_id' => $this->tecnico->id,
        ])->assertSessionHasNoErrors();

        $plan = PlanMantenimiento::sole();
        $this->assertCount(4, $plan->mantenimientos);
        $this->assertSame($inicio->copy()->addMonthsNoOverflow(3)->toDateString(), $plan->mantenimientos[1]->fecha_programada->toDateString());
        $this->assertSame('Mantenimiento 2 de 4', $plan->mantenimientos[1]->etiqueta());
    }

    /** CP45 – HU21 */
    public function test_cp45_las_visitas_no_pasan_la_vigencia_de_la_garantia(): void
    {
        $this->garantia->update(['fecha_vencimiento' => today()->addMonths(5)]);

        $this->actingAs($this->supervisor)->post('/planes-mantenimiento', [
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id, 'nombre' => 'Preventivo trimestral',
            'frecuencia_meses' => 3, 'total_visitas' => 4, 'fecha_inicio' => today()->toDateString(),
        ])->assertSessionHasErrors('total_visitas');
        $this->assertSame(0, PlanMantenimiento::count());
    }

    /** CP46 – HU21 */
    public function test_cp46_se_avisa_al_tecnico_de_la_visita_proxima(): void
    {
        $this->actingAs($this->supervisor)->post('/planes-mantenimiento', [
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id, 'nombre' => 'Preventivo trimestral',
            'frecuencia_meses' => 3, 'total_visitas' => 4, 'fecha_inicio' => today()->addDays(3)->toDateString(), 'tecnico_id' => $this->tecnico->id,
        ]);
        Notification::fake();

        $this->artisan('sggp:alertas')->assertSuccessful();

        Notification::assertSentTo([$this->tecnico, $this->supervisor], MantenimientoProximo::class);
    }

    /** CP47 – HU22 */
    public function test_cp47_hoja_de_servicio_con_equipos_materiales_y_acta_firmada_desde_la_app(): void
    {
        $laptop = $this->laptop();
        $token = $this->tecnico->createToken('app')->plainTextToken;

        $r = $this->post('/api/hojas', [
            'proyecto_id' => $this->proyecto->id, 'tipo_servicio' => 'preventivo', 'fecha' => today()->toDateString(),
            'hora_llegada' => '08:30', 'hora_salida' => '11:00', 'trabajo_realizado' => 'Limpieza interna y actualización de controladores',
            'equipos' => [['equipo_id' => $laptop->id, 'accion' => 'revisado', 'estado_final' => 'operativo']],
            'materiales' => [['descripcion' => 'Pasta térmica', 'cantidad' => 1, 'unidad' => 'tubo']],
            'actas' => [UploadedFile::fake()->image('acta-firmada.jpg')],
            'receptor_nombre' => 'Lic. Patricia Medina', 'receptor_dni' => '41234567',
        ], ['Authorization' => "Bearer $token", 'Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.estado', 'conforme');

        $hoja = HojaServicio::findOrFail($r->json('data.id'));
        $this->assertMatchesRegularExpression('/^HS-\d{4}-\d{5}$/', $hoja->numero);
        $this->assertCount(1, $hoja->equipos);
        $this->assertCount(1, $hoja->materiales);
        $this->assertCount(1, $hoja->actas());
    }

    /** CP48 – HU22 */
    public function test_cp48_la_hoja_de_una_incidencia_exige_diagnostico_y_resultado(): void
    {
        $laptop = $this->laptop();
        $this->actingAs($this->supervisor);
        $inc = app(IncidenciaService::class)->registrar([
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id, 'equipos' => [$laptop->id],
            'titulo' => 'Laptop no enciende', 'descripcion' => 'No carga la batería', 'prioridad' => 'media',
            'fecha_reporte' => now()->subHours(3), 'tecnico_id' => $this->tecnico->id,
        ]);
        auth()->forgetUser();
        $token = $this->tecnico->createToken('app')->plainTextToken;

        $this->post('/api/hojas', [
            'incidencia_id' => $inc->id, 'tipo_servicio' => 'correctivo', 'fecha' => today()->toDateString(),
            'hora_llegada' => '09:00', 'trabajo_realizado' => 'Revisión de cargador',
        ], ['Authorization' => "Bearer $token", 'Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors(['diagnostico', 'resultado']);
        $this->assertSame(0, HojaServicio::count());
    }

    /** CP49 – HU22 */
    public function test_cp49_pdf_de_la_hoja_de_servicio(): void
    {
        $this->actingAs($this->tecnico)->post('/hojas-servicio', [
            'proyecto_id' => $this->proyecto->id, 'tipo_servicio' => 'preventivo', 'fecha' => today()->toDateString(),
            'hora_llegada' => '08:00', 'hora_salida' => '09:30', 'trabajo_realizado' => 'Limpieza de equipos',
        ])->assertSessionHasNoErrors();

        $hoja = HojaServicio::sole();
        $this->actingAs($this->supervisor)->get("/hojas-servicio/{$hoja->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
