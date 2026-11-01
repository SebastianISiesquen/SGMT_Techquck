<?php

namespace Tests\Feature;

use App\Enums\EstadoGarantia;
use App\Models\Archivo;
use App\Models\Entidad;
use App\Models\Garantia;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Casos de prueba del Sprint 02: proyectos y servicios, documentos y garantías (HU06–HU11). */
class Sprint02Test extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;
    private User $tecnico;
    private Entidad $cliente;
    private Entidad $ministerio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->tecnico = User::factory()->create()->assignRole('Técnico');
        $this->cliente = Entidad::create(['nombre' => 'ABC Integradores S.A.C.', 'ruc' => '20555123456', 'naturaleza' => 'privado', 'sector' => 'Telecomunicaciones']);
        $this->ministerio = Entidad::create(['nombre' => 'Ministerio de Justicia', 'naturaleza' => 'publico', 'sector' => 'Gobierno nacional / Ministerios']);
    }

    private function datosProyecto(array $cambios = []): array
    {
        return [
            'nombre' => 'Videovigilancia sede central', 'clase' => 'implementacion', 'entidad_id' => $this->cliente->id,
            'tipo' => 'Videovigilancia', 'orden_compra' => 'OC-2026-0412', 'fecha_entrega' => today()->subMonth()->toDateString(),
            'estado' => 'operativo', ...$cambios,
        ];
    }

    private function proyecto(array $cambios = []): Proyecto
    {
        return Proyecto::create([
            'nombre' => 'Central de monitoreo', 'clase' => 'implementacion', 'entidad_id' => $this->cliente->id, 'tipo' => 'Videovigilancia',
            'fecha_entrega' => today()->subYear(), 'estado' => 'operativo', ...$cambios,
        ]);
    }

    private function garantia(Proyecto $p, string $vence): Garantia
    {
        return Garantia::create([
            'proyecto_id' => $p->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Cámaras y NVR',
            'fecha_inicio' => today()->subYear(), 'fecha_vencimiento' => $vence,
        ]);
    }

    /** CP13 – HU06 */
    public function test_cp13_registrar_proyecto_de_implementacion_con_cliente_final_y_orden_de_compra(): void
    {
        $this->actingAs($this->supervisor)->post('/proyectos', $this->datosProyecto(['cliente_final_id' => $this->ministerio->id]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $p = Proyecto::sole();
        $this->assertMatchesRegularExpression('/^PRY-\d{4}-\d{5}$/', $p->codigo);
        $this->assertSame('OC-2026-0412', $p->orden_compra);
        $this->assertSame('ABC Integradores S.A.C. → Ministerio de Justicia', $p->clientes());
    }

    /** CP14 – HU06 */
    public function test_cp14_el_cliente_final_no_puede_ser_el_mismo_cliente(): void
    {
        $this->actingAs($this->supervisor)->post('/proyectos', $this->datosProyecto(['cliente_final_id' => $this->cliente->id]))
            ->assertSessionHasErrors('cliente_final_id');
        $this->assertSame(0, Proyecto::count());
    }

    /** CP15 – HU06 */
    public function test_cp15_un_servicio_exige_la_fecha_de_fin_del_contrato(): void
    {
        $this->actingAs($this->supervisor)->post('/proyectos', $this->datosProyecto(['clase' => 'servicio', 'tipo' => 'Mantenimiento preventivo']))
            ->assertSessionHasErrors('fecha_fin');
        $this->assertSame(0, Proyecto::count());
    }

    /** CP16 – HU07 */
    public function test_cp16_el_listado_oculta_los_servicios_finalizados(): void
    {
        $this->proyecto(['nombre' => 'Central de monitoreo']);
        $this->proyecto(['nombre' => 'Soporte de laptops 2025', 'clase' => 'servicio', 'estado' => 'finalizado', 'fecha_fin' => today()->subMonth()]);

        $this->actingAs($this->supervisor)->get('/proyectos')->assertOk()
            ->assertSee('Central de monitoreo')->assertDontSee('Soporte de laptops 2025');
        $this->actingAs($this->supervisor)->get('/proyectos?estado=finalizado')->assertOk()->assertSee('Soporte de laptops 2025');
    }

    /** CP17 – HU07 */
    public function test_cp17_editar_los_datos_del_proyecto(): void
    {
        $p = $this->proyecto();

        $this->actingAs($this->supervisor)->put("/proyectos/{$p->id}", $this->datosProyecto(['nombre' => 'Central de monitoreo – ampliación', 'orden_compra' => 'OC-2026-0500']))
            ->assertSessionHasNoErrors();
        $this->assertSame(['Central de monitoreo – ampliación', 'OC-2026-0500'], [$p->fresh()->nombre, $p->fresh()->orden_compra]);
        $this->actingAs($this->tecnico)->put("/proyectos/{$p->id}", $this->datosProyecto())->assertForbidden();
    }

    /** CP18 – HU08 */
    public function test_cp18_adjuntar_contrato_en_disco_privado_y_descargarlo_con_permiso(): void
    {
        $p = $this->proyecto();

        $this->actingAs($this->supervisor)->post("/archivos/proyecto/{$p->id}", [
            'categoria' => 'contrato', 'archivos' => [UploadedFile::fake()->create('contrato.pdf', 300, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $archivo = Archivo::sole();
        Storage::disk('local')->assertExists($archivo->ruta);
        $this->actingAs($this->supervisor)->get("/archivos/{$archivo->id}/descargar")->assertOk();
        $this->actingAs($this->tecnico)->get("/archivos/{$archivo->id}/descargar")->assertForbidden();
    }

    /** CP19 – HU08 */
    public function test_cp19_no_se_aceptan_archivos_ejecutables(): void
    {
        $p = $this->proyecto();

        $this->actingAs($this->supervisor)->post("/archivos/proyecto/{$p->id}", [
            'categoria' => 'documento', 'archivos' => [UploadedFile::fake()->create('virus.exe', 20)],
        ])->assertSessionHasErrors('archivos.0');
        $this->assertSame(0, Archivo::count());
    }

    /** CP20 – HU09 */
    public function test_cp20_registrar_garantia_de_cinco_anios_con_orden_de_compra(): void
    {
        $p = $this->proyecto();

        $this->actingAs($this->supervisor)->post('/garantias', [
            'proyecto_id' => $p->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Cámaras, NVR y switches',
            'orden_compra' => 'OC-2026-0412', 'fecha_inicio' => '2026-01-15', 'duracion_meses' => 60, 'fecha_vencimiento' => '2031-01-15',
        ])->assertSessionHasNoErrors();

        $g = Garantia::sole();
        $this->assertMatchesRegularExpression('/^GAR-\d{4}-\d{5}$/', $g->codigo);
        $this->assertSame('5 años', $g->duracionTexto());
    }

    /** CP21 – HU09 */
    public function test_cp21_el_vencimiento_debe_ser_posterior_al_inicio(): void
    {
        $p = $this->proyecto();

        $this->actingAs($this->supervisor)->post('/garantias', [
            'proyecto_id' => $p->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Cámaras',
            'fecha_inicio' => '2026-01-15', 'fecha_vencimiento' => '2025-12-31',
        ])->assertSessionHasErrors('fecha_vencimiento');
        $this->assertSame(0, Garantia::count());
    }

    /** CP22 – HU10 */
    public function test_cp22_estado_vigente_por_vencer_o_vencida_segun_fechas(): void
    {
        $p = $this->proyecto();

        $this->assertSame(EstadoGarantia::Vigente, $this->garantia($p, today()->addYears(4)->toDateString())->estado);
        $this->assertSame(EstadoGarantia::PorVencer, $this->garantia($p, today()->addDays(20)->toDateString())->estado);
        $this->assertSame(EstadoGarantia::Vencida, $this->garantia($p, today()->subDay()->toDateString())->estado);
    }

    /** CP23 – HU10 */
    public function test_cp23_filtrar_garantias_proximas_a_vencer(): void
    {
        $p = $this->proyecto();
        $vigente = $this->garantia($p, today()->addYears(4)->toDateString());
        $porVencer = $this->garantia($p, today()->addDays(20)->toDateString());

        $this->actingAs($this->supervisor)->get('/garantias?estado='.EstadoGarantia::PorVencer->value)->assertOk()
            ->assertSee($porVencer->codigo)->assertDontSee($vigente->codigo);
    }

    /** CP24 – HU11 */
    public function test_cp24_emitir_carta_de_garantia_en_pdf(): void
    {
        $g = $this->garantia($this->proyecto(), today()->addYears(4)->toDateString());

        $this->actingAs($this->supervisor)->get("/garantias/{$g->id}/carta")->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
