<?php

namespace Tests\Feature;

use App\Models\AlmacenArticulo;
use App\Models\Checklist;
use App\Models\Cotizacion;
use App\Models\Entidad;
use App\Models\Equipo;
use App\Models\HojaServicio;
use App\Models\Proyecto;
use App\Models\User;
use App\Notifications\InformeCliente;
use App\Notifications\StockBajo;
use App\Services\HojaServicioService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Almacén (descuento por hoja), checklist de mantenimiento, informe al cliente y cotización aprobada → trabajo. */
class AlmacenChecklistInformeTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;
    private User $tecnico;
    private Proyecto $servicio;
    private Equipo $laptop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

        $this->supervisor = User::factory()->create()->assignRole('Supervisor');
        $this->tecnico = User::factory()->create()->assignRole('Técnico');
        $cliente = Entidad::create(['nombre' => 'Clínica SAC', 'naturaleza' => 'privado', 'sector' => 'Salud', 'contacto_email' => 'ti@clinica.pe']);
        $this->servicio = Proyecto::create([
            'nombre' => 'Mantenimiento de laptops', 'clase' => 'servicio', 'entidad_id' => $cliente->id, 'tipo' => 'Mantenimiento preventivo',
            'fecha_entrega' => today()->subMonth(), 'fecha_fin' => today()->addYear(), 'estado' => 'operativo',
        ]);
        $this->laptop = Equipo::create(['entidad_id' => $cliente->id, 'proyecto_id' => $this->servicio->id, 'categoria' => 'Laptop', 'serie' => 'LP-1']);
    }

    private function hoja(array $materiales, array $equipos = []): HojaServicio
    {
        $this->actingAs($this->tecnico);

        return app(HojaServicioService::class)->crear([
            'proyecto_id' => $this->servicio->id, 'tipo_servicio' => 'preventivo', 'fecha' => today()->toDateString(),
            'hora_llegada' => '09:00', 'trabajo_realizado' => 'Mantenimiento preventivo',
        ], $equipos, $materiales);
    }

    public function test_la_hoja_descuenta_del_almacen_y_al_anularla_se_devuelve(): void
    {
        $this->actingAs($this->supervisor)->post('/almacen', [
            'tipo' => 'consumible', 'nombre' => 'Pasta térmica', 'unidad' => 'tubo', 'stock_minimo' => 2, 'costo_unitario' => 15, 'stock_inicial' => 5,
        ])->assertSessionHasNoErrors();
        $pasta = AlmacenArticulo::firstOrFail();
        $this->assertSame('CON-0001', $pasta->codigo);
        $this->assertSame(5.0, $pasta->stock);

        // Más de lo que hay: no se guarda la hoja
        $this->expectsStockInsuficiente(fn () => $this->hoja([['articulo_id' => $pasta->id, 'cantidad' => 9]]));
        $this->assertSame(0, HojaServicio::count());
        $this->assertSame(5.0, $pasta->fresh()->stock);

        $hoja = $this->hoja([['articulo_id' => $pasta->id, 'cantidad' => 3], ['descripcion' => 'Cinta aislante', 'cantidad' => 1]]);
        $this->assertSame(2.0, $pasta->fresh()->stock);
        $this->assertSame('Pasta térmica', $hoja->materiales()->whereNotNull('articulo_id')->value('descripcion'));

        // Llegó al mínimo: alerta diaria a los responsables del almacén
        Notification::fake();
        $this->artisan('sggp:alertas')->assertSuccessful();
        Notification::assertSentTo($this->supervisor, StockBajo::class);
        Notification::assertNotSentTo($this->tecnico, StockBajo::class);

        // Editar la hoja vuelve a calcular el descuento; anularla devuelve el stock
        $servicio = app(HojaServicioService::class);
        $servicio->actualizar($hoja, $hoja->only(['proyecto_id', 'tipo_servicio', 'fecha', 'hora_llegada', 'trabajo_realizado']), [], [['articulo_id' => $pasta->id, 'cantidad' => 1]]);
        $this->assertSame(4.0, $pasta->fresh()->stock);
        $servicio->anular($hoja->fresh(), 'Error');
        $this->assertSame(5.0, $pasta->fresh()->stock);

        // Ajuste por conteo físico
        $this->actingAs($this->supervisor)->post("/almacen/{$pasta->id}/movimientos", ['tipo' => 'ajuste', 'stock_contado' => 4, 'motivo' => 'Conteo'])
            ->assertSessionHasNoErrors();
        $this->assertSame(4.0, $pasta->fresh()->stock);
        $this->assertSame([4.0, -1.0], [$pasta->movimientos()->first()->stock_resultante, $pasta->movimientos()->first()->variacion()]);
        $this->actingAs($this->tecnico)->post("/almacen/{$pasta->id}/movimientos", ['tipo' => 'entrada', 'cantidad' => 1])->assertForbidden();
    }

    private function expectsStockInsuficiente(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Debía rechazar la salida por stock insuficiente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Stock insuficiente', $e->errors()['materiales'][0]);
        }
    }

    public function test_checklist_observado_queda_en_el_historial_y_el_equipo_observado(): void
    {
        Checklist::create(['categoria' => 'Laptop', 'items' => "Limpieza interna\nEstado del disco (SMART)\nBatería"]);
        $this->assertSame(['Limpieza interna', 'Estado del disco (SMART)', 'Batería'], Checklist::para('laptop'));

        $this->hoja([], [[
            'equipo_id' => $this->laptop->id, 'accion' => 'revisado', 'estado_final' => 'operativo',
            'checklist' => [
                ['item' => 'Limpieza interna', 'resultado' => 'ok'],
                ['item' => 'Estado del disco (SMART)', 'resultado' => 'observado', 'nota' => '12 sectores reasignados'],
                ['item' => 'Batería', 'resultado' => ''],
            ],
        ]]);

        $fila = HojaServicio::firstOrFail()->equipos()->firstOrFail();
        $this->assertCount(2, $fila->checklist);
        $this->assertSame('observado', $this->laptop->fresh()->estado);
        $evento = $this->laptop->eventos()->where('tipo', 'mantenimiento')->firstOrFail();
        $this->assertStringContainsString('Estado del disco (SMART): observado (12 sectores reasignados)', $evento->detalle);

        $this->actingAs($this->supervisor)->get('/checklists')->assertOk()->assertSee('Estado del disco (SMART)');
        $this->actingAs($this->supervisor)->put('/checklists', ['checklists' => ['Laptop' => "Limpieza\nTeclado", 'UPS' => '']])->assertSessionHasNoErrors();
        $this->assertSame(['Limpieza', 'Teclado'], Checklist::para('Laptop'));
    }

    public function test_informe_mensual_al_cliente(): void
    {
        $this->hoja([['descripcion' => 'Limpia contactos', 'cantidad' => 1]], [['equipo_id' => $this->laptop->id, 'accion' => 'revisado', 'estado_final' => 'operativo']]);

        $this->actingAs($this->supervisor)->get("/proyectos/{$this->servicio->id}/informe?mes=".now()->format('Y-m'))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        Notification::fake();
        $this->actingAs($this->supervisor)->post("/proyectos/{$this->servicio->id}/informe/enviar", ['mes' => now()->format('Y-m')])->assertSessionHasNoErrors();
        Notification::assertSentOnDemand(InformeCliente::class, fn ($n, $c, $destino) => $destino->routes['mail'] === ['ti@clinica.pe']);

        // Envío automático del día 1: solo proyectos con el informe mensual activado
        Notification::fake();
        $this->artisan('sggp:informes')->assertSuccessful();
        Notification::assertNothingSent();
        $this->servicio->update(['informe_mensual' => true]);
        $this->artisan('sggp:informes')->assertSuccessful();
        Notification::assertSentOnDemandTimes(InformeCliente::class, 1);
    }

    public function test_cotizacion_aprobada_se_convierte_en_orden_de_trabajo(): void
    {
        $c = Cotizacion::create([
            'entidad_id' => $this->servicio->entidad_id, 'proyecto_id' => $this->servicio->id, 'equipo_id' => $this->laptop->id,
            'fecha' => today(), 'asunto' => 'Cambio de pantalla', 'estado' => 'aprobada', 'user_id' => $this->supervisor->id,
        ]);

        $this->actingAs($this->supervisor)->get("/cotizaciones/{$c->id}")->assertSee('Crear orden de trabajo');
        $this->actingAs($this->supervisor)->get("/incidencias/create?cotizacion_id={$c->id}")->assertOk()->assertSee('Trabajo cotizado: Cambio de pantalla', false);

        $this->actingAs($this->supervisor)->post('/incidencias', [
            'cotizacion_id' => $c->id, 'proyecto_id' => $this->servicio->id, 'equipo_id' => $this->laptop->id, 'cobertura' => 'sin_cobertura',
            'titulo' => 'Trabajo cotizado: Cambio de pantalla', 'descripcion' => 'Aprobado', 'prioridad' => 'media',
            'fecha_reporte' => now()->subMinute()->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($c->fresh()->incidencia_id);
        $this->actingAs($this->supervisor)->get("/cotizaciones/{$c->id}")->assertSee('Continuar la incidencia');
    }

    public function test_el_tecnico_tiene_un_solo_menu_de_incidencias(): void
    {
        $this->actingAs($this->tecnico)->get('/mis-incidencias')->assertOk()
            ->assertSee(route('mis-incidencias.index'), false)
            ->assertDontSee('Almacén');
    }
}
