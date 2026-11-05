<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MetodoPago;
use App\Models\Cliente;
use App\Models\User;
use App\Services\GeneradorCargos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Genera las mensualidades de los últimos 3 meses (con el mismo servicio que
 * usa el sistema) y registra pagos según el escenario de cada cliente.
 */
final class CobrosDemoSeeder extends Seeder
{
    public function run(GeneradorCargos $generador): void
    {
        $hoy = CarbonImmutable::today();
        foreach ([2, 1, 0] as $atras) {
            $generador->generar($hoy->subMonthsNoOverflow($atras));
        }

        $operadores = User::where('activo', true)->pluck('id')->all();
        $faker = fake();

        Cliente::query()->where('numero', 'like', 'DEMO-%')->whereDoesntHave('pagos')->each(function (Cliente $c) use ($hoy, $operadores, $faker) {
            $escenario = $this->escenario($c);
            $cargos = $c->cargos()->vigentes()->orderBy('vence_el')->get();
            if ($cargos->isEmpty()) {
                return;
            }

            // Cuántas de las mensualidades más recientes quedan SIN pagar
            $sinPagar = match ($escenario) {
                'debe_mes_actual' => 1,
                'atrasado', 'promesa', 'cortado_auto', 'cortado_manual' => min(2, $cargos->count()),
                default => 0,
            };

            foreach ($cargos->slice(0, $cargos->count() - $sinPagar) as $cargo) {
                $fecha = CarbonImmutable::parse($cargo->vence_el)->subDays(random_int(-2, 4));
                $c->pagos()->create([
                    'monto' => $cargo->monto,
                    'fecha' => $fecha->min($hoy)->toDateString(),
                    'metodo' => $faker->randomElement([MetodoPago::Efectivo, MetodoPago::Efectivo, MetodoPago::Transferencia, MetodoPago::Deposito])->value,
                    'referencia' => $faker->boolean(40) ? 'SPEI-'.$faker->numerify('######') : null,
                    'user_id' => $faker->randomElement($operadores),
                ]);
            }

            if ($escenario === 'saldo_a_favor') {
                $c->pagos()->create([
                    'monto' => $c->mensualidad, 'fecha' => $hoy->toDateString(),
                    'metodo' => MetodoPago::Transferencia->value, 'referencia' => 'Adelanto',
                    'user_id' => $faker->randomElement($operadores),
                ]);
            }

            if ($escenario === 'atrasado' && $faker->boolean(50)) {
                $c->cargos()->create([
                    'concepto' => 'Reconexión', 'monto' => 100, 'vence_el' => $hoy->subDays(10)->toDateString(),
                    'user_id' => $faker->randomElement($operadores),
                ]);
            }
        });

        // Un pago cancelado para que se vea en Pagos y en la ficha
        $alguno = Cliente::where('numero', 'like', 'DEMO-%')->whereHas('pagos')->inRandomOrder()->first();
        $alguno?->pagos()->create([
            'monto' => $alguno->mensualidad, 'fecha' => $hoy->toDateString(), 'metodo' => MetodoPago::Transferencia->value,
            'referencia' => 'SPEI-REBOTADO', 'user_id' => $operadores[0] ?? null,
            'cancelado_en' => now(), 'cancelado_por' => $operadores[0] ?? null, 'motivo_cancelacion' => 'Transferencia rebotada (demo)',
        ]);
    }

    private function escenario(Cliente $c): string
    {
        return preg_match('/\[demo:([a-z_]+)\]/', (string) $c->notas, $m) ? $m[1] : 'al_corriente';
    }
}
