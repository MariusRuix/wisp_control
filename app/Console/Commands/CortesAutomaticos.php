<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DecisionCorte;
use App\Services\PoliticaCortes;
use Illuminate\Console\Command;

/**
 *   php artisan cortes:automaticos --prueba   -> solo muestra a quién cortaría
 *   php artisan cortes:automaticos            -> encola los cortes (requiere COBROS_CORTE_AUTOMATICO=true)
 */
final class CortesAutomaticos extends Command
{
    protected $signature = 'cortes:automaticos {--prueba : No corta, solo muestra la lista}';

    protected $description = 'Corta en SmartOLT a los clientes con adeudo vencido fuera de gracia';

    public function handle(PoliticaCortes $politica): int
    {
        $prueba = (bool) $this->option('prueba');

        if (! $prueba && ! config('cobros.corte_automatico')) {
            $this->warn('Corte automático desactivado (COBROS_CORTE_AUTOMATICO=false). Usa --prueba para ver la lista.');

            return self::SUCCESS;
        }

        $evaluacion = $politica->evaluar();

        $this->table(
            ['Cliente', 'Vencido', 'Atraso', 'Decisión'],
            $evaluacion->map(fn ($f) => [
                $f['cliente']->nombre,
                '$'.number_format($f['resumen']['vencido'], 2),
                $f['resumen']['dias_atraso'].' d',
                $f['decision']->etiqueta(),
            ])->all(),
        );

        $n = $evaluacion->where('decision', DecisionCorte::Cortar)->count();

        if ($prueba) {
            $this->info("PRUEBA: se cortaría a {$n} cliente(s). No se hizo nada.");

            return self::SUCCESS;
        }

        $cortados = $politica->ejecutar();
        $this->info("Cortes enviados a la cola: {$cortados->count()}");

        return self::SUCCESS;
    }
}
