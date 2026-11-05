<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoAccion;
use App\Enums\TipoAccion;
use App\Jobs\CambiarEstadoServicio;
use App\Models\AccionServicio;
use App\Models\Onu;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registra en la bitácora y encola cortes/reconexiones.
 * Los controladores solo llaman a esto.
 */
final class ProgramadorCortes
{
    /**
     * @param  list<int>  $onuIds
     * @param  bool  $automatica  true = la generó el sistema (corte/reconexión por cobranza)
     * @return int número de acciones encoladas
     */
    public function programar(array $onuIds, TipoAccion $tipo, ?User $usuario, ?string $motivo = null, bool $automatica = false): int
    {
        $onus = Onu::query()
            ->whereKey($onuIds)
            ->whereNull('eliminada_en') // ya no existen en SmartOLT: la orden fallaría
            // no duplicar si ya hay una acción en cola para esa ONU
            ->whereDoesntHave('acciones', fn ($q) => $q->where('estado', EstadoAccion::EnCola->value))
            ->get();

        if ($onus->isEmpty()) {
            return 0;
        }

        $accionIds = DB::transaction(fn () => $onus->map(fn (Onu $onu) => AccionServicio::create([
            'onu_id' => $onu->id,
            'user_id' => $usuario?->id,
            'tipo' => $tipo,
            'estado' => EstadoAccion::EnCola,
            'motivo' => $motivo,
            'automatica' => $automatica,
        ])->id)->all());

        foreach (array_chunk($accionIds, (int) config('smartolt.bulk_chunk', 50)) as $lote) {
            CambiarEstadoServicio::dispatch($lote, $tipo)->afterCommit();
        }

        return count($accionIds);
    }
}
