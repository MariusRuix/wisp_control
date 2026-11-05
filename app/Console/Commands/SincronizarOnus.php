<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\SmartOltException;
use App\Models\Onu;
use App\Services\SmartOltService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Copia la lista de ONUs de SmartOLT a la tabla local `onus`.
 *   php artisan smartolt:sincronizar           -> todo (paginado)
 *   php artisan smartolt:sincronizar --cambios -> solo lo actualizado desde la última vez
 *   php artisan smartolt:sincronizar --estados -> solo Online/LOS/Offline
 */
final class SincronizarOnus extends Command
{
    protected $signature = 'smartolt:sincronizar {--cambios : Solo ONUs actualizadas desde la última sincronización} {--estados : Solo refrescar estado de red} {--forzar : Marcar como eliminadas aunque falten muchas}';

    /** Si en una sincronización completa "desaparecen" más de este porcentaje, no se marca nada (posible falla de la API). */
    private const MAX_PORCENTAJE_ELIMINADAS = 20;

    protected $description = 'Sincroniza ONUs (clientes) desde SmartOLT';

    public function handle(SmartOltService $smartOlt): int
    {
        try {
            return $this->option('estados')
                ? $this->sincronizarEstados($smartOlt)
                : $this->sincronizarDetalles($smartOlt);
        } catch (SmartOltException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function sincronizarDetalles(SmartOltService $smartOlt): int
    {
        $filtros = [];
        if ($this->option('cambios') && ($ultima = Onu::max('sincronizado_en'))) {
            $filtros['updated_since'] = date('Y-m-d H:i:s', strtotime((string) $ultima) - 300);
        }

        $completa = ! $this->option('cambios');
        $inicio = now()->subSecond();
        $pagina = 1;
        $total = 0;
        $paginas = 1;

        do {
            $json = $smartOlt->detallesOnus($pagina, 100, $filtros);
            $paginas = (int) ($json['total_pages'] ?? $paginas);

            foreach ($json['onus'] ?? [] as $o) {
                if (empty($o['unique_external_id'])) {
                    continue;
                }

                Onu::updateOrCreate(
                    ['external_id' => (string) $o['unique_external_id']],
                    [
                        'sn' => $o['sn'] ?? null,
                        'nombre' => $o['name'] ?? null,
                        'direccion' => $o['address'] ?? null,
                        'contacto' => $o['contact'] ?? null,
                        'olt_id' => isset($o['olt_id']) ? (string) $o['olt_id'] : null,
                        'olt_nombre' => $o['olt_name'] ?? null,
                        'zona' => $o['zone_name'] ?? null,
                        'estado_admin' => $o['administrative_status'] ?? null,
                        'estado_red' => $o['status'] ?? null,
                        'sincronizado_en' => now(),
                        'eliminada_en' => null,   // si había desaparecido y volvió
                    ],
                );
                $total++;
            }

            $this->line("Página {$pagina}/{$paginas}");
            $pagina++;
        } while ($pagina <= $paginas);

        $this->info("ONUs sincronizadas: {$total}");

        if ($completa) {
            $this->marcarEliminadas($inicio, $total);
        }

        return self::SUCCESS;
    }

    /**
     * Solo tras una sincronización COMPLETA: las ONUs que no vinieron ya no existen en SmartOLT.
     */
    private function marcarEliminadas(\DateTimeInterface $inicio, int $recibidas): void
    {
        $faltantes = Onu::query()
            ->whereNull('eliminada_en')
            ->where(fn ($q) => $q->whereNull('sincronizado_en')->orWhere('sincronizado_en', '<', $inicio));

        $n = (clone $faltantes)->count();
        if ($n === 0) {
            return;
        }

        $existentes = $recibidas + $n;
        $porcentaje = (int) round($n / max(1, $existentes) * 100);

        if ($recibidas === 0 || ($porcentaje > self::MAX_PORCENTAJE_ELIMINADAS && $n > 5 && ! $this->option('forzar'))) {
            $this->warn("No marqué {$n} ONU(s) como eliminadas: serían el {$porcentaje}% y puede ser una falla de SmartOLT. "
                .'Revisa y, si es correcto, corre: php artisan smartolt:sincronizar --forzar');
            Log::warning('Sincronización SmartOLT: demasiadas ONUs faltantes, no se marcaron', compact('n', 'recibidas'));

            return;
        }

        $faltantes->update(['eliminada_en' => now()]);
        $this->warn("ONUs que ya no existen en SmartOLT (marcadas): {$n}");
        Log::info('Sincronización SmartOLT: ONUs marcadas como eliminadas', ['n' => $n]);
    }

    private function sincronizarEstados(SmartOltService $smartOlt): int
    {
        $json = $smartOlt->estadosOnus();
        $n = 0;

        foreach ($json['response'] ?? [] as $o) {
            if (! empty($o['unique_external_id'])) {
                $n += Onu::where('external_id', (string) $o['unique_external_id'])
                    ->update(['estado_red' => $o['status'] ?? null]);
            }
        }

        $this->info("Estados actualizados: {$n}");

        return self::SUCCESS;
    }
}
