<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GeneradorCargos;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 *   php artisan cobros:generar-cargos              -> mes actual
 *   php artisan cobros:generar-cargos --mes=2026-10 -> un mes específico (p. ej. si el servidor estuvo apagado)
 */
final class GenerarCargos extends Command
{
    protected $signature = 'cobros:generar-cargos {--mes= : Mes en formato AAAA-MM}';

    protected $description = 'Genera la mensualidad del mes para los clientes activos';

    public function handle(GeneradorCargos $generador): int
    {
        $mes = $this->option('mes');

        if ($mes !== null && ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $mes)) {
            $this->error('Usa el formato AAAA-MM, por ejemplo 2026-11.');

            return self::FAILURE;
        }

        $fecha = $mes ? CarbonImmutable::createFromFormat('Y-m-d', $mes.'-01') : CarbonImmutable::today();
        $n = $generador->generar($fecha);

        $this->info("Cargos creados para {$fecha->format('Y-m')}: {$n}");

        return self::SUCCESS;
    }
}
