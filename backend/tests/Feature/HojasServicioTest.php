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

    /** Firma dibujada en la app: trazos en coordenadas del pad. */
    private string $firmaTrazos = '{"w":300,"h":120,"trazos":[[[10,60],[50,20],[90,90],[130,30],[170,80]]]}';

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

        // Hoja de servicio con equipos, materiales, foto y firma dibujada en la app
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
            'receptor_nombre' => 'Ing. María Távara',
            'receptor_dni' => '41234567',
            'firma_receptor' => $this->firmaTrazos,
            'firma_tecnico' => $this->firmaTrazos,
            'conforme' => '1',
        ], $api)->assertCreated()
            ->assertJsonPath('data.estado', 'firmada');

        $hoja = HojaServicio::findOrFail($r->json('data.id'));
        $this->assertMatchesRegularExpression('/^HS-\d{4}-\d{5}$/', $hoja->numero);
        $this->assertSame($garantia->id, $hoja->garantia_id);
        $this->assertCount(1, $hoja->equipos);
        $this->assertCount(1, $hoja->archivos);
        Storage::disk('local')->assertExists($hoja->firma_receptor);

        // La hoja creó la atención técnica y dejó la incidencia resuelta (sin registrar nada aparte)
        $this->assertSame('resuelta', $incidencia->fresh()->estado->value);
        $this->assertNotNull($hoja->atencion_id);
        $this->assertSame('Fuente de 12V dañada por sobretensión', $hoja->atencion->diagnostico);
        $this->assertTrue($incidencia->fresh()->todasLasEvidencias()->contains('archivable_id', $hoja->id));

        // La hoja firmada con conformidad reemplaza al acta escaneada
        $this->assertTrue($incidencia->fresh()->tieneActaConformidad());
        $this->actingAs($this->supervisor)->post("/incidencias/{$incidencia->id}/cerrar")->assertSessionHasNoErrors();
        $this->assertSame('cerrada', $incidencia->fresh()->estado->value);

        // PDF: con sesión, y por enlace firmado temporal (app); sin firma de URL, rechazado
        $this->actingAs($this->supervisor)->get("/hojas-servicio/{$hoja->id}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get($r->json('data.pdf_url'))->assertOk();
        $this->get("/hojas-servicio/{$hoja->id}/pdf-publico")->assertForbidden();
    }

    public function test_firma_vacia_no_crea_hoja(): void
    {
        $token = $this->tecnico->createToken('app')->plainTextToken;

        $this->post('/api/hojas', [
            'proyecto_id' => $this->proyecto->id, 'tipo_servicio' => 'preventivo', 'fecha' => today()->toDateString(),
            'hora_llegada' => '08:00', 'trabajo_realizado' => 'Mantenimiento preventivo',
            'receptor_nombre' => 'Juan', 'firma_receptor' => '{"w":300,"h":120,"trazos":[]}',
        ], ['Authorization' => "Bearer $token", 'Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('firma_receptor');

        $this->assertSame(0, HojaServicio::count());
    }

    public function test_hoja_pendiente_se_firma_despues_desde_la_web_y_queda_bloqueada(): void
    {
        $this->actingAs($this->tecnico)->post('/hojas-servicio', [
            'proyecto_id' => $this->proyecto->id, 'tipo_servicio' => 'preventivo',
            'fecha' => today()->toDateString(), 'hora_llegada' => '08:00', 'hora_salida' => '09:00',
            'trabajo_realizado' => 'Limpieza y ajuste de cámaras',
        ])->assertRedirect();

        $hoja = HojaServicio::firstOrFail();
        $this->assertSame('pendiente_firma', $hoja->estado->value);

        // Firma desde el navegador (canvas → PNG en base64)
        $png = 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('f.png', 300, 100)->getContent());
        $this->actingAs($this->tecnico)->post("/hojas-servicio/{$hoja->id}/firmar", [
            'receptor_nombre' => 'Jefe de Seguridad', 'firma_receptor' => $png, 'conforme' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('firmada', $hoja->fresh()->estado->value);
        $this->actingAs($this->tecnico)->get("/hojas-servicio/{$hoja->id}/edit")->assertForbidden();
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
