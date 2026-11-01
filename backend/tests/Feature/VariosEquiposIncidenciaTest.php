<?php

namespace Tests\Feature;

use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\EquipoEvento;
use App\Models\Garantia;
use App\Models\HojaServicio;
use App\Models\Incidencia;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proyecto con 10 equipos instalados; un reporte del cliente afecta a 3. Cada equipo lleva su
 * diagnóstico y resultado en la visita, y su informe técnico. Si unos quedan reparados y otros
 * pendientes, los pendientes pasan a una incidencia nueva.
 */
class VariosEquiposIncidenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;
    private User $tecnico;
    private Proyecto $proyecto;
    private $camaras;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->tecnico = User::factory()->create()->assignRole('Técnico');
        $cliente = Entidad::create(['nombre' => 'Municipalidad de Chiclayo', 'naturaleza' => 'publico', 'sector' => 'Gobierno local / Municipalidades']);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Videovigilancia Chiclayo', 'clase' => 'implementacion', 'entidad_id' => $cliente->id, 'tipo' => 'Videovigilancia',
            'fecha_entrega' => today()->subMonths(6), 'estado' => 'operativo', 'responsable_id' => $this->supervisor->id,
        ]);
        Garantia::create([
            'proyecto_id' => $this->proyecto->id, 'tipo' => 'Garantía de equipos', 'descripcion' => 'Garantía de equipos',
            'fecha_inicio' => today()->subMonths(6), 'duracion_meses' => 60, 'fecha_vencimiento' => today()->subMonths(6)->addYears(5),
        ]);

        $this->actingAs($this->supervisor)->post("/proyectos/{$this->proyecto->id}/equipos", [
            'equipos' => [['categoria' => 'Cámara IP', 'marca' => 'Hikvision', 'cantidad' => 10]],
        ])->assertSessionHasNoErrors();
        $this->camaras = Equipo::orderBy('codigo')->get();
        $this->assertCount(10, $this->camaras);
    }

    /** Registra la incidencia con 3 cámaras y la asigna al técnico. */
    private function incidenciaConTresCamaras(): Incidencia
    {
        $this->actingAs($this->supervisor)->post('/incidencias', [
            'proyecto_id' => $this->proyecto->id, 'equipos' => $this->camaras->take(3)->pluck('id')->all(), 'cobertura' => 'garantia',
            'titulo' => 'Tres cámaras sin señal en Av. Balta', 'descripcion' => 'El centro de monitoreo no recibe imagen de 3 cámaras.',
            'prioridad' => 'alta', 'fecha_reporte' => now()->subHours(2)->format('Y-m-d H:i'), 'tecnico_id' => $this->tecnico->id,
        ])->assertSessionHasNoErrors();

        return Incidencia::sole();
    }

    private function filaEquipo(Equipo $e, string $resultado, array $extra = []): array
    {
        return ['equipo_id' => $e->id, 'equipo' => 'Cámara IP', 'accion' => 'reparado', 'estado_final' => 'operativo',
            'diagnostico' => "Falla en {$e->codigo}", 'resultado' => $resultado, ...$extra];
    }

    private function hoja(Incidencia $inc, array $equipos, array $extra = [])
    {
        return $this->actingAs($this->tecnico)->post('/hojas-servicio', [
            'incidencia_id' => $inc->id, 'tipo_servicio' => 'correctivo', 'fecha' => today()->toDateString(),
            'hora_llegada' => '08:00', 'hora_salida' => '11:00', 'trabajo_realizado' => 'Revisión de las cámaras del cruce', 'equipos' => $equipos, ...$extra,
        ]);
    }

    public function test_una_incidencia_registra_varios_equipos_con_falla(): void
    {
        $inc = $this->incidenciaConTresCamaras();

        $this->assertSame($this->camaras->take(3)->pluck('id')->all(), $inc->equipos->pluck('id')->all());
        foreach ($this->camaras->take(3) as $camara) {
            $this->assertSame('con_falla', $camara->fresh()->estado);
            $this->assertDatabaseHas('equipo_eventos', ['equipo_id' => $camara->id, 'incidencia_id' => $inc->id, 'tipo' => 'falla']);
        }
        $this->assertSame('operativo', $this->camaras[3]->fresh()->estado);

        // Un equipo de otro cliente no se acepta
        $ajeno = Equipo::create(['entidad_id' => Entidad::create(['nombre' => 'Otro', 'naturaleza' => 'privado', 'sector' => 'Salud'])->id,
            'categoria' => 'PC', 'estado' => 'operativo']);
        $this->actingAs($this->supervisor)->put("/incidencias/{$inc->id}", [
            'proyecto_id' => $this->proyecto->id, 'equipos' => [$ajeno->id], 'titulo' => 'x', 'descripcion' => 'x', 'prioridad' => 'alta',
            'fecha_reporte' => now()->subHour()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('equipos.0');

        // Al editar se agrega una cuarta cámara: su falla queda en su historial
        $this->actingAs($this->supervisor)->put("/incidencias/{$inc->id}", [
            'proyecto_id' => $this->proyecto->id, 'equipos' => $this->camaras->take(4)->pluck('id')->all(), 'titulo' => $inc->titulo,
            'descripcion' => $inc->descripcion, 'prioridad' => 'alta', 'fecha_reporte' => $inc->fecha_reporte->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();
        $this->assertCount(4, $inc->fresh()->equipos);
        $this->assertSame(1, EquipoEvento::where('equipo_id', $this->camaras[3]->id)->where('tipo', 'falla')->count());
    }

    public function test_cada_equipo_necesita_su_diagnostico_y_resultado(): void
    {
        $inc = $this->incidenciaConTresCamaras();
        [$a, $b] = $this->camaras;

        $this->hoja($inc, [$this->filaEquipo($a, 'resuelto'), $this->filaEquipo($b, 'resuelto', ['diagnostico' => ''])])
            ->assertSessionHasErrors('equipos');
        $this->assertSame(0, HojaServicio::count());
    }

    public function test_reparados_y_pendientes_se_separan_en_otra_incidencia(): void
    {
        $inc = $this->incidenciaConTresCamaras();
        [$a, $b, $c] = $this->camaras;

        $this->hoja($inc, [
            $this->filaEquipo($a, 'resuelto', ['fotos' => [UploadedFile::fake()->image('camara-a.jpg')]]),
            $this->filaEquipo($b, 'resuelto'),
            $this->filaEquipo($c, 'pendiente_repuesto', ['estado_final' => 'inoperativo']),
        ], ['pendientes' => 'Cambiar la fuente de poder de la cámara', 'proxima_visita' => today()->addDays(3)->toDateString()])
            ->assertSessionHasNoErrors();

        // La incidencia original queda resuelta con las 2 cámaras reparadas
        $inc->refresh();
        $this->assertSame('resuelta', $inc->estado->value);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $inc->equipos->pluck('id')->all());
        $this->assertSame('operativo', $a->fresh()->estado);

        // La cámara pendiente pasa a una incidencia nueva, asignada al mismo técnico y con la próxima visita
        $nueva = Incidencia::where('incidencia_origen_id', $inc->id)->sole();
        $this->assertSame([$c->id], $nueva->equipos->pluck('id')->all());
        $this->assertSame('asignada', $nueva->estado->value);
        $this->assertSame($this->tecnico->id, $nueva->tecnico_id);
        $this->assertSame(today()->addDays(3)->toDateString(), $nueva->proxima_visita->toDateString());
        $this->assertSame('Cambiar la fuente de poder de la cámara', $nueva->motivo_reprogramacion);
        // Su falla se mueve a la incidencia nueva (no se duplica)
        $this->assertSame(1, EquipoEvento::where('equipo_id', $c->id)->where('tipo', 'falla')->count());
        $this->assertDatabaseHas('equipo_eventos', ['equipo_id' => $c->id, 'tipo' => 'falla', 'incidencia_id' => $nueva->id]);

        // Foto del equipo marcada con el equipo
        $this->assertDatabaseHas('archivos', ['equipo_id' => $a->id, 'categoria' => 'foto']);

        // Informe técnico por equipo (uno y todos) y del equipo pendiente en la incidencia nueva
        $this->actingAs($this->supervisor)->get("/incidencias/{$inc->id}/equipos/{$a->id}/informe")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->supervisor)->get("/incidencias/{$inc->id}/informes-equipos")->assertOk();
        $this->actingAs($this->supervisor)->get("/incidencias/{$inc->id}/equipos/{$c->id}/informe")->assertNotFound();
        $this->actingAs($this->supervisor)->get("/incidencias/{$nueva->id}/equipos/{$c->id}/informe")->assertOk();
        $this->actingAs($this->supervisor)->get("/incidencias/{$inc->id}")->assertOk()->assertSee($nueva->codigo)->assertSee('Todos los informes');
    }

    public function test_si_ningun_equipo_se_termina_la_incidencia_continua(): void
    {
        $inc = $this->incidenciaConTresCamaras();
        $filas = $this->camaras->take(3)->map(fn ($e) => $this->filaEquipo($e, 'parcial'))->all();

        $this->hoja($inc, $filas, ['pendientes' => 'Falta configurar el NVR', 'proxima_visita' => today()->addDay()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame('en_proceso', $inc->fresh()->estado->value);
        $this->assertCount(3, $inc->fresh()->equipos);
        $this->assertSame(0, Incidencia::whereNotNull('incidencia_origen_id')->count());
    }

    public function test_la_app_recibe_los_equipos_y_registra_su_resultado(): void
    {
        $inc = $this->incidenciaConTresCamaras();
        $token = $this->tecnico->createToken('app')->plainTextToken;
        $cabecera = ['Authorization' => "Bearer $token"];

        $this->getJson("/api/incidencias/{$inc->id}", $cabecera)->assertOk()->assertJsonCount(3, 'data.equipos');

        $this->postJson('/api/hojas', [
            'incidencia_id' => $inc->id, 'tipo_servicio' => 'correctivo', 'fecha' => today()->toDateString(), 'hora_llegada' => '09:00',
            'trabajo_realizado' => 'Revisión', 'equipos' => $this->camaras->take(3)->map(fn ($e) => $this->filaEquipo($e, 'resuelto'))->all(),
        ], $cabecera)->assertCreated()->assertJsonPath('data.equipos.0.resultado', 'resuelto');

        $this->assertSame('resuelta', $inc->fresh()->estado->value);
    }

    public function test_reenvio_desde_la_app_sin_senal_no_duplica_la_hoja(): void
    {
        $inc = $this->incidenciaConTresCamaras();
        $cabecera = ['Authorization' => 'Bearer '.$this->tecnico->createToken('app')->plainTextToken];
        $hoja = [
            'uuid_cliente' => '1730390000-abc123', 'incidencia_id' => $inc->id, 'tipo_servicio' => 'correctivo', 'fecha' => today()->toDateString(),
            'hora_llegada' => '09:00', 'trabajo_realizado' => 'Revisión',
            'equipos' => $this->camaras->take(3)->map(fn ($e) => $this->filaEquipo($e, 'resuelto'))->all(),
        ];

        $primera = $this->postJson('/api/hojas', $hoja, $cabecera)->assertCreated()->json('data.id');
        // La señal se cortó antes de la respuesta: la app reenvía la misma hoja
        $this->postJson('/api/hojas', $hoja, $cabecera)->assertCreated()->assertJsonPath('data.id', $primera);

        $this->assertSame(1, HojaServicio::count());
        $this->assertSame(1, $inc->fresh()->atenciones()->count());
    }
}
