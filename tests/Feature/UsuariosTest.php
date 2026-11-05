<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private User $yo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->yo = User::factory()->admin()->create(['name' => 'Mario', 'password' => 'clave-actual-123']);
    }

    public function test_alta_y_edicion_de_operador(): void
    {
        $this->actingAs($this->yo)->get(route('usuarios.index'))->assertOk()->assertSee('Mario');

        $this->post(route('usuarios.store'), [
            'name' => 'Ana', 'email' => ' ANA@Empresa.MX ', 'password' => 'secreto-largo', 'password_confirmation' => 'secreto-largo', 'rol' => 'operador',
        ])->assertRedirect(route('usuarios.index'));

        $ana = User::where('email', 'ana@empresa.mx')->firstOrFail();
        $this->assertTrue($ana->activo);

        // Contraseña corta / sin confirmar / correo repetido
        $this->post(route('usuarios.store'), ['name' => 'X', 'email' => 'x@x.mx', 'password' => 'corta', 'password_confirmation' => 'corta', 'rol' => 'operador'])
            ->assertSessionHasErrors('password');
        $this->post(route('usuarios.store'), ['name' => 'X', 'email' => 'ana@empresa.mx', 'password' => 'secreto-largo', 'password_confirmation' => 'secreto-largo', 'rol' => 'operador'])
            ->assertSessionHasErrors('email');

        // Editar sin contraseña conserva la anterior
        $this->put(route('usuarios.update', $ana), ['name' => 'Ana María', 'email' => 'ana@empresa.mx', 'rol' => 'operador'])->assertSessionHasNoErrors();
        $this->assertSame('Ana María', $ana->fresh()->name);
        $this->assertTrue(Hash::check('secreto-largo', $ana->fresh()->password));

        // Restablecer contraseña de otro operador
        $this->put(route('usuarios.update', $ana), ['name' => 'Ana María', 'email' => 'ana@empresa.mx', 'rol' => 'operador', 'password' => 'nueva-clave-99', 'password_confirmation' => 'nueva-clave-99']);
        $this->assertTrue(Hash::check('nueva-clave-99', $ana->fresh()->password));
    }

    public function test_desactivar_impide_entrar_y_saca_la_sesion(): void
    {
        $ana = User::factory()->create(['email' => 'ana@x.mx', 'password' => 'secreto-largo']);

        $this->actingAs($this->yo)->post(route('usuarios.desactivar', $ana))->assertRedirect(route('usuarios.index'));
        $this->assertFalse($ana->fresh()->activo);

        auth()->logout();
        $this->post(route('login'), ['email' => 'ana@x.mx', 'password' => 'secreto-largo'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Si ya tenía sesión abierta, el siguiente clic la saca
        $this->actingAs($ana->fresh())->get(route('clientes.index'))->assertRedirect(route('login'));
        $this->assertGuest();

        // Reactivar
        $this->actingAs($this->yo)->post(route('usuarios.activar', $ana));
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'ana@x.mx', 'password' => 'secreto-largo'])->assertRedirect(route('inicio'));
        $this->assertNotNull($ana->fresh()->ultimo_acceso);
    }

    public function test_no_puede_desactivarse_a_si_mismo_ni_al_ultimo_activo(): void
    {
        $this->actingAs($this->yo)->post(route('usuarios.desactivar', $this->yo))->assertSessionHasErrors('usuario');
        $this->assertTrue($this->yo->fresh()->activo);

        $otro = User::factory()->admin()->create();
        User::factory()->admin()->create(); // otro admin activo, para que $this->yo no sea el último
        $this->actingAs($otro)->post(route('usuarios.desactivar', $this->yo))->assertRedirect();
        $this->assertFalse($this->yo->fresh()->activo);
    }

    public function test_cambiar_mi_contrasena(): void
    {
        $this->actingAs($this->yo)->get(route('password.edit'))->assertOk();

        $this->put(route('password.update'), ['actual' => 'mala', 'password' => 'otra-clave-123', 'password_confirmation' => 'otra-clave-123'])
            ->assertSessionHasErrors('actual');

        $this->put(route('password.update'), ['actual' => 'clave-actual-123', 'password' => 'otra-clave-123', 'password_confirmation' => 'otra-clave-123'])
            ->assertRedirect(route('inicio'));

        $this->assertTrue(Hash::check('otra-clave-123', $this->yo->fresh()->password));

        // Desde "Editar" no puede cambiar su propia contraseña sin la actual
        $this->put(route('usuarios.update', $this->yo), [
            'name' => 'Mario', 'email' => $this->yo->email, 'rol' => 'admin', 'password' => 'hackeo-12345', 'password_confirmation' => 'hackeo-12345',
        ]);
        $this->assertTrue(Hash::check('otra-clave-123', $this->yo->fresh()->password));
    }

    public function test_alta_y_edicion_desde_modales(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Cajera', 'email' => 'ana@x.mx']);
        $luis = User::factory()->create(['name' => 'Luis Técnico', 'email' => 'luis@x.mx']);

        $this->actingAs($this->yo)->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('id="modal-usuario-nuevo"', false)
            ->assertSee('data-abrir-modal="modal-usuario-nuevo"', false)
            ->assertSee('id="modal-usuario-'.$ana->id.'"', false)
            ->assertSee('data-abrir-modal="modal-usuario-'.$ana->id.'"', false)
            ->assertSee('Editar · Ana Cajera')
            ->assertSee('Mis datos');

        // Error al editar a Ana: reabre SOLO su modal y lo capturado no aparece en el de Luis
        $this->from(route('usuarios.index'))
            ->put(route('usuarios.update', $ana), ['_modal' => 'usuario-'.$ana->id, 'name' => 'Ana Editada', 'email' => 'luis@x.mx', 'rol' => 'operador'])
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHasErrors('email');

        $html = $this->get(route('usuarios.index'))->assertSee('Ese correo ya está registrado.')->getContent();
        $this->assertSame(1, substr_count($html, 'se-abre-al-cargar'));      // un solo modal se abre
        $this->assertSame(1, substr_count($html, 'value="Ana Editada"'));      // lo capturado solo en el modal de Ana
        $this->assertStringContainsString('value="Luis Técnico"', $html);

        // Alta correcta desde el modal
        $this->from(route('usuarios.index'))
            ->post(route('usuarios.store'), ['_modal' => 'usuario-nuevo', 'name' => 'Nuevo', 'email' => 'nuevo@x.mx', 'rol' => 'operador',
                'password' => 'secreto-largo', 'password_confirmation' => 'secreto-largo'])
            ->assertRedirect(route('usuarios.index'));
        $this->assertTrue(User::where('email', 'nuevo@x.mx')->exists());

        // ?nuevo=1 abre el de alta
        $this->get(route('usuarios.index', ['nuevo' => 1]))->assertSee('se-abre-al-cargar', false);
        $this->get(route('usuarios.index'))->assertDontSee('se-abre-al-cargar', false);
    }
}
