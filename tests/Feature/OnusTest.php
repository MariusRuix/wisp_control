<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DecisionCorte;
use App\Models\AccionServicio;
use App\Models\Cliente;
use App\Models\Onu;
use App\Models\User;
use App\Services\EstadoCuenta;
use App\Services\PoliticaCortes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OnusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['smartolt.subdomain' => 'demo', 'smartolt.token' => 't', 'queue.default' => 'sync']);
    }

    /** @var list<string> ONUs que "existen" en el SmartOLT simulado */
    private array $enSmartOlt = [];

    /** Simula get_all_onus_details con las ONUs indicadas (una sola página). */
    private function fakeSmartOlt(array $ids): void
    {
        $this->enSmartOlt = $ids;

        // Http::fake acumula; con un closure basta registrarlo una vez y cambiar $enSmartOlt
        static $registrado = null;
        if ($registrado !== $this) {
            $registrado = $this;
            Http::fake(['demo.smartolt.com/api/onu/get_all_onus_details*' => fn () => Http::response([
                'status' => true, 'page' => 1, 'page_size' => 100, 'total_items' => count($this->enSmartOlt), 'total_pages' => 1,
                'onus' => array_map(fn ($id) => ['unique_external_id' => $id, 'sn' => 'SN-'.$id, 'name' => 'onu '.$id,
                    'administrative_status' => 'Enabled', 'status' => 'Online'], $this->enSmartOlt),
            ])]);
        }
    }

    public function test_lista_muestra_el_cliente_del_panel(): void
    {
        $a = Onu::create(['external_id' => 'A', 'sn' => 'SN-A', 'nombre' => 'nombre-en-smartolt']);
        Onu::create(['external_id' => 'B', 'sn' => 'SN-B']);
        $c = Cliente::create(['nombre' => 'Rosa Pérez', 'numero' => 'C-7', 'mensualidad' => 300, 'dia_pago' => 5, 'fecha_alta' => '2026-01-01', 'onu_id' => $a->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('onus.index'))
            ->assertOk()
            ->assertSee('Cliente del panel')
            ->assertSee('nombre-en-smartolt')
            ->assertSee('Rosa Pérez')->assertSee(route('clientes.show', $c))
            ->assertSee('Sin cliente');

        // Buscar por nombre del cliente y filtrar las que no tienen cliente
        $this->get(route('onus.index', ['q' => 'Rosa']))->assertSee('SN-A')->assertDontSee('SN-B');
        $this->get(route('onus.index', ['estado' => 'sin_cliente']))->assertSee('SN-B')->assertDontSee('SN-A');
    }

    public function test_sincronizacion_completa_marca_y_desmarca_eliminadas(): void
    {
        foreach (range(1, 10) as $i) {
            Onu::create(['external_id' => "X{$i}", 'sn' => "SN-X{$i}", 'sincronizado_en' => now()->subDay()]);
        }

        // SmartOLT ya no trae X10
        $this->fakeSmartOlt(array_map(fn ($i) => "X{$i}", range(1, 9)));
        $this->travel(1)->seconds();
        $this->artisan('smartolt:sincronizar')->expectsOutputToContain('ya no existen en SmartOLT (marcadas): 1')->assertSuccessful();

        $this->assertNotNull(Onu::where('external_id', 'X10')->value('eliminada_en'));
        $this->assertNull(Onu::where('external_id', 'X1')->value('eliminada_en'));

        // Vuelve a aparecer: se desmarca
        $this->fakeSmartOlt(array_map(fn ($i) => "X{$i}", range(1, 10)));
        $this->travel(1)->seconds();
        $this->artisan('smartolt:sincronizar')->assertSuccessful();
        $this->assertNull(Onu::where('external_id', 'X10')->value('eliminada_en'));
    }

    public function test_freno_si_desaparecen_demasiadas(): void
    {
        foreach (range(1, 20) as $i) {
            Onu::create(['external_id' => "Y{$i}", 'sincronizado_en' => now()->subDay()]);
        }

        // Solo vienen 5 de 20 (75% faltan): parece falla, no se marca nada
        $this->fakeSmartOlt(['Y1', 'Y2', 'Y3', 'Y4', 'Y5']);
        $this->travel(1)->seconds();
        $this->artisan('smartolt:sincronizar')->expectsOutputToContain('No marqué 15 ONU(s)')->assertSuccessful();
        $this->assertSame(0, Onu::whereNotNull('eliminada_en')->count());

        // Con --forzar sí
        $this->travel(1)->seconds();
        $this->artisan('smartolt:sincronizar', ['--forzar' => true])->assertSuccessful();
        $this->assertSame(15, Onu::whereNotNull('eliminada_en')->count());
    }

    public function test_sincronizacion_por_cambios_no_marca_nada(): void
    {
        Onu::create(['external_id' => 'Z1', 'sincronizado_en' => now()->subDay()]);
        Onu::create(['external_id' => 'Z2', 'sincronizado_en' => now()->subDay()]);
        $this->fakeSmartOlt(['Z1']);
        $this->travel(1)->seconds();

        $this->artisan('smartolt:sincronizar', ['--cambios' => true])->assertSuccessful();
        $this->assertSame(0, Onu::whereNotNull('eliminada_en')->count());
    }

    public function test_onu_eliminada_no_se_corta_ni_se_ofrece(): void
    {
        Http::fake();
        $eliminada = Onu::create(['external_id' => 'E', 'sn' => 'SN-ELIM', 'estado_admin' => 'Enabled', 'eliminada_en' => now()]);
        Onu::create(['external_id' => 'L', 'sn' => 'SN-LIBRE']);
        $c = Cliente::create(['nombre' => 'Con ONU vieja', 'mensualidad' => 300, 'dia_pago' => 5, 'fecha_alta' => '2026-01-01', 'onu_id' => $eliminada->id]);
        $c->cargos()->create(['concepto' => 'Vieja', 'monto' => 300, 'vence_el' => now()->subDays(30)]);
        $admin = User::factory()->admin()->create();

        // No se manda la orden a SmartOLT
        $this->actingAs($admin)->post(route('acciones.store'), ['tipo' => 'cortar', 'onus' => [$eliminada->id]])->assertRedirect();
        $this->assertSame(0, AccionServicio::count());
        Http::assertNothingSent();

        // El corte automático la trata como "Sin ONU"
        $decision = app(PoliticaCortes::class)->decidir($c, app(EstadoCuenta::class)->resumen($c));
        $this->assertSame(DecisionCorte::SinOnu, $decision);

        // Pantallas
        $this->get(route('onus.index'))->assertDontSee('SN-ELIM');                                  // oculta por defecto
        $this->get(route('onus.index', ['estado' => 'eliminadas']))->assertSee('Ya no existe en SmartOLT');
        $this->get(route('clientes.show', $c))->assertSee('Esta ONU ya no existe en SmartOLT')->assertDontSee('Cortar servicio');
        $this->get(route('clientes.create'))->assertSee('SN-LIBRE')->assertDontSee('SN-ELIM');       // no se ofrece a clientes nuevos
        $this->get(route('inicio'))->assertSee('1 cliente(s) con una ONU que ya no existe');
    }
}
