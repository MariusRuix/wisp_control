<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\EstadoAccion;
use App\Enums\TipoAccion;
use App\Exceptions\SmartOltException;
use App\Models\AccionServicio;
use App\Services\SmartOltService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Ejecuta en SmartOLT un corte o reconexión para 1..50 acciones.
 * Con una sola ONU usa el endpoint individual; con varias, el bulk.
 */
final class CambiarEstadoServicio implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> segundos entre reintentos */
    public array $backoff = [10, 30, 60, 120];

    /**
     * @param  list<int>  $accionIds
     */
    public function __construct(
        public readonly array $accionIds,
        public readonly TipoAccion $tipo,
    ) {
        $this->onQueue('smartolt');
    }

    public function handle(SmartOltService $smartOlt): void
    {
        $acciones = $this->accionesPendientes();
        if ($acciones->isEmpty()) {
            return;
        }

        $ids = $acciones->map(fn (AccionServicio $a) => $a->onu->external_id)->values()->all();
        $acciones->each(fn (AccionServicio $a) => $a->increment('intentos'));

        try {
            match (true) {
                count($ids) === 1 && $this->tipo === TipoAccion::Cortar => $smartOlt->deshabilitar($ids[0]),
                count($ids) === 1 => $smartOlt->habilitar($ids[0]),
                $this->tipo === TipoAccion::Cortar => $smartOlt->deshabilitarVarias($ids),
                default => $smartOlt->habilitarVarias($ids),
            };
        } catch (SmartOltException $e) {
            if ($e->esLimiteDePeticiones()) {
                $this->release($e->retryAfter ?? 60);

                return;
            }

            if ($e->esReintentable()) {
                throw $e; // la cola reintenta con $backoff
            }

            $this->marcarFallidas($acciones, $e->getMessage());
            $this->fail($e);

            return;
        }

        $acciones->each(function (AccionServicio $a): void {
            $a->update(['estado' => EstadoAccion::Exito, 'error' => null, 'ejecutada_en' => now()]);
            $a->onu->update(['estado_admin' => $this->tipo->estadoResultante()]);
        });
    }

    public function failed(?Throwable $e): void
    {
        $this->marcarFallidas($this->accionesPendientes(), $e?->getMessage() ?? 'Error desconocido');
    }

    /**
     * @return Collection<int, AccionServicio>
     */
    private function accionesPendientes(): Collection
    {
        return AccionServicio::query()
            ->with('onu')
            ->whereKey($this->accionIds)
            ->where('estado', EstadoAccion::EnCola->value)
            ->get();
    }

    /**
     * @param  Collection<int, AccionServicio>  $acciones
     */
    private function marcarFallidas(Collection $acciones, string $error): void
    {
        $acciones->each(fn (AccionServicio $a) => $a->update([
            'estado' => EstadoAccion::Fallido,
            'error' => mb_substr($error, 0, 1000),
            'ejecutada_en' => now(),
        ]));
    }
}
