<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaginacionTest extends TestCase
{
    use RefreshDatabase;

    private function clientes(int $n): void
    {
        foreach (range(1, $n) as $i) {
            Cliente::create(['nombre' => sprintf('Cliente %03d', $i), 'mensualidad' => 300, 'dia_pago' => 5, 'fecha_alta' => '2026-01-01']);
        }
    }

    public function test_listas_paginan_de_25_en_espanol_con_selector(): void
    {
        $this->clientes(30);
        $this->actingAs(User::factory()->create());

        $this->get(route('clientes.index'))->assertOk()
            ->assertSee('Mostrando <b>1–25</b> de <b>30</b>', false)
            ->assertSee('Siguiente')->assertSee('Anterior')
            ->assertSee('Por página')
            ->assertSee('Cliente 025')->assertDontSee('Cliente 026');

        $this->get(route('clientes.index', ['page' => 2]))
            ->assertSee('Mostrando <b>26–30</b> de <b>30</b>', false)->assertSee('Cliente 030')->assertDontSee('Cliente 001');

        $this->get(route('clientes.index', ['por_pagina' => 50]))->assertSee('Cliente 030')->assertSee('Mostrando <b>1–30</b>', false);

        // Valores no permitidos vuelven a 25
        $this->get(route('clientes.index', ['por_pagina' => 100000]))->assertDontSee('Cliente 026');

        // Los filtros se conservan al cambiar de página
        $this->get(route('clientes.index', ['q' => 'Cliente', 'page' => 1]))->assertSee('q=Cliente', false);
    }

    public function test_pocas_filas_no_muestran_paginas_ni_selector(): void
    {
        $this->clientes(3);
        $this->actingAs(User::factory()->create())
            ->get(route('clientes.index'))
            ->assertSee('Mostrando <b>1–3</b> de <b>3</b>', false)
            ->assertDontSee('Siguiente')->assertDontSee('Por página');
    }

    public function test_ficha_pagina_cada_seccion_por_separado(): void
    {
        $this->clientes(1);
        $c = Cliente::first();
        foreach (range(1, 20) as $i) {
            $c->cargos()->create(['concepto' => "Cargo {$i}", 'monto' => 10, 'vence_el' => now()->subDays(40 - $i)]);
        }
        foreach (range(1, 12) as $i) {
            $c->fallas()->create(['descripcion' => "Falla {$i}", 'reportada_el' => now()->subHours(20 - $i), 'estado' => 'abierta']);
        }

        $this->actingAs(User::factory()->create());
        $html = $this->get(route('clientes.show', $c))->assertOk()
            ->assertSee('Cargo 20')->assertDontSee('Cargo 5<')   // 15 movimientos más recientes
            ->assertSee('Falla 12')->assertDontSee('Falla 2<')
            ->getContent();

        $this->assertStringContainsString('mov_pag=2#cuenta', $html);
        $this->assertStringContainsString('fallas_pag=2#fallas', $html);

        $this->get(route('clientes.show', [$c, 'mov_pag' => 2]))->assertSee('Cargo 5')->assertSee('Falla 12');
        $this->get(route('clientes.show', [$c, 'fallas_pag' => 2]))->assertSee('Falla 2')->assertSee('Cargo 20');
    }

    public function test_usuarios_paginan(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'AAA Admin']);
        User::factory()->count(30)->create();

        $this->actingAs($admin)->get(route('usuarios.index'))
            ->assertOk()->assertSee('Mostrando <b>1–25</b> de <b>31</b>', false);
    }
}
