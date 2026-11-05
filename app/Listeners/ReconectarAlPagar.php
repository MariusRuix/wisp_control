<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\EstadoAccion;
use App\Enums\TipoAccion;
use App\Events\PagoRegistrado;
use App\Models\AccionServicio;
use App\Services\EstadoCuenta;
use App\Services\ProgramadorCortes;

/**
 * Al registrar un pago, si el cliente ya no tiene adeudo vencido:
 *  - anula un corte automático que siga en cola (aún no llegó a SmartOLT);
 *  - o, si su último corte fue automático, encola la reconexión.
 * Un corte hecho a mano por un operador nunca se revierte aquí.
 */
final class ReconectarAlPagar
{
    public function __construct(
        private readonly EstadoCuenta $cuentas,
        private readonly ProgramadorCortes $programador,
    ) {
    }

    public function handle(PagoRegistrado $evento): void
    {
        if (! config('cobros.reconexion_automatica')) {
            return;
        }

        $cliente = $evento->pago->cliente()->with('onu')->first();
        $onu = $cliente?->onu;

        if ($onu === null || $cliente->trashed() || $this->cuentas->resumen($cliente)['vencido'] > 0) {
            return;
        }

        $ultima = AccionServicio::query()->where('onu_id', $onu->id)->latest('id')->first();

        if ($ultima === null || ! $ultima->automatica || $ultima->tipo !== TipoAccion::Cortar) {
            return;
        }

        if ($ultima->estado === EstadoAccion::EnCola) {
            $ultima->update([
                'estado' => EstadoAccion::Anulado,
                'error' => 'Anulado: el cliente pagó antes de que se aplicara el corte.',
                'ejecutada_en' => now(),
            ]);

            return;
        }

        if ($ultima->estado === EstadoAccion::Exito && $onu->estaCortada()) {
            $this->programador->programar(
                [$onu->id],
                TipoAccion::Reconectar,
                $evento->pago->usuario,
                'Reconexión automática: pago registrado',
                automatica: true,
            );
        }
    }
}
