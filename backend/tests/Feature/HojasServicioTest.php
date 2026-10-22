<?php

namespace Tests\Feature;

use App\Models\Entidad;
use App\Models\Garantia;
use App\Models\HojaServicio;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\IncidenciaService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HojasServicioTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;
    private User $tecnico;
    private Entidad $entidad;
    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->tecnico = User::factory()->create()->assignRole('Técnico');

        $this->entidad = Entidad::create(['nombre' => 'Municipalidad de Prueba', 'sector' => 'Gobierno local / Municipalidades', 'contacto_email' => 'ti@muni.gob.pe']);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Videovigilancia', 'entidad_id' => $this->entidad->id, 'tipo' => 'Videovigilancia',
            'fecha_entrega' => today()->subYear(), 'estado' => 'operativo',
        ]);
    }

    public function test_supervisor_registra_entidad(): void
    {
        $this->actingAs($this->supervisor)->post('/entidades', ['nombre' => 'Gobierno Regional', 'naturaleza' => 'publico', 'sector' => 'Gobierno regional', 'activo' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('entidades', ['nombre' => 'Gobierno Regional']);
    }

    public function test_tecnico_registra_hoja_firmada_desde_la_app_y_permite_cerrar_la_incidencia(): void
    {
        $garantia = Garantia::create([
            'proyecto_id' => $this->proyecto->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Equipos',
            'fecha_inicio' => today()->subYear(), 'fecha_vencimiento' => today()->addYears(4),
        ]);
        $this->actingAs($this->supervisor);
        $incidencia = app(IncidenciaService::class)->registrar([
            'proyecto_id' => $this->proyecto->id, 'garantia_id' => $garantia->id,
            'titulo' => 'Cámara sin video', 'descripcion' => 'Sin señal', 'prioridad' => 'alta',
            'fecha_reporte' => now()->subHours(3), 'tecnico_id' => $this->tecnico->id,
        ]);

        // La app usa solo el token: sin sesión web previa
        auth()->forgetUser();
        $token = $this->tecnico->createToken('app')->plainTextToken;
        $api = ['Authorization' => "Bearer $token", 'Accept' => 'application/json'];

        // La hoja de una incidencia exige diagnóstico y resultado (es el registro único de la visita)
        $this->post('/api/hojas', [
            'incidencia_id' => $incidencia->id, 'tipo_servicio' => 'correctivo', 'fecha' => today()->toDateString(),
            'hora_llegada' => '09:00', 'trabajo_realizado' => 'Cambio de fuente',
        ], $api)->assertUnprocessable()->assertJsonValidationErrors(['diagnostico', 'resultado']);
        $this->assertSame(0, HojaServicio::count());

        // Hoja de servicio con equipos, materiales, foto y el acta firmada (foto) desde la app
        $r = $this->post('/api/hojas', [
            'incidencia_id' => $incidencia->id,
            'tipo_servicio' => 'correctivo',
            'fecha' => today()->toDateString(),
            'hora_llegada' => '09:00',
            'hora_salida' => '10:30',
            'diagnostico' => 'Fuente de 12V dañada por sobretensión',
            'resultado' => 'resuelto',
            'trabajo_realizado' => 'Reemplazo de fuente de 12V',
            'equipos' => [['equipo' => 'Cámara PTZ', 'marca' => 'Hikvision', 'serie' => 'ABC123', 'accion' => 'reparado', 'estado_final' => 'operativo']],
            'materiales' => [['descripcion' => 'Fuente 12V 2A', 'cantidad' => 1, 'unidad' => 'und']],
            'fotos' => [UploadedFile::fake()->image('antes.jpg')],
            'fotos_descripcion' => ['Fuente dañada'],
            'actas' => [UploadedFile::fake()->image('acta-firmada.jpg')],
            'receptor_nombre' => 'Ing. María Távara',
            'receptor_dni' => '41234567',
        ], $api)->assertCreated()
            ->assertJsonPath('data.estado', 'conforme')
            ->assertJsonPath('data.tiene_acta', true);

        $hoja = HojaServicio::findOrFail($r->json('data.id'));
        $this->assertMatchesRegularExpression('/^HS-\d{4}-\d{5}$/', $hoja->numero);
        $this->assertSame($garantia->id, $hoja->garantia_id);
        $this->assertCount(1, $hoja->equipos);
        $this->assertCount(2, $hoja->archivos);
        $this->assertSame('Fuente dañada', $hoja->archivos->firstWhere('categoria.value', 'foto')->descripcion);
        $this->assertCount(1, $hoja->actas());

        // La hoja creó la atención técnica y dejó la incidencia resuelta (sin registrar nada aparte)
        $this->assertSame('resuelta', $incidencia->fresh()->estado->value);
        $this->assertNotNull($hoja->atencion_id);
        $this->assertSame('Fuente de 12V dañada por sobretensión', $hoja->atencion->diagnostico);
        $this->assertTrue($incidencia->fresh()->todasLasEvidencias()->contains('archivable_id', $hoja->id));

        // El acta subida en la hoja permite cerrar la incidencia
        $this->assertTrue($incidencia->fresh()->tieneActaConformidad());
        $this->actingAs($this->supervisor)->post("/incidencias/{$incidencia->id}/cerrar")->assertSessionHasNoErrors();
        $this->assertSame('cerrada', $incidencia->fresh()->estado->value);

        // PDF: con sesión, y por enlace firmado temporal (app); sin firma de URL, rechazado
        $this->actingAs($this->supervisor)->get("/hojas-servicio/{$hoja->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get($r->json('data.pdf_url'))->assertOk();
        $this->get("/hojas-servicio/{$hoja->id}/pdf-publico")->assertForbidden();
    }

    public function test_sin_acta_la_hoja_queda_pendiente_y_al_subirla_se_bloquea(): void
    {
        $this->actingAs($this->tecnico)->post('/hojas-servicio', [
            'proyecto_id' => $this->proyecto->id, 'tipo_servicio' => 'preventivo',
            'fecha' => today()->toDateString(), 'hora_llegada' => '08:00', 'hora_salida' => '09:00',
            'verificaciones' => [['item' => 'Cuenta con EPP', 'resultado' => 'ok'], ['item' => 'Zona señalizada', 'resultado' => 'na']],
            'mediciones' => [['parametro' => 'Tensión R-S', 'valor' => '380.9', 'unidad' => 'V'], ['parametro' => '', 'valor' => '']],
            'trabajo_realizado' => 'Limpieza y ajuste de cámaras',
        ])->assertRedirect();

        $hoja = HojaServicio::firstOrFail();
        $this->assertSame('registrada', $hoja->estado->value);
        $this->assertCount(2, $hoja->verificaciones);
        $this->assertSame([['parametro' => 'Tensión R-S', 'valor' => '380.9', 'unidad' => 'V']], $hoja->mediciones);
        $this->actingAs($this->tecnico)->get("/hojas-servicio/{$hoja->id}")->assertOk()->assertSee('Falta el acta de conformidad firmada');

        // El técnico sube el acta firmada (foto o PDF): la hoja queda conforme y ya no se edita
        $this->actingAs($this->tecnico)->post("/archivos/hoja_servicio/{$hoja->id}", [
            'categoria' => 'acta_conformidad', 'archivos' => [UploadedFile::fake()->create('acta.pdf', 120, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $this->assertSame('conforme', $hoja->fresh()->estado->value);
        $this->actingAs($this->tecnico)->get("/hojas-servicio/{$hoja->id}/edit")->assertForbidden();

        // Si el supervisor retira el acta equivocada, la hoja vuelve a quedar pendiente
        $acta = $hoja->archivos()->where('categoria', 'acta_conformidad')->firstOrFail();
        $this->actingAs($this->supervisor)->delete("/archivos/{$acta->id}")->assertSessionHasNoErrors();
        $this->assertSame('registrada', $hoja->fresh()->estado->value);
    }

    public function test_si_no_termina_queda_registrado_y_se_programa_otra_visita(): void
    {
        $this->actingAs($this->supervisor);
        $incidencia = app(IncidenciaService::class)->registrar([
            'proyecto_id' => $this->proyecto->id, 'titulo' => 'UPS no mantiene carga', 'descripcion' => 'Se apaga en cortes',
            'prioridad' => 'alta', 'fecha_reporte' => now()->subHours(5), 'tecnico_id' => $this->tecnico->id,
        ]);
        $base = [
            'incidencia_id' => $incidencia->id, 'tipo_servicio' => 'correctivo', 'fecha' => today()->toDateString(),
            'hora_llegada' => '08:00', 'diagnostico' => 'Baterías degradadas', 'trabajo_realizado' => 'Prueba de baterías',
        ];

        // Si no se terminó, son obligatorios lo pendiente y la fecha de la próxima visita
        $this->actingAs($this->tecnico)->post('/hojas-servicio', [...$base, 'resultado' => 'pendiente_repuesto'])
            ->assertSessionHasErrors(['pendientes', 'proxima_visita']);

        $this->actingAs($this->tecnico)->post('/hojas-servicio', [
            ...$base, 'resultado' => 'pendiente_repuesto', 'pendientes' => 'Cambiar 4 baterías', 'proxima_visita' => today()->addDays(2)->toDateString(),
        ])->assertSessionHasNoErrors();

        $incidencia->refresh();
        $this->assertSame('en_proceso', $incidencia->estado->value);
        $this->assertSame(today()->addDays(2)->toDateString(), $incidencia->proxima_visita->toDateString());
        $this->assertSame('Cambiar 4 baterías', $incidencia->motivo_reprogramacion);

        // El supervisor puede cambiar la fecha; se avisa al técnico
        \Illuminate\Support\Facades\Notification::fake();
        $this->actingAs($this->supervisor)->post("/incidencias/{$incidencia->id}/reprogramar", [
            'proxima_visita' => today()->addDays(4)->toDateString(), 'motivo_reprogramacion' => 'El cliente pidió el lunes',
        ])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Notification::assertSentTo($this->tecnico, \App\Notifications\VisitaReprogramada::class);

        // Segunda visita: el formulario parte de lo pendiente; al resolverse se limpia la próxima visita
        $this->actingAs($this->tecnico)->get("/hojas-servicio/create?incidencia_id={$incidencia->id}")->assertOk()->assertSee('Continuación de la visita');
        $this->actingAs($this->tecnico)->post('/hojas-servicio', [...$base, 'trabajo_realizado' => 'Cambio de 4 baterías', 'resultado' => 'resuelto'])
            ->assertSessionHasNoErrors();

        $incidencia->refresh();
        $this->assertSame('resuelta', $incidencia->estado->value);
        $this->assertNull($incidencia->proxima_visita);
        $this->assertSame(2, $incidencia->hojasServicio()->count());
        $this->assertSame(2, $incidencia->atenciones()->count());
        $this->actingAs($this->supervisor)->get("/incidencias/{$incidencia->id}")->assertSee('Visitas realizadas (2)');
    }

    public function test_tecnico_solo_ve_sus_hojas(): void
    {
        $otro = User::factory()->create()->assignRole('Técnico');
        $this->actingAs($otro)->post('/hojas-servicio', [
            'proyecto_id' => $this->proyecto->id, 'tipo_servicio' => 'inspeccion', 'fecha' => today()->toDateString(),
            'hora_llegada' => '08:00', 'trabajo_realizado' => 'Inspección',
        ]);
        $hoja = HojaServicio::firstOrFail();

        $this->actingAs($this->tecnico)->get("/hojas-servicio/{$hoja->id}")->assertForbidden();
        $this->actingAs($this->tecnico)->get('/hojas-servicio')->assertOk()->assertDontSee($hoja->numero);
        $this->actingAs($this->supervisor)->get('/hojas-servicio')->assertOk()->assertSee($hoja->numero);
    }
}
