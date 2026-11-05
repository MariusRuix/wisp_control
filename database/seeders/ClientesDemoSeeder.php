<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Onu;
use Illuminate\Database\Seeder;

/**
 * 40 clientes ficticios. Cada uno lleva en "notas" el escenario que
 * CobrosDemoSeeder y CortesDemoSeeder le van a armar.
 */
final class ClientesDemoSeeder extends Seeder
{
    /** escenario => cuántos clientes */
    public const ESCENARIOS = [
        'al_corriente' => 18,
        'debe_mes_actual' => 8,   // según el día de pago: en gracia, por vencer o para corte
        'atrasado' => 5,          // debe 2 meses: se corta
        'promesa' => 2,           // atrasado con promesa de pago
        'cortado_auto' => 2,      // ya cortado por el sistema
        'cortado_manual' => 1,    // cortado por un operador
        'saldo_a_favor' => 1,
        'sin_onu' => 2,
        'baja' => 1,
    ];

    public function run(): void
    {
        $faker = fake();
        $onus = Onu::query()->where('external_id', 'like', 'DEMO-%')
            ->whereNotIn('id', Cliente::withTrashed()->whereNotNull('onu_id')->select('onu_id'))
            ->orderBy('id')->get();
        $n = Cliente::withTrashed()->where('numero', 'like', 'DEMO-%')->count();

        foreach (self::ESCENARIOS as $escenario => $cantidad) {
            for ($i = 0; $i < $cantidad; $i++) {
                $n++;
                $onu = $escenario === 'sin_onu' ? null : $onus->shift();

                $cliente = Cliente::create([
                    'numero' => sprintf('DEMO-C%03d', $n),
                    'nombre' => DatosMexico::nombreCompleto(),
                    'telefono' => $faker->numerify('55########'),
                    'email' => $faker->boolean(60) ? 'cliente'.$n.'@ejemplo.test' : null,
                    'direccion' => $onu?->direccion ?? DatosMexico::direccion(),
                    'mensualidad' => $faker->randomElement([300, 350, 350, 400, 450, 500, 600]),
                    'dia_pago' => $faker->randomElement([1, 5, 5, 10, 15, 15, 20, 25]),
                    'fecha_alta' => now()->subMonths(random_int(4, 24))->startOfMonth()->addDays(random_int(0, 20))->toDateString(),
                    'onu_id' => $onu?->id,
                    'no_cortar_hasta' => $escenario === 'promesa' ? now()->addDays(random_int(3, 10))->toDateString() : null,
                    'notas' => "[demo:{$escenario}]",
                ]);

                if ($escenario === 'baja') {
                    $cliente->update(['onu_id' => null, 'notas' => "[demo:{$escenario}]\nBaja de prueba"]);
                    $cliente->delete();
                }
            }
        }
    }
}
