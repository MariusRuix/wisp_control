<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoAccion;
use App\Enums\TipoAccion;
use App\Models\AccionServicio;
use App\Models\Cliente;
use App\Models\Onu;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CortesAutomaticosTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00'));
        config([
            'smartolt.subdomain' => 'demo',
            'smartolt.token' => 't',
            'queue.default' => 'sync',
            'cobros.dias_gracia' => 3,
            'cobros.corte_automatico' => true,
            'cobros.reconexion_automatica' => true,
        ]);
        Http::fake(['demo.smartolt.com/*' => Http::response(['status' => true, 'response' => 'ok'])]);
        $this->operador = User::factory()->create();
    }

    /** Cliente con ONU activa y una mensualidad que vence en $vence. */
    private function deudor(string $nombre, string $vence, array $extra = []): Cliente
    {
        static $n = 0;
        $n++;
        $onu = Onu::create(['external_id' => "E{$n}", 'sn' => "SN{$n}", 'estado_admin' => 'Enabled']);
        $c = Cliente::create([
            'nombre' => $nombre, 'mensualidad' => 350, 'dia_pago' => 5,
            'fecha_alta' => '2026-01-01', 'onu_id' => $onu->id, ...$extra,
        ]);
        $c->cargos()->create(['concepto' => 'Mensualidad', 'monto' => 350, 'vence_el' => $vence, 'periodo' => substr($vence, 0, 8).'01']);

        return $c;
    }

    public function test_corta_solo_a_quien_paso_la_gracia(): void
    {
        $cortar = $this->deudor('Atrasado', '2026-11-05');            // 15 días
        $gracia = $this->deudor('En gracia', '2026-11-18');           // 2 días
        $promesa = $this->deudor('Promesa', '2026-11-05', ['no_cortar_hasta' => '2026-11-25']);
        $promesaVencida = $this->deudor('Promesa vencida', '2026-11-05', ['no_cortar_hasta' => '2026-11-19']);
        $sinOnu = Cliente::create(['nombre' => 'Sin ONU', 'mensualidad' => 350, 'dia_pago' => 5, 'fecha_alta' => '2026-01-01']);
        $sinOnu->cargos()->create(['concepto' => 'X', 'monto' => 350, 'vence_el' => '2026-11-01']);
        $baja = $this->deudor('De baja', '2026-11-05');
        $baja->delete();

        $this->artisan('cortes:automaticos', ['--prueba' => true])
            ->expectsOutputToContain('se cortaría a 2 cliente(s)')
            ->assertSuccessful();
        $this->assertSame(0, AccionServicio::count());

        $this->artisan('cortes:automaticos')->expectsOutputToContain('Cortes enviados a la cola: 2');

        $this->assertTrue($cortar->onu->fresh()->estaCortada());
        $this->assertTrue($promesaVencida->onu->fresh()->estaCortada());
        $this->assertFalse($gracia->onu->fresh()->estaCortada());
        $this->assertFalse($promesa->onu->fresh()->estaCortada());

        $accion = AccionServicio::where('onu_id', $cortar->onu_id)->firstOrFail();
        $this->assertTrue($accion->automatica);
        $this->assertNull($accion->user_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/onu/bulk_disable'));

        // Segunda corrida: ya están cortados, no repite
        $this->artisan('cortes:automaticos')->expectsOutputToContain('Cortes enviados a la cola: 0');
    }

    public function test_interruptor_apagado_no_corta(): void
    {
        config(['cobros.corte_automatico' => false]);
        $this->deudor('Atrasado', '2026-11-05');

        $this->artisan('cortes:automaticos')->expectsOutputToContain('desactivado');
        $this->assertSame(0, AccionServicio::count());
    }

    public function test_pago_completo_reconecta_corte_automatico(): void
    {
        $c = $this->deudor('Atrasado', '2026-11-05');
        $this->artisan('cortes:automaticos');
        $this->assertTrue($c->onu->fresh()->estaCortada());

        // Pago parcial: sigue con vencido, no reconecta
        $this->actingAs($this->operador)->post(route('pagos.store', $c), ['monto' => 100, 'fecha' => '2026-11-20', 'metodo' => 'efectivo']);
        $this->assertTrue($c->onu->fresh()->estaCortada());

        $this->post(route('pagos.store', $c), ['monto' => 250, 'fecha' => '2026-11-20', 'metodo' => 'efectivo']);

        $this->assertFalse($c->onu->fresh()->estaCortada());
        $rec = AccionServicio::where('tipo', TipoAccion::Reconectar->value)->firstOrFail();
        $this->assertTrue($rec->automatica);
        $this->assertSame(EstadoAccion::Exito, $rec->estado);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/onu/enable/E'));
    }

    public function test_corte_manual_no_se_revierte_al_pagar(): void
    {
        $c = $this->deudor('Mal uso', '2026-11-05');
        $this->actingAs($this->operador)->post(route('acciones.store'), ['tipo' => 'cortar', 'onus' => [$c->onu_id]]);
        $this->assertTrue($c->onu->fresh()->estaCortada());

        $this->post(route('pagos.store', $c), ['monto' => 350, 'fecha' => '2026-11-20', 'metodo' => 'efectivo']);

        $this->assertTrue($c->onu->fresh()->estaCortada());
        $this->assertSame(0, AccionServicio::where('tipo', TipoAccion::Reconectar->value)->count());
    }

    public function test_pago_anula_corte_automatico_en_cola(): void
    {
        config(['queue.default' => 'database']); // que se quede en cola
        $c = $this->deudor('Atrasado', '2026-11-05');
        $this->artisan('cortes:automaticos');

        $accion = AccionServicio::firstOrFail();
        $this->assertSame(EstadoAccion::EnCola, $accion->estado);

        $this->actingAs($this->operador)->post(route('pagos.store', $c), ['monto' => 350, 'fecha' => '2026-11-20', 'metodo' => 'efectivo']);

        $this->assertSame(EstadoAccion::Anulado, $accion->fresh()->estado);

        // Al procesar la cola ya no se corta
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'smartolt']);
        $this->assertFalse($c->onu->fresh()->estaCortada());
        Http::assertNothingSent();
    }

    public function test_pantalla_de_adeudos_muestra_decision(): void
    {
        $this->deudor('Atrasado', '2026-11-05');
        $this->deudor('Promesa', '2026-11-05', ['no_cortar_hasta' => '2026-11-25']);

        $this->actingAs($this->operador)->get(route('cobros.adeudos'))
            ->assertOk()
            ->assertSee('Corte automático ACTIVO')
            ->assertSee('Se corta')
            ->assertSee('Promesa de pago')
            ->assertSee('25/11/2026');
    }
}
