<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoAccion;
use App\Enums\EstadoFalla;
use App\Enums\TipoAccion;
use App\Models\AccionServicio;
use App\Models\Cliente;
use App\Models\Onu;
use App\Models\User;
use App\Services\ResumenInicio;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InicioTest extends TestCase
{
    use RefreshDatabase;

    private User $yo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
        config(['cobros.dias_gracia' => 3, 'cobros.corte_automatico' => false]);
        $this->yo = User::factory()->create(['name' => 'Laura Cobranza']);
    }

    private function cliente(string $nombre, array $onu = [], array $extra = []): Cliente
    {
        static $n = 0;
        $n++;
        $o = $onu === [] ? null : Onu::create(['external_id' => "E{$n}", 'sn' => "SN{$n}", 'estado_admin' => 'Enabled', 'estado_red' => 'Online', 'sincronizado_en' => now()->subMinutes(20), ...$onu]);

        return Cliente::create(['nombre' => $nombre, 'mensualidad' => 400, 'dia_pago' => 5, 'fecha_alta' => '2026-01-01', 'onu_id' => $o?->id, ...$extra]);
    }

    public function test_contadores(): void
    {
        $alCorriente = $this->cliente('Al corriente', ['estado_red' => 'LOS']);
        $alCorriente->cargos()->create(['periodo' => '2026-11-01', 'concepto' => 'Nov', 'monto' => 400, 'vence_el' => '2026-11-05']);
        $alCorriente->pagos()->create(['monto' => 400, 'fecha' => '2026-11-20', 'metodo' => 'efectivo']);

        $deudor = $this->cliente('Deudor', ['estado_red' => 'Online']);
        $deudor->cargos()->create(['periodo' => '2026-11-01', 'concepto' => 'Nov', 'monto' => 400, 'vence_el' => '2026-11-05']);

        $promesa = $this->cliente('Promesa', ['estado_red' => 'Online'], ['no_cortar_hasta' => '2026-11-30']);
        $promesa->cargos()->create(['periodo' => '2026-11-01', 'concepto' => 'Nov', 'monto' => 400, 'vence_el' => '2026-11-05']);
        $promesa->pagos()->create(['monto' => 100, 'fecha' => '2026-11-02', 'metodo' => 'efectivo']);

        $cortado = $this->cliente('Cortado', ['estado_admin' => 'Disabled', 'estado_red' => 'Offline'], ['fecha_alta' => '2026-11-03']);
        $this->cliente('Sin ONU');

        $deudor->fallas()->create(['descripcion' => 'x', 'reportada_el' => now(), 'estado' => EstadoFalla::Abierta]);
        $deudor->fallas()->create(['descripcion' => 'y', 'reportada_el' => now(), 'estado' => EstadoFalla::EnProceso, 'asignado_a' => $this->yo->id]);
        $deudor->fallas()->create(['descripcion' => 'z', 'reportada_el' => now(), 'estado' => EstadoFalla::Resuelta, 'solucion' => 'ok']);
        AccionServicio::create(['onu_id' => $deudor->onu_id, 'tipo' => TipoAccion::Cortar, 'estado' => EstadoAccion::Fallido, 'error' => 'HTTP 500']);

        $r = app(ResumenInicio::class)->calcular($this->yo);

        $this->assertSame(['activos' => 5, 'nuevosMes' => 1, 'cortados' => 1, 'sinOnu' => 1], $r['clientes']);
        $this->assertSame(700.0, $r['cobranza']['vencido']);       // 400 deudor + 300 promesa
        $this->assertSame(2, $r['cobranza']['deudores']);
        $this->assertSame(1, $r['cobranza']['seCortan']);
        $this->assertSame(1, $r['cobranza']['promesas']);
        $this->assertSame(400.0, $r['cobranza']['cobradoHoy']);
        $this->assertSame(500.0, $r['cobranza']['cobradoMes']);
        $this->assertSame(1200.0, $r['cobranza']['facturadoMes']);
        $this->assertSame(42, $r['cobranza']['avanceMes']);
        $this->assertSame(1, $r['red']['fallasAbiertas']);
        $this->assertSame(1, $r['red']['fallasEnProceso']);
        $this->assertSame(1, $r['red']['fallasMias']);
        $this->assertSame(1, $r['red']['onusProblema']);           // LOS con servicio activo; el cortado no cuenta
        $this->assertSame(1, $r['red']['erroresSmartOlt']);
        $this->assertFalse($r['red']['syncAtrasada']);
    }

    public function test_pantalla_de_inicio(): void
    {
        $this->get('/')->assertRedirect(route('login'));

        $this->actingAs($this->yo)->get(route('inicio'))
            ->assertOk()
            ->assertSee('Hola, Laura')
            ->assertSee('Cobrado hoy')
            ->assertSee('Adeudo vencido')
            ->assertSee('Corte automático apagado')
            ->assertSee('Última sincronización: nunca');

        // Sin datos no truena y marca la sincronización como atrasada
        $this->assertTrue(app(ResumenInicio::class)->calcular($this->yo)['red']['syncAtrasada']);
    }

    public function test_login_lleva_al_inicio_y_filtro_de_onus_con_problema(): void
    {
        $u = User::factory()->create(['email' => 'a@a.mx', 'password' => 'secreto-largo']);
        $this->post(route('login'), ['email' => 'a@a.mx', 'password' => 'secreto-largo'])->assertRedirect(route('inicio'));

        Onu::create(['external_id' => 'A', 'sn' => 'SN-LOS', 'estado_admin' => 'Enabled', 'estado_red' => 'LOS']);
        Onu::create(['external_id' => 'B', 'sn' => 'SN-OK', 'estado_admin' => 'Enabled', 'estado_red' => 'Online']);
        $this->actingAs($u)->get(route('onus.index', ['estado' => 'problema']))->assertSee('SN-LOS')->assertDontSee('SN-OK');
    }
}
