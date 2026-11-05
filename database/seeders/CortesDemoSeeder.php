<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoAccion;
use App\Enums\TipoAccion;
use App\Models\AccionServicio;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Historial en la bitácora SIN llamar a SmartOLT: marca ONUs como cortadas
 * y escribe las acciones como si ya se hubieran aplicado.
 */
final class CortesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $operador = User::where('email', 'laura@wisp.test')->first();

        Cliente::query()->with('onu')->where('notas', 'like', '%[demo:cortado_%')->whereNotNull('onu_id')->each(function (Cliente $c) use ($operador) {
            $automatico = str_contains((string) $c->notas, '[demo:cortado_auto]');
            $cuando = now()->subDays(random_int(1, 6))->setTime(9, random_int(0, 5));

            AccionServicio::create([
                'onu_id' => $c->onu_id,
                'user_id' => $automatico ? null : $operador?->id,
                'tipo' => TipoAccion::Cortar,
                'estado' => EstadoAccion::Exito,
                'motivo' => $automatico ? 'Corte automático: adeudo vencido (demo)' : 'Corte manual: uso indebido (demo)',
                'automatica' => $automatico,
                'intentos' => 1,
                'ejecutada_en' => $cuando,
                'created_at' => $cuando,
                'updated_at' => $cuando,
            ]);

            $c->onu->update(['estado_admin' => 'Disabled', 'estado_red' => 'Online']);
        });

        // Un intento fallido (p. ej. SmartOLT no respondió) para que se vea en la bitácora
        $otro = Cliente::query()->where('notas', 'like', '%[demo:al_corriente]%')->whereNotNull('onu_id')->first();
        if ($otro) {
            AccionServicio::create([
                'onu_id' => $otro->onu_id, 'user_id' => $operador?->id, 'tipo' => TipoAccion::Reconectar,
                'estado' => EstadoAccion::Fallido, 'motivo' => 'Prueba', 'automatica' => false, 'intentos' => 5,
                'error' => 'SmartOLT: respuesta inesperada (HTTP 500) (demo)', 'ejecutada_en' => now()->subDays(2),
            ]);
        }
    }
}
