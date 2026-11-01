<?php

namespace Tests\Feature;

use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\Garantia;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\User;
use App\Notifications\AlertaGarantia;
use App\Notifications\AvisoVencimientoCliente;
use App\Notifications\IncidenciaAsignada;
use App\Services\IncidenciaService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Casos de prueba del Sprint 03: incidencias, asignación, atención técnica, evidencias, historial y alertas (HU12–HU18). */
class Sprint03Test extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;
    private User $tecnico;
    private Proyecto $proyecto;
    private Garantia $garantia;
    private Equipo $camara;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->tecnico = User::factory()->create()->assignRole('Técnico');

        $cliente = Entidad::create([
            'nombre' => 'Municipalidad Provincial de Chiclayo', 'naturaleza' => 'publico', 'sector' => 'Gobierno local / Municipalidades',
            'contacto_email' => 'ti@munichiclayo.gob.pe', 'correos_alertas' => 'logistica@munichiclayo.gob.pe',
        ]);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Videovigilancia urbana', 'entidad_id' => $cliente->id, 'tipo' => 'Videovigilancia',
            'fecha_entrega' => today()->subYear(), 'estado' => 'operativo',
        ]);
        $this->garantia = Garantia::create([
            'proyecto_id' => $this->proyecto->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Cámaras y NVR',
            'fecha_inicio' => today()->subYear(), 'fecha_vencimiento' => today()->addYears(4),
        ]);
        $this->camara = Equipo::create([
            'entidad_id' => $cliente->id, 'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id,
            'categoria' => 'Cámara PTZ', 'marca' => 'Hikvision', 'serie' => 'PTZ-0012', 'estado' => 'operativo',
        ]);
    }

    private function datosIncidencia(array $cambios = []): array
    {
        return [
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $this->garantia->id, 'equipos' => [$this->camara->id],
            'titulo' => 'Cámara sin señal', 'descripcion' => 'No hay video en el cruce de Balta con Elías Aguirre',
            'prioridad' => 'alta', 'fecha_reporte' => now()->subHour()->format('Y-m-d H:i'), ...$cambios,
        ];
    }

    private function incidenciaAsignada(): Incidencia
    {
        $this->actingAs($this->supervisor);

        return app(IncidenciaService::class)->registrar([
            ...$this->datosIncidencia(), 'fecha_reporte' => now()->subHours(2), 'tecnico_id' => $this->tecnico->id,
        ]);
    }

    private function atender(Incidencia $i, array $cambios = [])
    {
        return $this->actingAs($this->tecnico)->post("/mis-incidencias/{$i->id}/atenciones", [
            'fecha_inicio' => now()->subHour()->format('Y-m-d H:i'),
            'diagnostico' => 'Conector RJ45 sulfatado', 'actividades' => 'Cambio de conector y pruebas de video', 'resultado' => 'resuelto',
            ...$cambios,
        ]);
    }

    /** CP25 – HU12 */
    public function test_cp25_registrar_incidencia_con_proyecto_garantia_y_equipo(): void
    {
        $this->actingAs($this->supervisor)->post('/incidencias', $this->datosIncidencia())->assertSessionHasNoErrors();

        $i = Incidencia::sole();
        $this->assertMatchesRegularExpression('/^INC-\d{4}-\d{5}$/', $i->codigo);
        $this->assertSame('registrada', $i->estado->value);
        $this->assertSame([$this->camara->id], $i->equipos->pluck('id')->all());
        $this->assertSame('con_falla', $this->camara->fresh()->estado);
    }

    /** CP26 – HU12 */
    public function test_cp26_no_se_registra_incidencia_sin_titulo_ni_descripcion(): void
    {
        $this->actingAs($this->supervisor)->post('/incidencias', $this->datosIncidencia(['titulo' => '', 'descripcion' => '']))
            ->assertSessionHasErrors(['titulo', 'descripcion']);
        $this->assertSame(0, Incidencia::count());
    }

    /** CP27 – HU13 */
    public function test_cp27_asignar_incidencia_a_un_tecnico_con_fecha_limite(): void
    {
        Notification::fake();
        $this->actingAs($this->supervisor)->post('/incidencias', $this->datosIncidencia());
        $i = Incidencia::sole();

        $this->post("/incidencias/{$i->id}/asignar", ['tecnico_id' => $this->tecnico->id, 'fecha_limite' => today()->addDays(2)->toDateString()])
            ->assertSessionHasNoErrors();

        $i->refresh();
        $this->assertSame('asignada', $i->estado->value);
        $this->assertSame($this->tecnico->id, $i->tecnico_id);
        $this->assertSame(today()->addDays(2)->toDateString(), $i->fecha_limite->toDateString());
        Notification::assertSentTo($this->tecnico, IncidenciaAsignada::class);
    }

    /** CP28 – HU13 */
    public function test_cp28_no_se_asigna_a_un_usuario_que_no_atiende_incidencias(): void
    {
        $this->actingAs($this->supervisor)->post('/incidencias', $this->datosIncidencia());
        $i = Incidencia::sole();

        $this->post("/incidencias/{$i->id}/asignar", ['tecnico_id' => $this->supervisor->id])->assertSessionHasErrors('tecnico_id');
        $this->assertNull($i->fresh()->tecnico_id);
    }

    /** CP29 – HU14 */
    public function test_cp29_el_tecnico_solo_ve_sus_incidencias_en_la_web(): void
    {
        $i = $this->incidenciaAsignada();
        $otro = User::factory()->create()->assignRole('Técnico');

        $this->actingAs($this->tecnico)->get('/mis-incidencias')->assertOk()->assertSee($i->codigo);
        $this->actingAs($otro)->get('/mis-incidencias')->assertOk()->assertDontSee($i->codigo);
        $this->actingAs($otro)->get("/mis-incidencias/{$i->id}")->assertForbidden();
    }

    /** CP30 – HU14 */
    public function test_cp30_la_app_movil_lista_las_incidencias_asignadas(): void
    {
        $i = $this->incidenciaAsignada();
        auth()->forgetUser();

        $token = $this->postJson('/api/login', ['email' => $this->tecnico->email, 'password' => 'password'])->assertOk()->json('token');
        $this->getJson('/api/incidencias', ['Authorization' => "Bearer $token"])->assertOk()
            ->assertJsonPath('data.0.codigo', $i->codigo)->assertJsonCount(1, 'data');
    }

    /** CP31 – HU15 */
    public function test_cp31_registrar_diagnostico_actividades_y_resultado(): void
    {
        $i = $this->incidenciaAsignada();

        $this->atender($i)->assertSessionHasNoErrors();

        $i->refresh();
        $this->assertSame('resuelta', $i->estado->value);
        $this->assertSame('Conector RJ45 sulfatado', $i->atenciones()->sole()->diagnostico);
        $this->assertSame('operativo', $this->camara->fresh()->estado);
    }

    /** CP32 – HU15 */
    public function test_cp32_la_atencion_exige_diagnostico(): void
    {
        $i = $this->incidenciaAsignada();

        $this->atender($i, ['diagnostico' => ''])->assertSessionHasErrors('diagnostico');
        $this->assertSame('asignada', $i->fresh()->estado->value);
    }

    /** CP33 – HU16 */
    public function test_cp33_subir_foto_de_evidencia_desde_la_app(): void
    {
        $i = $this->incidenciaAsignada();
        auth()->forgetUser();
        $token = $this->tecnico->createToken('app')->plainTextToken;

        $this->post("/api/incidencias/{$i->id}/archivos", [
            'archivo' => UploadedFile::fake()->image('camara.jpg', 1600, 1200), 'categoria' => 'foto', 'descripcion' => 'Conector antes del cambio',
        ], ['Authorization' => "Bearer $token", 'Accept' => 'application/json'])->assertCreated();

        $foto = $i->fresh()->todasLasEvidencias()->sole();
        Storage::disk('local')->assertExists($foto->ruta);
        $this->assertSame('Conector antes del cambio', $foto->descripcion);
    }

    /** CP34 – HU16 */
    public function test_cp34_no_se_cierra_sin_acta_de_conformidad(): void
    {
        $i = $this->incidenciaAsignada();
        $this->atender($i);

        $this->actingAs($this->supervisor)->post("/incidencias/{$i->id}/cerrar")->assertSessionHasErrors('estado');
        $this->assertSame('resuelta', $i->fresh()->estado->value);
    }

    /** CP35 – HU17 */
    public function test_cp35_el_historial_registra_cada_cambio_hasta_el_cierre(): void
    {
        $i = $this->incidenciaAsignada();
        $this->atender($i, ['archivos' => [UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf')], 'categorias' => ['acta_conformidad']]);

        $this->actingAs($this->supervisor)->post("/incidencias/{$i->id}/cerrar")->assertSessionHasNoErrors();

        $this->assertSame('cerrada', $i->fresh()->estado->value);
        $this->assertEqualsCanonicalizing(['registro', 'asignacion', 'atencion', 'cierre'], $i->historial()->pluck('accion')->unique()->values()->all());
        $this->actingAs($this->supervisor)->get("/incidencias/{$i->id}")->assertOk()->assertSee('Historial');
    }

    /** CP36 – HU18 */
    public function test_cp36_aviso_de_vencimiento_al_supervisor_y_al_cliente(): void
    {
        Notification::fake();
        $this->garantia->update(['fecha_vencimiento' => today()->addDays(30)]);

        $this->artisan('sggp:alertas')->assertSuccessful();

        Notification::assertSentTo($this->supervisor, AlertaGarantia::class);
        Notification::assertNotSentTo($this->tecnico, AlertaGarantia::class);
        Notification::assertSentOnDemand(AvisoVencimientoCliente::class,
            fn ($n, $c, $destino) => $destino->routes['mail'] === ['ti@munichiclayo.gob.pe', 'logistica@munichiclayo.gob.pe']);
    }

    /** CP37 – HU18 */
    public function test_cp37_el_mismo_aviso_no_se_repite(): void
    {
        $this->garantia->update(['fecha_vencimiento' => today()->addDays(30)]);
        Notification::fake();
        $this->artisan('sggp:alertas');

        Notification::fake();
        $this->artisan('sggp:alertas')->assertSuccessful();
        Notification::assertNothingSentTo($this->supervisor);
        Notification::assertSentOnDemandTimes(AvisoVencimientoCliente::class, 0);
    }
}
