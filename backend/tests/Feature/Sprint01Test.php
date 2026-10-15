<?php

namespace Tests\Feature;

use App\Models\Entidad;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Casos de prueba del Sprint 01: inicio de sesión, usuarios, roles y clientes (HU01–HU05). */
class Sprint01Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $supervisor;
    private User $tecnico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        $this->admin = User::factory()->create(['email' => 'admin@prueba.pe', 'password' => 'Clave2026*'])->assignRole('Administrador');
        $this->supervisor = User::factory()->create(['email' => 'super@prueba.pe', 'password' => 'Clave2026*'])->assignRole('Supervisor');
        $this->tecnico = User::factory()->create(['email' => 'tec@prueba.pe', 'password' => 'Clave2026*'])->assignRole('Técnico');
    }

    /** CP01 – HU01 */
    public function test_cp01_login_con_credenciales_validas_lleva_al_panel_del_rol(): void
    {
        $this->post('/login', ['email' => 'super@prueba.pe', 'password' => 'Clave2026*'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->supervisor);
        $this->get('/')->assertRedirect('/dashboard');
    }

    /** CP02 – HU01 */
    public function test_cp02_login_con_contrasena_incorrecta_muestra_mensaje_generico(): void
    {
        $this->from('/login')->post('/login', ['email' => 'super@prueba.pe', 'password' => 'otra'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** CP03 – HU01 */
    public function test_cp03_demasiados_intentos_fallidos_se_bloquean_temporalmente(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'super@prueba.pe', 'password' => 'mal']);
        }
        $this->post('/login', ['email' => 'super@prueba.pe', 'password' => 'Clave2026*'])->assertStatus(429);
        $this->assertGuest();
    }

    /** CP04 – HU01 */
    public function test_cp04_la_contrasena_se_guarda_cifrada(): void
    {
        $guardada = $this->supervisor->fresh()->getAuthPassword();
        $this->assertNotSame('Clave2026*', $guardada);
        $this->assertTrue(Hash::check('Clave2026*', $guardada));
    }

    /** CP05 – HU02 */
    public function test_cp05_no_se_registran_dos_usuarios_con_el_mismo_correo(): void
    {
        $this->actingAs($this->admin)->post('/admin/usuarios', [
            'name' => 'Duplicado', 'email' => 'tec@prueba.pe', 'password' => 'Clave2026*', 'password_confirmation' => 'Clave2026*', 'rol' => 'Técnico',
        ])->assertSessionHasErrors('email');
    }

    /** CP06 – HU02 */
    public function test_cp06_la_contrasena_debe_tener_8_caracteres_con_letras_y_numeros(): void
    {
        $this->actingAs($this->admin)->post('/admin/usuarios', [
            'name' => 'Nuevo', 'email' => 'nuevo@prueba.pe', 'password' => 'abc123', 'password_confirmation' => 'abc123', 'rol' => 'Técnico',
        ])->assertSessionHasErrors('password');
    }

    /** CP07 – HU02 */
    public function test_cp07_usuario_desactivado_no_puede_ingresar(): void
    {
        $this->actingAs($this->admin)->patch("/admin/usuarios/{$this->tecnico->id}/estado");
        auth()->logout();
        $this->assertFalse($this->tecnico->fresh()->activo);
        $this->post('/login', ['email' => 'tec@prueba.pe', 'password' => 'Clave2026*']);
        $this->assertGuest();
    }

    /** CP08 – HU03 */
    public function test_cp08_el_tecnico_no_accede_a_modulos_de_administracion(): void
    {
        $this->actingAs($this->tecnico)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($this->tecnico)->get('/entidades')->assertForbidden();
        $this->actingAs($this->supervisor)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/usuarios')->assertOk();
    }

    /** CP09 – HU04 */
    public function test_cp09_ruc_invalido_no_se_registra(): void
    {
        $this->actingAs($this->supervisor)->post('/entidades', [
            'nombre' => 'Cliente', 'ruc' => '2048791379', 'naturaleza' => 'privado', 'sector' => 'Minería', 'activo' => 1,
        ])->assertSessionHasErrors('ruc');
        $this->assertSame(0, Entidad::count());
    }

    /** CP10 – HU04 */
    public function test_cp10_registrar_cliente_privado_valido(): void
    {
        $this->actingAs($this->supervisor)->post('/entidades', [
            'nombre' => 'Compañía Minera Lambayeque S.A.C.', 'ruc' => '20601234567', 'naturaleza' => 'privado', 'sector' => 'Minería', 'activo' => 1,
        ])->assertSessionHasNoErrors()->assertSessionHas('ok');
        $this->assertDatabaseHas('entidades', ['ruc' => '20601234567', 'naturaleza' => 'privado']);
    }

    /** CP11 – HU04 */
    public function test_cp11_ruc_duplicado_no_se_registra(): void
    {
        Entidad::create(['nombre' => 'Primero', 'ruc' => '20601234567', 'naturaleza' => 'privado', 'sector' => 'Minería']);
        $this->actingAs($this->supervisor)->post('/entidades', [
            'nombre' => 'Segundo', 'ruc' => '20601234567', 'naturaleza' => 'privado', 'sector' => 'Minería', 'activo' => 1,
        ])->assertSessionHasErrors('ruc');
    }

    /** CP12 – HU05 */
    public function test_cp12_filtrar_clientes_por_tipo_y_buscar_por_ruc(): void
    {
        Entidad::create(['nombre' => 'Municipalidad Provincial de Chiclayo', 'ruc' => '20175639391', 'naturaleza' => 'publico', 'sector' => 'Gobierno local / Municipalidades']);
        Entidad::create(['nombre' => 'Clínica San Juan', 'ruc' => '20488765432', 'naturaleza' => 'privado', 'sector' => 'Salud']);

        $this->actingAs($this->supervisor)->get('/entidades?naturaleza=publico')
            ->assertOk()->assertSee('Municipalidad Provincial de Chiclayo')->assertDontSee('Clínica San Juan');
        $this->actingAs($this->supervisor)->get('/entidades?buscar=20488765432')
            ->assertOk()->assertSee('Clínica San Juan')->assertDontSee('Municipalidad Provincial de Chiclayo');
    }
}
