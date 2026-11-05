<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Onu;
use App\Models\User;
use App\Services\ConfiguracionSmartOlt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ConfiguracionSmartOltTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['smartolt.subdomain' => 'desde-env', 'smartolt.token' => 'token-del-env-1234', 'queue.default' => 'sync']);
        $this->admin = User::factory()->admin()->create();
    }

    private function datos(array $extra = []): array
    {
        return ['subdomain' => 'miempresa', 'token' => 'TOKEN-SECRETO-NUEVO-9876', 'timeout' => 20, 'bulk_field' => 'onus_external_ids', ...$extra];
    }

    public function test_solo_admin_entra(): void
    {
        $cajero = User::factory()->create();
        $this->actingAs($cajero)->get(route('configuracion.smartolt'))->assertForbidden();
        $this->put(route('configuracion.smartolt.guardar'), $this->datos())->assertForbidden();
        $this->post(route('configuracion.smartolt.probar'))->assertForbidden();
        $this->get(route('clientes.index'))->assertDontSee(route('configuracion.smartolt'));

        $this->actingAs($this->admin)->get(route('configuracion.smartolt'))->assertOk()
            ->assertSee('desde-env')->assertSee('.ENV')->assertDontSee('token-del-env-1234')->assertSee('1234');
    }

    public function test_guardar_cifra_el_token_y_tiene_prioridad_sobre_env(): void
    {
        $this->actingAs($this->admin)
            ->put(route('configuracion.smartolt.guardar'), $this->datos(['subdomain' => 'https://MiEmpresa.smartolt.com/']))
            ->assertSessionHasNoErrors()->assertRedirect();

        $token = Ajuste::where('clave', 'smartolt.token')->firstOrFail();
        $this->assertTrue($token->cifrado);
        $this->assertStringNotContainsString('TOKEN-SECRETO', (string) $token->valor);

        $cfg = new ConfiguracionSmartOlt();
        $this->assertSame('miempresa', $cfg->subdominio());
        $this->assertSame('TOKEN-SECRETO-NUEVO-9876', $cfg->token());
        $this->assertSame(20, $cfg->timeout());
        $this->assertSame('panel', $cfg->origen('token'));

        // La pantalla nunca muestra el token completo
        $this->get(route('configuracion.smartolt'))->assertDontSee('TOKEN-SECRETO-NUEVO-9876')->assertSee('9876')->assertSee('PANEL');

        // Token vacío = no se cambia
        $this->put(route('configuracion.smartolt.guardar'), $this->datos(['token' => '', 'timeout' => 45]));
        $this->assertSame('TOKEN-SECRETO-NUEVO-9876', (new ConfiguracionSmartOlt())->token());
        $this->assertSame(45, (new ConfiguracionSmartOlt())->timeout());
    }

    public function test_las_llamadas_reales_usan_lo_del_panel(): void
    {
        Http::fake(['*' => Http::response(['status' => true, 'response' => 'ok'])]);
        $this->actingAs($this->admin)->put(route('configuracion.smartolt.guardar'), $this->datos([
            'endpoints' => ['disable' => '/api/onu/suspender/{id}'],
        ]));
        $onu = Onu::create(['external_id' => 'X9', 'estado_admin' => 'Enabled']);

        $this->post(route('acciones.store'), ['tipo' => 'cortar', 'onus' => [$onu->id]]);

        Http::assertSent(fn ($r) => $r->url() === 'https://miempresa.smartolt.com/api/onu/suspender/X9'
            && $r->hasHeader('X-Token', 'TOKEN-SECRETO-NUEVO-9876'));

        // Ruta vacía vuelve a la de fábrica
        $this->put(route('configuracion.smartolt.guardar'), $this->datos(['token' => '', 'endpoints' => ['disable' => '']]));
        $this->assertSame('/api/onu/disable/{id}', (new ConfiguracionSmartOlt())->ruta('disable'));
    }

    public function test_probar_conexion(): void
    {
        Http::fake([
            'miempresa.smartolt.com/api/system/get_olts' => Http::response(['status' => true, 'response' => [['id' => 1], ['id' => 2]]]),
            'mala.smartolt.com/*' => Http::response(['status' => false, 'error' => 'Invalid API key'], 403),
        ]);

        $this->actingAs($this->admin)->put(route('configuracion.smartolt.guardar'), $this->datos(['probar' => 1]))
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '2 OLT(s)'));

        $this->put(route('configuracion.smartolt.guardar'), $this->datos(['subdomain' => 'mala', 'token' => '']));
        $this->post(route('configuracion.smartolt.probar'))->assertSessionHas('aviso', fn ($m) => str_contains($m, 'Invalid API key'));
    }

    public function test_validaciones_y_restablecer(): void
    {
        $this->actingAs($this->admin);
        $this->put(route('configuracion.smartolt.guardar'), $this->datos(['subdomain' => 'mi empresa!']))->assertSessionHasErrors('subdomain');
        $this->put(route('configuracion.smartolt.guardar'), $this->datos(['timeout' => 500]))->assertSessionHasErrors('timeout');
        $this->put(route('configuracion.smartolt.guardar'), $this->datos(['endpoints' => ['disable' => 'http://otro.com/x']]))->assertSessionHasErrors('endpoints.disable');
        $this->put(route('configuracion.smartolt.guardar'), $this->datos(['token' => 'corto']))->assertSessionHasErrors('token');

        $this->put(route('configuracion.smartolt.guardar'), $this->datos())->assertSessionHasNoErrors();
        $this->post(route('configuracion.smartolt.restablecer'))->assertRedirect();
        $this->assertSame(0, Ajuste::count());
        $this->assertSame('desde-env', (new ConfiguracionSmartOlt())->subdominio());
    }

    public function test_token_ilegible_si_cambia_app_key(): void
    {
        Ajuste::create(['clave' => 'smartolt.token', 'valor' => 'basura-no-cifrada', 'cifrado' => true]);
        $cfg = new ConfiguracionSmartOlt();

        $this->assertTrue($cfg->tokenIlegible());
        $this->assertSame('token-del-env-1234', $cfg->token()); // cae al .env
        $this->actingAs($this->admin)->get(route('configuracion.smartolt'))->assertSee('no se puede leer');
    }
}
