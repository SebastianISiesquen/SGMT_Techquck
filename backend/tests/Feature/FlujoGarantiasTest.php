<?php

namespace Tests\Feature;

use App\Enums\EstadoGarantia;
use App\Models\Entidad;
use App\Models\Garantia;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\User;
use App\Notifications\IncidenciaAsignada;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlujoGarantiasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $supervisor;
    private User $tecnico;
    private Proyecto $proyecto;
    private Garantia $garantia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->create()->assignRole('Administrador');
        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->tecnico = User::factory()->create()->assignRole('Técnico');

        $entidad = Entidad::create(['nombre' => 'Municipalidad de Prueba', 'sector' => 'Gobierno local / Municipalidades', 'contacto_email' => 'ti@muni.gob.pe']);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Videovigilancia', 'entidad_id' => $entidad->id, 'tipo' => 'Videovigilancia',
            'fecha_entrega' => today()->subYear(), 'estado' => 'operativo',
        ]);
        $this->garantia = Garantia::create([
            'proyecto_id' => $this->proyecto->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Equipos',
            'fecha_inicio' => today()->subYear(), 'fecha_vencimiento' => today()->addYears(4),
        ]);
    }

    public function test_estado_de_garantia_se_calcula_por_fechas(): void
    {
        $this->assertSame(EstadoGarantia::Vigente, $this->garantia->estado);

        $this->garantia->update(['fecha_vencimiento' => today()->addDays(20)]);
        $this->assertSame(EstadoGarantia::PorVencer, $this->garantia->fresh()->estado);

        $this->garantia->update(['fecha_vencimiento' => today()->subDay()]);
        $this->assertSame(EstadoGarantia::Vencida, $this->garantia->fresh()->estado);
        $this->assertSame(1, Garantia::vencidas()->count());
    }

    public function test_los_codigos_se_generan_automaticamente(): void
    {
        $this->assertMatchesRegularExpression('/^PRY-\d{4}-\d{5}$/', $this->proyecto->fresh()->codigo);
        $this->assertMatchesRegularExpression('/^GAR-\d{4}-\d{5}$/', $this->garantia->fresh()->codigo);
    }

    public function test_cada_rol_accede_solo_a_sus_modulos(): void
    {
        $this->actingAs($this->tecnico)->get('/dashboard')->assertForbidden();
        $this->actingAs($this->tecnico)->get('/mis-incidencias')->assertOk();
        $this->actingAs($this->supervisor)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($this->supervisor)->get('/dashboard')->assertOk();
        $this->actingAs($this->admin)->get('/admin/usuarios')->assertOk();
    }

    public function test_el_administrador_puede_dar_modulos_extra_a_un_tecnico(): void
    {
        $this->actingAs($this->admin)->put("/admin/usuarios/{$this->tecnico->id}", [
            'name' => $this->tecnico->name, 'email' => $this->tecnico->email,
            'rol' => 'Técnico', 'permisos' => ['reportes.ver'],
        ])->assertRedirect();

        $this->actingAs($this->tecnico->fresh())->get('/reportes')->assertOk();
    }

    public function test_flujo_completo_de_incidencia_con_app_movil(): void
    {
        Notification::fake();

        // 1. El supervisor registra y asigna la incidencia (web)
        $this->actingAs($this->supervisor)->post('/incidencias', [
            'proyecto_id' => $this->proyecto->id,
            'garantia_id' => $this->garantia->id,
            'titulo' => 'Cámara sin señal',
            'descripcion' => 'No hay video en el cruce',
            'prioridad' => 'alta',
            'fecha_reporte' => now()->subHour()->format('Y-m-d H:i'),
            'tecnico_id' => $this->tecnico->id,
        ])->assertRedirect();

        $incidencia = Incidencia::firstOrFail();
        $this->assertSame('asignada', $incidencia->estado->value);
        Notification::assertSentTo($this->tecnico, IncidenciaAsignada::class);

        // 2. El técnico inicia sesión en la app y ve su incidencia
        $token = $this->postJson('/api/login', ['email' => $this->tecnico->email, 'password' => 'password'])
            ->assertOk()->json('token');
        $api = ['Authorization' => "Bearer $token", 'Accept' => 'application/json'];

        $this->getJson('/api/incidencias', $api)->assertOk()->assertJsonPath('data.0.codigo', $incidencia->codigo);

        // 3. Registra la atención con foto y acta de conformidad
        $this->post("/api/incidencias/{$incidencia->id}/atenciones", [
            'fecha_inicio' => now()->subMinutes(30)->toDateTimeString(),
            'diagnostico' => 'Conector RJ45 dañado',
            'actividades' => 'Cambio de conector y pruebas',
            'resultado' => 'resuelto',
            'archivos' => [
                UploadedFile::fake()->image('camara.jpg'),
                UploadedFile::fake()->create('acta.pdf', 120, 'application/pdf'),
            ],
            'categorias' => ['foto', 'acta_conformidad'],
        ], $api)->assertCreated()->assertJsonPath('data.estado', 'resuelta');

        $incidencia->refresh();
        $this->assertSame('resuelta', $incidencia->estado->value);
        $this->assertTrue($incidencia->tieneActaConformidad());
        $this->assertCount(2, $incidencia->todasLasEvidencias());
        Storage::disk('local')->assertExists($incidencia->todasLasEvidencias()->first()->ruta);

        // 4. El supervisor da conformidad y cierra
        $this->actingAs($this->supervisor)->post("/incidencias/{$incidencia->id}/cerrar")->assertRedirect();
        $this->assertSame('cerrada', $incidencia->fresh()->estado->value);

        // Trazabilidad completa: registro, asignación, atención y cierre
        $this->assertEqualsCanonicalizing(
            ['registro', 'asignacion', 'atencion', 'cierre'],
            $incidencia->historial()->pluck('accion')->all(),
        );
    }

    public function test_no_se_puede_cerrar_sin_acta_de_conformidad(): void
    {
        $incidencia = $this->incidenciaAsignada();

        $this->actingAs($this->tecnico)->post("/mis-incidencias/{$incidencia->id}/atenciones", [
            'fecha_inicio' => now()->subHour()->format('Y-m-d H:i'),
            'diagnostico' => 'Falla', 'actividades' => 'Reparación', 'resultado' => 'resuelto',
        ])->assertRedirect();

        $this->actingAs($this->supervisor)->post("/incidencias/{$incidencia->id}/cerrar")->assertSessionHasErrors('estado');
        $this->assertSame('resuelta', $incidencia->fresh()->estado->value);
    }

    public function test_un_tecnico_no_ve_incidencias_ni_archivos_de_otros(): void
    {
        $incidencia = $this->incidenciaAsignada();
        $otro = User::factory()->create()->assignRole('Técnico');

        $this->actingAs($otro)->get("/mis-incidencias/{$incidencia->id}")->assertForbidden();

        $token = $otro->createToken('t')->plainTextToken;
        $this->getJson("/api/incidencias/{$incidencia->id}", ['Authorization' => "Bearer $token"])->assertForbidden();
    }

    public function test_usuario_desactivado_no_puede_ingresar(): void
    {
        $this->tecnico->update(['activo' => false]);

        $this->postJson('/api/login', ['email' => $this->tecnico->email, 'password' => 'password'])->assertUnprocessable();
        $this->post('/login', ['email' => $this->tecnico->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_reportes_se_exportan_en_pdf_y_excel(): void
    {
        $this->incidenciaAsignada();

        foreach (['proyectos', 'garantias', 'incidencias', 'atenciones'] as $tipo) {
            $this->actingAs($this->supervisor)->get("/reportes/$tipo/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->actingAs($this->supervisor)->get("/reportes/$tipo/excel")->assertOk();
        }
    }

    public function test_comando_de_alertas_notifica_garantias_por_vencer(): void
    {
        Notification::fake();
        $this->garantia->update(['fecha_vencimiento' => today()->addDays(30)]);

        $this->artisan('sggp:alertas')->assertSuccessful();

        Notification::assertSentTo($this->supervisor, \App\Notifications\AlertaGarantia::class);
        Notification::assertNotSentTo($this->tecnico, \App\Notifications\AlertaGarantia::class);
    }

    private function incidenciaAsignada(): Incidencia
    {
        $this->actingAs($this->supervisor);

        return app(\App\Services\IncidenciaService::class)->registrar([
            'proyecto_id' => $this->proyecto->id,
            'garantia_id' => $this->garantia->id,
            'titulo' => 'Switch sin PoE',
            'descripcion' => 'Puertos sin energía',
            'prioridad' => 'media',
            'fecha_reporte' => now()->subHours(2),
            'tecnico_id' => $this->tecnico->id,
        ]);
    }
}
