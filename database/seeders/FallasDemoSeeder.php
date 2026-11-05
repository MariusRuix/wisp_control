<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoFalla;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Seeder;

final class FallasDemoSeeder extends Seeder
{
    private const REPORTES = [
        ['Sin internet desde la mañana, luz LOS en rojo.', 'Fibra dañada en la acometida; se empalmó.'],
        ['Internet muy lento en las noches.', 'Se cambió el canal del WiFi y se reinició la ONU.'],
        ['Se va el internet cada rato.', 'Conector sucio, se limpió y se re-ponchó.'],
        ['No prende el módem.', 'Eliminador dañado, se reemplazó.'],
        ['No llega señal WiFi al segundo piso.', 'Se recomendó repetidor; el cliente lo compró.'],
        ['Cambió la contraseña del WiFi y ya no se acuerda.', 'Se restableció la contraseña desde SmartOLT.'],
        ['Sin servicio después de la tormenta.', null],
        ['La televisión por internet se traba.', null],
    ];

    public function run(): void
    {
        $tecnico = User::where('email', 'luis@wisp.test')->first();
        $registra = User::where('email', 'laura@wisp.test')->first();
        $clientes = Cliente::query()->with('onu')->where('numero', 'like', 'DEMO-%')->whereNotNull('onu_id')->inRandomOrder()->limit(count(self::REPORTES) + 2)->get();

        foreach (self::REPORTES as $i => [$descripcion, $solucion]) {
            $cliente = $clientes[$i] ?? null;
            if ($cliente === null) {
                break;
            }

            // Las primeras 5 resueltas, luego 1 en proceso, el resto abiertas
            $estado = match (true) {
                $solucion !== null && $i < 5 => EstadoFalla::Resuelta,
                $i === 5 || $i === 7 => EstadoFalla::EnProceso,
                default => EstadoFalla::Abierta,
            };
            $reportada = now()->subDays(random_int(0, 20))->subHours(random_int(0, 10));

            $cliente->fallas()->create([
                'descripcion' => $descripcion,
                'reportada_el' => $reportada,
                'estado' => $estado,
                'asignado_a' => $estado === EstadoFalla::Abierta ? null : $tecnico?->id,
                'creado_por' => $registra?->id,
                'estado_red_al_reportar' => $estado === EstadoFalla::Resuelta ? 'LOS' : $cliente->onu?->estado_red,
                'solucion' => $estado === EstadoFalla::Resuelta ? $solucion : null,
                'resuelta_el' => $estado === EstadoFalla::Resuelta ? $reportada->copy()->addHours(random_int(2, 30))->min(now()) : null,
            ]);
        }
    }
}
