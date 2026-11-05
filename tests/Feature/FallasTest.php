<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoFalla;
use App\Models\Cliente;
use App\Models\Falla;
use App\Models\Onu;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FallasTest extends TestCase
{
    use RefreshDatabase;

    private User $yo;

    private User $tecnico;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
        $this->yo = User::factory()->create(['name' => 'Mario']);
        $this->tecnico = User::factory()->create(['name' => 'Técnico Luis']);
        $onu = Onu::create(['external_id' => 'E1', 'sn' => 'SN1', 'estado_red' => 'LOS']);
        $this->cliente = Cliente::create([
            'nombre' => 'Rosa Pérez', 'mensualidad' => 350, 'dia_pago' => 5, 'fecha_alta' => '2026-01-01', 'onu_id' => $onu->id,
        ]);
    }

    public function test_registrar_falla_desde_la_ficha(): void
    {
        $this->actingAs($this->yo)->get(route('clientes.show', $this->cliente))
            ->assertOk()->assertSee('Registrar falla')->assertSee('Sin fallas reportadas');

        $this->post(route('fallas.store', $this->cliente), [
            'descripcion' => 'Sin internet, luz roja', 'reportada_el' => '2026-11-20T08:30',
        ])->assertRedirect();

        $f = Falla::firstOrFail();
        $this->assertSame(EstadoFalla::Abierta, $f->estado);
        $this->assertSame('LOS', $f->estado_red_al_reportar);
        $this->assertSame($this->yo->id, $f->creado_por);

        // Con técnico asignado nace "en proceso"
        $this->post(route('fallas.store', $this->cliente), [
            'descripcion' => 'Lento', 'reportada_el' => '2026-11-20T09:00', 'asignado_a' => $this->tecnico->id,
        ]);
        $this->assertSame(EstadoFalla::EnProceso, Falla::latest('id')->first()->estado);

        // Validaciones
        $this->post(route('fallas.store', $this->cliente), ['descripcion' => '', 'reportada_el' => '2026-11-20T09:00'])
            ->assertSessionHasErrors('descripcion');
        $this->post(route('fallas.store', $this->cliente), ['descripcion' => 'x', 'reportada_el' => '2026-11-21T09:00'])
            ->assertSessionHasErrors('reportada_el');

        $this->get(route('clientes.show', $this->cliente))->assertSee('Sin internet, luz roja')->assertSee('Técnico Luis');
    }

    public function test_resolver_requiere_solucion_y_se_puede_reabrir(): void
    {
        $f = $this->cliente->fallas()->create([
            'descripcion' => 'Sin servicio', 'reportada_el' => now(), 'estado' => EstadoFalla::Abierta, 'creado_por' => $this->yo->id,
        ]);

        $this->actingAs($this->yo)->get(route('fallas.edit', $f))->assertOk()->assertSee('Rosa Pérez');

        $this->put(route('fallas.update', $f), ['estado' => 'resuelta', 'descripcion' => 'Sin servicio'])
            ->assertSessionHasErrors('solucion');

        $this->put(route('fallas.update', $f), [
            'estado' => 'resuelta', 'descripcion' => 'Sin servicio', 'solucion' => 'Conector dañado, se reemplazó', 'asignado_a' => $this->tecnico->id,
        ])->assertRedirect(route('fallas.index'));

        $f->refresh();
        $this->assertTrue($f->estaResuelta());
        $this->assertNotNull($f->resuelta_el);
        $this->assertSame($this->tecnico->id, $f->asignado_a);

        $this->put(route('fallas.update', $f), ['estado' => 'abierta', 'descripcion' => 'Volvió a fallar', 'volver' => 'cliente'])
            ->assertRedirect(route('clientes.show', $this->cliente).'#fallas');
        $this->assertNull($f->fresh()->resuelta_el);
    }

    public function test_lista_de_fallas_pendientes_y_filtros(): void
    {
        $base = ['reportada_el' => now()->subHours(3), 'creado_por' => $this->yo->id];
        $this->cliente->fallas()->create([...$base, 'descripcion' => 'Abierta sin asignar', 'estado' => EstadoFalla::Abierta]);
        $this->cliente->fallas()->create([...$base, 'descripcion' => 'La atiende Luis', 'estado' => EstadoFalla::EnProceso, 'asignado_a' => $this->tecnico->id]);
        $this->cliente->fallas()->create([...$base, 'descripcion' => 'Ya resuelta', 'estado' => EstadoFalla::Resuelta, 'solucion' => 'ok', 'resuelta_el' => now()]);

        $this->actingAs($this->yo)->get(route('fallas.index'))
            ->assertOk()
            ->assertSee('Abierta sin asignar')->assertSee('La atiende Luis')->assertDontSee('Ya resuelta')
            ->assertSee('1 abierta(s)');

        $this->get(route('fallas.index', ['estado' => 'resuelta']))->assertSee('Ya resuelta')->assertDontSee('Abierta sin asignar');

        $this->actingAs($this->tecnico)->get(route('fallas.index', ['mias' => 1]))
            ->assertSee('La atiende Luis')->assertDontSee('Abierta sin asignar');

        // Contador en el menú
        $this->get(route('clientes.index'))->assertSeeInOrder(['Fallas', '2']);
    }
}
