<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cargo;
use App\Models\Cliente;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Crea la mensualidad del mes para cada cliente activo.
 * Es idempotente: correrlo varias veces en el mismo mes no duplica cargos
 * (índice único cliente_id + periodo).
 *
 * Regla: el cargo del mes M vence el "día de pago" de M y se crea solo si
 * el cliente ya estaba dado de alta en esa fecha.
 */
final class GeneradorCargos
{
    /**
     * @return int cargos creados
     */
    public function generar(CarbonImmutable $mes): int
    {
        $periodo = $mes->startOfMonth();
        $creados = 0;

        Cliente::query()
            ->where('mensualidad', '>', 0)
            ->whereDate('fecha_alta', '<=', $periodo->endOfMonth()->toDateString())
            ->whereDoesntHave('cargos', fn ($q) => $q->whereDate('periodo', $periodo->toDateString()))
            ->chunkById(200, function ($clientes) use ($periodo, &$creados): void {
                foreach ($clientes as $cliente) {
                    $vence = $periodo->setDay(min(28, max(1, (int) $cliente->dia_pago)));

                    if ($cliente->fecha_alta->gt($vence)) {
                        continue; // entró después de su día de pago: empieza el mes siguiente
                    }

                    try {
                        Cargo::create([
                            'cliente_id' => $cliente->id,
                            'periodo' => $periodo->toDateString(),
                            'concepto' => 'Mensualidad '.$this->nombreMes($periodo),
                            'monto' => $cliente->mensualidad,
                            'vence_el' => $vence->toDateString(),
                        ]);
                        $creados++;
                    } catch (UniqueConstraintViolationException) {
                        // otro proceso lo creó al mismo tiempo
                    }
                }
            });

        return $creados;
    }

    private function nombreMes(CarbonImmutable $fecha): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
            'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return $meses[$fecha->month - 1].' '.$fecha->year;
    }
}
