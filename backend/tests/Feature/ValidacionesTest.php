<?php

namespace Tests\Feature;

use App\Models\Entidad;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Validaciones de formato y mensajes en español en los formularios principales. */
class ValidacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        $this->admin = User::factory()->create()->assignRole('Administrador');
    }

    public function test_ruc_y_telefono_del_cliente(): void
    {
        $base = ['nombre' => 'Cliente', 'naturaleza' => 'privado', 'sector' => 'Minería'];

        $this->actingAs($this->admin)->post('/entidades', [...$base, 'ruc' => '12345678901', 'contacto_telefono' => 'abc'])
            ->assertSessionHasErrors([
                'ruc' => 'El RUC debe tener 11 dígitos y empezar en 10, 15, 16, 17 o 20.',
                'contacto_telefono' => 'Ingrese un teléfono válido (solo números, espacios, + o guiones).',
            ]);

        $this->actingAs($this->admin)->post('/entidades', [...$base, 'ruc' => '20601234567', 'contacto_telefono' => '+51 974 123-456'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/entidades', [...$base, 'nombre' => 'Otro', 'ruc' => '20601234567'])
            ->assertSessionHasErrors(['ruc' => 'Ya existe un cliente con ese RUC.']);
    }

    public function test_campos_obligatorios_se_nombran_en_espanol(): void
    {
        $this->actingAs($this->admin)->post('/entidades', [])
            ->assertSessionHasErrors(['naturaleza' => 'El campo tipo de cliente es obligatorio.']);

        $cliente = Entidad::create(['nombre' => 'C', 'sector' => 'Salud']);
        $this->actingAs($this->admin)->post('/proyectos', [
            'nombre' => 'P', 'clase' => 'implementacion', 'entidad_id' => $cliente->id, 'tipo' => 'Videovigilancia',
            'fecha_entrega' => today()->toDateString(), 'estado' => 'operativo', 'monto_contrato' => '10.555', 'orden_compra' => 'OC<script>',
        ])->assertSessionHasErrors([
            'monto_contrato' => 'El monto puede tener como máximo 2 decimales.',
            'orden_compra' => 'La orden de compra solo puede tener letras, números, espacios y - / . #',
        ]);
        $this->assertSame(0, Proyecto::count());
    }

    public function test_dni_del_usuario(): void
    {
        $this->actingAs($this->admin)->post('/admin/usuarios', [
            'name' => 'Técnico Nuevo', 'email' => 'nuevo@techquk.com.pe', 'dni' => '1234', 'rol' => 'Técnico',
            'password' => 'Clave2026', 'password_confirmation' => 'Clave2026',
        ])->assertSessionHasErrors(['dni' => 'El DNI debe tener 8 dígitos.']);
    }

    public function test_las_pantallas_usan_la_ventana_de_confirmacion_propia(): void
    {
        $cliente = Entidad::create(['nombre' => 'Cliente sin proyectos', 'sector' => 'Salud']);

        $this->actingAs($this->admin)->get("/entidades/{$cliente->id}")->assertOk()
            ->assertSee('data-confirmar="¿Eliminar el cliente?"', false)
            ->assertDontSee('return confirm(', false);
    }
}
