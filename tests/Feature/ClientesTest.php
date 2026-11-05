<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Onu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientesTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operador = User::factory()->create();
    }

    private function datos(array $extra = []): array
    {
        return [
            'nombre' => 'María López',
            'numero' => 'C-1',
            'telefono' => '5512345678',
            'mensualidad' => '$1,350.50',
            'dia_pago' => 5,
            'fecha_alta' => '2026-11-01',
            ...$extra,
        ];
    }

    public function test_alta_de_cliente_con_onu(): void
    {
        $onu = Onu::create(['external_id' => 'X1', 'sn' => 'HWTC0001']);

        $this->actingAs($this->operador)
            ->post(route('clientes.store'), $this->datos(['onu_id' => $onu->id]))
            ->assertRedirect();

        $c = Cliente::firstOrFail();
        $this->assertSame('1350.50', $c->mensualidad);
        $this->assertSame($onu->id, $c->onu_id);

        $this->get(route('clientes.show', $c))->assertOk()->assertSee('HWTC0001')->assertSee('Cortar servicio');
        $this->get(route('clientes.index', ['q' => 'HWTC0001']))->assertOk()->assertSee('María López');
    }

    public function test_una_onu_no_se_puede_ligar_a_dos_clientes(): void
    {
        $onu = Onu::create(['external_id' => 'X1', 'sn' => 'S1']);
        Cliente::create([...$this->datos(['mensualidad' => 300]), 'onu_id' => $onu->id]);

        $this->actingAs($this->operador)
            ->post(route('clientes.store'), $this->datos(['numero' => 'C-2', 'onu_id' => $onu->id]))
            ->assertSessionHasErrors('onu_id');

        $this->assertSame(1, Cliente::count());
    }

    public function test_editar_conserva_su_onu_y_valida_dia_de_pago(): void
    {
        $onu = Onu::create(['external_id' => 'X1', 'sn' => 'S1']);
        $c = Cliente::create([...$this->datos(['mensualidad' => 300]), 'onu_id' => $onu->id]);

        $this->actingAs($this->operador)
            ->put(route('clientes.update', $c), $this->datos(['onu_id' => $onu->id, 'dia_pago' => 31]))
            ->assertSessionHasErrors('dia_pago');

        $this->put(route('clientes.update', $c), $this->datos(['onu_id' => $onu->id, 'nombre' => 'María L.']))
            ->assertSessionHasNoErrors();

        $this->assertSame('María L.', $c->fresh()->nombre);
        $this->get(route('clientes.edit', $c))->assertOk()->assertSee('S1');
    }

    public function test_baja_libera_onu_y_se_puede_reactivar(): void
    {
        $onu = Onu::create(['external_id' => 'X1', 'sn' => 'S1']);
        $c = Cliente::create([...$this->datos(['mensualidad' => 300]), 'onu_id' => $onu->id]);

        $this->actingAs($this->operador)->delete(route('clientes.destroy', $c))->assertRedirect(route('clientes.index'));

        $baja = Cliente::onlyTrashed()->firstOrFail();
        $this->assertNull($baja->onu_id);
        $this->assertStringContainsString('se liberó la ONU S1', (string) $baja->notas);
        $this->get(route('clientes.show', $baja))->assertOk()->assertSee('Reactivar');
        $this->get(route('clientes.index', ['vista' => 'bajas']))->assertSee('María López');

        $this->post(route('clientes.restore', $baja->id))->assertRedirect(route('clientes.show', [$baja, 'editar' => 1]));
        $this->assertFalse($baja->fresh()->trashed());
    }

    public function test_importar_csv(): void
    {
        Onu::create(['external_id' => 'X1', 'sn' => 'HWTC1']);
        $csv = tempnam(sys_get_temp_dir(), 'cli');
        file_put_contents($csv, "\xEF\xBB\xBFnumero;nombre;mensualidad;dia_pago;fecha_alta;onu_sn\n"
            ."A1;Ana;350;5;01/03/2025;HWTC1\n"
            ."A2;Beto;400;10;;\n"
            ."A3;;400;10;;\n"
            ."A4;Caro;400;10;;NOEXISTE\n");

        $this->artisan('clientes:importar', ['archivo' => $csv, '--prueba' => true])->assertSuccessful();
        $this->assertSame(0, Cliente::count());

        $this->artisan('clientes:importar', ['archivo' => $csv])
            ->expectsOutputToContain('Nuevos: 2 · Actualizados: 0 · Con error: 2')
            ->assertSuccessful();

        $this->assertSame('2025-03-01', Cliente::where('numero', 'A1')->value('fecha_alta')?->toDateString()
            ?? Cliente::where('numero', 'A1')->first()->fecha_alta->toDateString());
        $this->assertNotNull(Cliente::where('numero', 'A1')->value('onu_id'));

        // Reimportar actualiza en vez de duplicar
        $this->artisan('clientes:importar', ['archivo' => $csv])->expectsOutputToContain('Actualizados: 2');
        $this->assertSame(2, Cliente::count());
    }

    public function test_invitado_no_ve_clientes(): void
    {
        auth()->logout();
        $this->get(route('clientes.index'))->assertRedirect(route('login'));
    }

    public function test_alta_desde_el_modal_de_la_lista(): void
    {
        Onu::create(['external_id' => 'M1', 'sn' => 'SN-MODAL']);

        $this->actingAs($this->operador)->get(route('clientes.index'))
            ->assertOk()
            ->assertSee('id="modal-cliente-nuevo"', false)
            ->assertSee('data-abrir-modal="modal-cliente-nuevo"', false)
            ->assertSee('SN-MODAL')            // ONUs disponibles dentro del modal
            ->assertDontSee('se-abre-al-cargar', false);

        // Con ?nuevo=1 (botón de Inicio) se abre solo
        $this->get(route('clientes.index', ['nuevo' => 1]))->assertSee('se-abre-al-cargar', false);

        // Error de validación: regresa a la lista y reabre el modal con el mensaje
        $this->from(route('clientes.index'))
            ->post(route('clientes.store'), ['_modal' => 'nuevo', 'nombre' => '', 'mensualidad' => '350', 'dia_pago' => 5, 'fecha_alta' => '2026-11-01'])
            ->assertRedirect(route('clientes.index'))
            ->assertSessionHasErrors('nombre');
        $this->get(route('clientes.index'))->assertSee('se-abre-al-cargar', false)->assertSee('El campo nombre es obligatorio', false);

        // Guardado correcto: va a la ficha del cliente nuevo
        $this->from(route('clientes.index'))
            ->post(route('clientes.store'), ['_modal' => 'nuevo', ...$this->datos(['numero' => 'C-MODAL'])])
            ->assertRedirect(route('clientes.show', Cliente::where('numero', 'C-MODAL')->firstOrFail()));
    }

    public function test_edicion_desde_el_modal_de_la_ficha(): void
    {
        $onu = Onu::create(['external_id' => 'E1', 'sn' => 'SN-ACTUAL']);
        Onu::create(['external_id' => 'E2', 'sn' => 'SN-LIBRE']);
        $otra = Onu::create(['external_id' => 'E3', 'sn' => 'SN-OCUPADA']);
        $c = Cliente::create([...$this->datos(['mensualidad' => 300]), 'onu_id' => $onu->id]);
        Cliente::create([...$this->datos(['numero' => 'C-2', 'mensualidad' => 300]), 'onu_id' => $otra->id]);

        $this->actingAs($this->operador)->get(route('clientes.show', $c))
            ->assertOk()
            ->assertSee('id="modal-cliente-editar"', false)
            ->assertSee('data-abrir-modal="modal-cliente-editar"', false)
            ->assertSee('Editar · María López')
            ->assertSee('SN-ACTUAL')->assertSee('SN-LIBRE')
            ->assertDontSee('SN-OCUPADA');   // ONU de otro cliente no se ofrece

        // Error: regresa a la ficha y reabre el modal de edición (no el de alta)
        $this->from(route('clientes.show', $c))
            ->put(route('clientes.update', $c), ['_modal' => 'editar', ...$this->datos(['dia_pago' => 40, 'onu_id' => $onu->id])])
            ->assertRedirect(route('clientes.show', $c))
            ->assertSessionHasErrors('dia_pago');
        $this->get(route('clientes.show', $c))->assertSee('se-abre-al-cargar', false)->assertSee('El día de pago debe estar entre 1 y 28');

        // Guardado correcto
        $this->from(route('clientes.show', $c))
            ->put(route('clientes.update', $c), ['_modal' => 'editar', ...$this->datos(['nombre' => 'María L. Editada', 'onu_id' => $onu->id])])
            ->assertRedirect(route('clientes.show', $c));
        $this->assertSame('María L. Editada', $c->fresh()->nombre);

        // ?editar=1 lo abre al cargar (lo usa "Reactivar")
        $this->get(route('clientes.show', [$c, 'editar' => 1]))->assertSee('se-abre-al-cargar', false);
    }
}
