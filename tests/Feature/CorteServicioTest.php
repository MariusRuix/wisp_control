<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoAccion;
use App\Models\AccionServicio;
use App\Models\Onu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CorteServicioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smartolt.subdomain' => 'demo',
            'smartolt.token' => 'token-prueba',
            'queue.default' => 'sync',
        ]);
    }

    public function test_cortar_un_cliente_llama_a_smartolt_y_queda_en_bitacora(): void
    {
        Http::fake(['demo.smartolt.com/api/onu/disable/*' => Http::response(['status' => true, 'response' => 'ok'])]);

        $usuario = User::factory()->create();
        $onu = Onu::create(['external_id' => 'ONU-001', 'nombre' => 'Juan', 'estado_admin' => 'Enabled']);

        $this->actingAs($usuario)
            ->post(route('acciones.store'), ['tipo' => 'cortar', 'onus' => [$onu->id], 'motivo' => 'Falta de pago'])
            ->assertRedirect();

        Http::assertSent(fn ($r) => $r->url() === 'https://demo.smartolt.com/api/onu/disable/ONU-001'
            && $r->hasHeader('X-Token', 'token-prueba'));

        $this->assertSame('Disabled', $onu->fresh()->estado_admin);
        $this->assertSame(EstadoAccion::Exito, AccionServicio::first()->estado);
    }

    public function test_varios_clientes_usan_bulk_y_errores_se_registran(): void
    {
        Http::fake(['demo.smartolt.com/api/onu/bulk_enable' => Http::response(['status' => false, 'error' => 'ONU no encontrada'], 400)]);

        $usuario = User::factory()->create();
        $a = Onu::create(['external_id' => 'A', 'estado_admin' => 'Disabled']);
        $b = Onu::create(['external_id' => 'B', 'estado_admin' => 'Disabled']);

        $this->actingAs($usuario)
            ->post(route('acciones.store'), ['tipo' => 'reconectar', 'onus' => [$a->id, $b->id]]);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/onu/bulk_enable'));

        $this->assertSame(2, AccionServicio::where('estado', EstadoAccion::Fallido->value)->count());
        $this->assertSame('Disabled', $a->fresh()->estado_admin);
    }

    public function test_invitado_no_puede_cortar(): void
    {
        $this->post(route('acciones.store'), ['tipo' => 'cortar', 'onus' => [1]])
            ->assertRedirect(route('login'));
    }
}
