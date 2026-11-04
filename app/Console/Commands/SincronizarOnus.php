<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\SmartOltException;
use App\Models\Onu;
use App\Services\SmartOltService;
use Illuminate\Console\Command;

/**
 * Copia la lista de ONUs de SmartOLT a la tabla local `onus`.
 *   php artisan smartolt:sincronizar           -> todo (paginado)
 *   php artisan smartolt:sincronizar --cambios -> solo lo actualizado desde la última vez
 *   php artisan smartolt:sincronizar --estados -> solo Online/LOS/Offline
 */
final class SincronizarOnus extends Command
{
    protected $signature = 'smartolt:sincronizar {--cambios : Solo ONUs actualizadas desde la última sincronización} {--estados : Solo refrescar estado de red}';

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
                    ],
                );
                $total++;
            }

            $this->line("Página {$pagina}/{$paginas}");
            $pagina++;
        } while ($pagina <= $paginas);

        $this->info("ONUs sincronizadas: {$total}");

        return self::SUCCESS;
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
