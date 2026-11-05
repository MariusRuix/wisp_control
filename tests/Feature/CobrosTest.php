<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\PagoRegistrado;
use App\Models\Cargo;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\User;
use App\Services\EstadoCuenta;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class CobrosTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operador = User::factory()->admin()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    }

    private function cliente(array $extra = []): Cliente
    {
        return Cliente::create([
            'nombre' => 'Cliente '.uniqid(),
            'mensualidad' => 350,
            'dia_pago' => 5,
            'fecha_alta' => '2026-01-01',
            ...$extra,
        ]);
    }

    public function test_genera_mensualidad_una_sola_vez_y_respeta_fecha_de_alta(): void
    {
        $antiguo = $this->cliente();
        $nuevo = $this->cliente(['fecha_alta' => '2026-11-10']); // entró después del día 5
        $tarde = $this->cliente(['fecha_alta' => '2026-11-10', 'dia_pago' => 25]);
        $deBaja = $this->cliente();
        $deBaja->delete();

        $this->artisan('cobros:generar-cargos')->expectsOutputToContain('Cargos creados para 2026-11: 2');
        $this->artisan('cobros:generar-cargos')->expectsOutputToContain('Cargos creados para 2026-11: 0');

        $cargo = Cargo::where('cliente_id', $antiguo->id)->firstOrFail();
        $this->assertSame('Mensualidad noviembre 2026', $cargo->concepto);
        $this->assertSame('2026-11-05', $cargo->vence_el->toDateString());
        $this->assertSame(0, Cargo::where('cliente_id', $nuevo->id)->count());
        $this->assertSame('2026-11-25', Cargo::where('cliente_id', $tarde->id)->value('vence_el')?->toDateString()
            ?? Cargo::where('cliente_id', $tarde->id)->first()->vence_el->toDateString());

        // Mes específico y formato inválido
        $this->artisan('cobros:generar-cargos', ['--mes' => '2026-10'])->expectsOutputToContain('2026-10: 1');
        $this->artisan('cobros:generar-cargos', ['--mes' => '10-2026'])->assertFailed();
    }

    public function test_mensualidad_cancelada_no_se_regenera(): void
    {
        $c = $this->cliente();
        $this->artisan('cobros:generar-cargos');
        $cargo = $c->cargos()->firstOrFail();

        $this->actingAs($this->operador)
            ->post(route('cargos.cancelar', $cargo), ['motivo' => 'Mes bonificado'])
            ->assertRedirect(route('clientes.show', $c));

        $this->artisan('cobros:generar-cargos')->expectsOutputToContain(': 0');
        $this->assertSame(0.0, $c->fresh()->saldo());
    }

    public function test_saldo_vencido_y_dias_de_atraso_fifo(): void
    {
        $c = $this->cliente();
        $c->cargos()->create(['periodo' => '2026-10-01', 'concepto' => 'Oct', 'monto' => 350, 'vence_el' => '2026-10-05']);
        $c->cargos()->create(['periodo' => '2026-11-01', 'concepto' => 'Nov', 'monto' => 350, 'vence_el' => '2026-11-05']);
        $c->cargos()->create(['concepto' => 'Equipo', 'monto' => 500, 'vence_el' => '2026-12-01']);

        $cuenta = app(EstadoCuenta::class);
        $r = $cuenta->resumen($c);
        $this->assertSame(1200.0, $r['saldo']);
        $this->assertSame(700.0, $r['vencido']);
        $this->assertSame(46, $r['dias_atraso']); // desde 5 oct

        // Pago parcial cubre octubre: el atraso ahora cuenta desde noviembre
        $c->pagos()->create(['monto' => 400, 'fecha' => '2026-11-18', 'metodo' => 'efectivo']);
        $r = $cuenta->resumen($c->fresh());
        $this->assertSame(800.0, $r['saldo']);
        $this->assertSame(300.0, $r['vencido']);
        $this->assertSame(15, $r['dias_atraso']);

        // Movimientos con saldo corrido (más reciente primero)
        $mov = $cuenta->movimientos($c->fresh());
        $this->assertSame(800.0, $mov->first()['saldo']);
        $this->assertSame(350.0, $mov->last()['saldo']);
    }

    public function test_registrar_y_cancelar_pago_desde_la_ficha(): void
    {
        Event::fake([PagoRegistrado::class]);
        $c = $this->cliente();
        $c->cargos()->create(['periodo' => '2026-11-01', 'concepto' => 'Nov', 'monto' => 350, 'vence_el' => '2026-11-05']);

        $this->actingAs($this->operador)
            ->get(route('clientes.show', $c))
            ->assertOk()->assertSee('Registrar pago')->assertSee('$350.00');

        $this->post(route('pagos.store', $c), ['monto' => '$350', 'fecha' => '2026-11-20', 'metodo' => 'transferencia', 'referencia' => 'SPEI-1'])
            ->assertRedirect(route('clientes.show', $c));

        Event::assertDispatched(PagoRegistrado::class);
        $pago = Pago::firstOrFail();
        $this->assertSame($this->operador->id, $pago->user_id);
        $this->assertSame(0.0, $c->fresh()->saldo());

        // Validaciones
        $this->post(route('pagos.store', $c), ['monto' => '0', 'fecha' => '2026-11-20', 'metodo' => 'efectivo'])->assertSessionHasErrors('monto');
        $this->post(route('pagos.store', $c), ['monto' => '10', 'fecha' => '2026-12-01', 'metodo' => 'efectivo'])->assertSessionHasErrors('fecha');
        $this->post(route('pagos.cancelar', $pago), [])->assertSessionHasErrors('motivo');

        $this->post(route('pagos.cancelar', $pago), ['motivo' => 'Transferencia rebotada'])->assertRedirect();
        $this->assertNotNull($pago->fresh()->cancelado_en);
        $this->assertSame(350.0, $c->fresh()->saldo());
        $this->get(route('clientes.show', $c))->assertSee('Transferencia rebotada');
    }

    public function test_cargo_extra(): void
    {
        $c = $this->cliente();
        $this->actingAs($this->operador)
            ->post(route('cargos.store', $c), ['concepto' => 'Instalación', 'monto' => '1,200', 'vence_el' => '2026-11-30'])
            ->assertRedirect();

        $this->assertSame(1200.0, $c->fresh()->saldo());
        $this->assertSame(0.0, $c->fresh()->vencido());
    }

    public function test_lista_de_adeudos_y_pagos_del_dia(): void
    {
        $debe = $this->cliente(['nombre' => 'Debe Mucho']);
        $debe->cargos()->create(['periodo' => '2026-11-01', 'concepto' => 'Nov', 'monto' => 350, 'vence_el' => '2026-11-05']);
        $alCorriente = $this->cliente(['nombre' => 'Al Corriente']);
        $alCorriente->cargos()->create(['periodo' => '2026-11-01', 'concepto' => 'Nov', 'monto' => 350, 'vence_el' => '2026-11-05']);
        $alCorriente->pagos()->create(['monto' => 350, 'fecha' => '2026-11-20', 'metodo' => 'efectivo', 'user_id' => $this->operador->id]);
        $porVencer = $this->cliente(['nombre' => 'Por Vencer']);
        $porVencer->cargos()->create(['periodo' => '2026-11-01', 'concepto' => 'Nov', 'monto' => 350, 'vence_el' => '2026-11-25']);

        $this->actingAs($this->operador)
            ->get(route('cobros.adeudos'))
            ->assertOk()
            ->assertSee('Debe Mucho')->assertDontSee('Al Corriente')->assertDontSee('Por Vencer')
            ->assertSee('15 día(s)')->assertSee('$350.00');

        $this->get(route('cobros.adeudos', ['q' => 'nadie']))->assertSee('Nadie tiene adeudo vencido');

        $this->get(route('cobros.pagos'))->assertOk()->assertSee('Al Corriente')->assertSee('$350.00');
        $this->get(route('cobros.pagos', ['desde' => '2026-11-01', 'hasta' => '2026-11-19']))->assertDontSee('Al Corriente');

        $this->get(route('clientes.index'))->assertOk()->assertSee('Debe Mucho');
    }
}
