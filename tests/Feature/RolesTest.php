<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Cliente;
use App\Models\Onu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RolesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        config(['smartolt.subdomain' => 'demo', 'smartolt.token' => 't', 'queue.default' => 'sync']);
        Http::fake(['demo.smartolt.com/*' => Http::response(['status' => true, 'response' => 'ok'])]);
        $this->admin = User::factory()->admin()->create(['name' => 'Admin']);
        $this->cajero = User::factory()->create(['name' => 'Cajera Ana']);
    }

    private function clienteConPago(): array
    {
        $c = Cliente::create(['nombre' => 'Rosa', 'mensualidad' => 350, 'dia_pago' => 5, 'fecha_alta' => '2026-01-01']);
        $cargo = $c->cargos()->create(['concepto' => 'Mensualidad', 'monto' => 350, 'vence_el' => '2026-11-05']);
        $pago = $c->pagos()->create(['monto' => 350, 'fecha' => '2026-11-04', 'metodo' => 'efectivo']);

        return [$c, $cargo, $pago];
    }

    public function test_nuevos_usuarios_nacen_como_cajero(): void
    {
        $this->assertSame(Rol::Operador, User::factory()->create()->rol);
        $this->assertSame(Rol::Operador, (new User())->rol);
    }

    public function test_cajero_no_entra_a_usuarios(): void
    {
        $this->actingAs($this->cajero);
        $this->get(route('usuarios.index'))->assertForbidden()->assertSee('No tienes permiso');
        $this->get(route('usuarios.create'))->assertForbidden();
        $this->post(route('usuarios.store'), ['name' => 'X', 'email' => 'x@x.mx', 'rol' => 'admin', 'password' => 'secreto-largo', 'password_confirmation' => 'secreto-largo'])->assertForbidden();
        $this->post(route('usuarios.desactivar', $this->admin))->assertForbidden();
        $this->put(route('usuarios.update', $this->cajero), ['name' => 'Yo', 'email' => $this->cajero->email, 'rol' => 'admin'])->assertForbidden();

        $this->assertSame(1, User::where('rol', 'admin')->count());
        $this->assertTrue($this->admin->fresh()->activo);

        // Sí puede cambiar su propia contraseña y no ve el menú de Usuarios
        $this->get(route('password.edit'))->assertOk();
        $this->get(route('clientes.index'))->assertOk()->assertDontSee(route('usuarios.index'))->assertSee('Cajero/Técnico');
    }

    public function test_cajero_registra_pagos_pero_no_cancela(): void
    {
        [$c, $cargo, $pago] = $this->clienteConPago();
        $this->actingAs($this->cajero);

        $this->get(route('clientes.show', $c))->assertOk()->assertSee('Registrar pago')->assertDontSee('summary class', false);

        $this->post(route('pagos.store', $c), ['monto' => 100, 'fecha' => now()->toDateString(), 'metodo' => 'efectivo'])->assertRedirect();
        $this->post(route('cargos.store', $c), ['concepto' => 'Instalación', 'monto' => 500, 'vence_el' => now()->toDateString()])->assertRedirect();
        $this->assertSame(2, $c->pagos()->count());

        $this->post(route('pagos.cancelar', $pago), ['motivo' => 'error'])->assertForbidden();
        $this->post(route('cargos.cancelar', $cargo), ['motivo' => 'error'])->assertForbidden();
        $this->assertNull($pago->fresh()->cancelado_en);
        $this->assertNull($cargo->fresh()->cancelado_en);
    }

    public function test_admin_si_cancela(): void
    {
        [$c, $cargo, $pago] = $this->clienteConPago();
        $this->actingAs($this->admin)->get(route('clientes.show', $c))->assertSee('summary class', false);

        $this->post(route('pagos.cancelar', $pago), ['motivo' => 'Rebotó'])->assertRedirect();
        $this->post(route('cargos.cancelar', $cargo), ['motivo' => 'Bonificado'])->assertRedirect();
        $this->assertNotNull($pago->fresh()->cancelado_en);
        $this->assertNotNull($cargo->fresh()->cancelado_en);
    }

    public function test_cajero_corta_de_uno_en_uno_pero_no_en_masa(): void
    {
        $a = Onu::create(['external_id' => 'A', 'estado_admin' => 'Enabled']);
        $b = Onu::create(['external_id' => 'B', 'estado_admin' => 'Enabled']);
        $this->actingAs($this->cajero);

        $this->post(route('acciones.store'), ['tipo' => 'cortar', 'onus' => [$a->id, $b->id]])->assertForbidden();
        $this->assertFalse($a->fresh()->estaCortada());

        $this->post(route('acciones.store'), ['tipo' => 'cortar', 'onus' => [$a->id]])->assertRedirect();
        $this->assertTrue($a->fresh()->estaCortada());

        // La pantalla de ONUs no muestra la barra de acciones masivas
        $this->get(route('onus.index'))->assertOk()->assertDontSee('Cortar seleccionados');
        $this->actingAs($this->admin)->get(route('onus.index'))->assertSee('Cortar seleccionados');

        $this->post(route('acciones.store'), ['tipo' => 'reconectar', 'onus' => [$a->id, $b->id]])->assertRedirect();
    }

    public function test_siempre_queda_un_administrador(): void
    {
        // Nadie puede quitarse su propio rol (así nunca se queda sin admins por error)
        $this->actingAs($this->admin)
            ->put(route('usuarios.update', $this->admin), ['name' => 'Admin', 'email' => $this->admin->email, 'rol' => 'operador'])
            ->assertSessionHasErrors('rol');
        $this->assertTrue($this->admin->fresh()->esAdmin());

        // Promover a la cajera
        $this->put(route('usuarios.update', $this->cajero), ['name' => 'Cajera Ana', 'email' => $this->cajero->email, 'rol' => 'admin'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->cajero->fresh()->esAdmin());

        // Con dos administradores, uno puede bajar o desactivar al otro
        $this->actingAs($this->cajero->fresh())
            ->put(route('usuarios.update', $this->admin), ['name' => 'Admin', 'email' => $this->admin->email, 'rol' => 'operador'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($this->admin->fresh()->esAdmin());

        // El que bajaron ya no entra a Usuarios
        $this->actingAs($this->admin->fresh())->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_comando_crea_admin_por_defecto_y_cajero_con_opcion(): void
    {
        $this->artisan('operador:crear', ['nombre' => 'Jefe', 'email' => 'jefe@x.mx'])
            ->expectsQuestion('Contraseña (mín. 10 caracteres)', 'secreto-largo')
            ->expectsOutputToContain('Administrador')
            ->assertSuccessful();
        $this->assertTrue(User::where('email', 'jefe@x.mx')->first()->esAdmin());

        $this->artisan('operador:crear', ['nombre' => 'Caja', 'email' => 'caja@x.mx', '--rol' => 'operador'])
            ->expectsQuestion('Contraseña (mín. 10 caracteres)', 'secreto-largo')
            ->assertSuccessful();
        $this->assertFalse(User::where('email', 'caja@x.mx')->first()->esAdmin());

        $this->artisan('operador:crear', ['nombre' => 'X', 'email' => 'x@x.mx', '--rol' => 'jefe'])->assertFailed();
    }
}
