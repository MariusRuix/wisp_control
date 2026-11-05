<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DecisionCorte;
use App\Enums\TipoAccion;
use App\Models\Cliente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Regla única de corte por cobranza:
 *   se corta si tiene adeudo vencido, días de atraso > días de gracia,
 *   ONU ligada y activa, sin acción en cola y sin promesa de pago vigente.
 */
final class PoliticaCortes
{
    public function __construct(
        private readonly EstadoCuenta $cuentas,
        private readonly ProgramadorCortes $programador,
    ) {
    }

    public function decidir(Cliente $cliente, array $resumen, ?CarbonImmutable $hoy = null): DecisionCorte
    {
        $hoy ??= CarbonImmutable::now();
        $cliente->loadMissing('onu.accionPendiente');

        return match (true) {
            $cliente->onu === null, ! $cliente->onu->existeEnSmartOlt() => DecisionCorte::SinOnu,
            $cliente->onu->accionPendiente !== null => DecisionCorte::EnCola,
            $cliente->onu->estaCortada() => DecisionCorte::YaCortado,
            $cliente->protegidoDeCorte($hoy) => DecisionCorte::Promesa,
            $resumen['vencido'] <= 0 || $resumen['dias_atraso'] <= (int) config('cobros.dias_gracia') => DecisionCorte::EnGracia,
            default => DecisionCorte::Cortar,
        };
    }

    /**
     * Todos los clientes activos con adeudo vencido y su decisión.
     *
     * @return Collection<int, array{cliente: Cliente, resumen: array, decision: DecisionCorte}>
     */
    public function evaluar(?CarbonImmutable $hoy = null): Collection
    {
        $hoy ??= CarbonImmutable::now();

        $base = Cliente::query()->select('clientes.*')->conSaldos($hoy->toDateString());

        return Cliente::query()
            ->fromSub($base, 'clientes')
            ->whereRaw('cargos_vencidos - total_pagos > 0.004')
            ->with('onu.accionPendiente')
            ->orderBy('nombre')
            ->get()
            ->map(function (Cliente $c) use ($hoy) {
                $resumen = $this->cuentas->resumen($c, $hoy->startOfDay());

                return ['cliente' => $c, 'resumen' => $resumen, 'decision' => $this->decidir($c, $resumen, $hoy)];
            });
    }

    /**
     * Encola los cortes. Devuelve los clientes cortados.
     *
     * @return Collection<int, array{cliente: Cliente, resumen: array, decision: DecisionCorte}>
     */
    public function ejecutar(?CarbonImmutable $hoy = null): Collection
    {
        $aCortar = $this->evaluar($hoy)->filter(fn ($f) => $f['decision'] === DecisionCorte::Cortar)->values();

        if ($aCortar->isNotEmpty()) {
            $this->programador->programar(
                $aCortar->map(fn ($f) => $f['cliente']->onu_id)->all(),
                TipoAccion::Cortar,
                null,
                'Corte automático: adeudo vencido (más de '.config('cobros.dias_gracia').' días de gracia)',
                automatica: true,
            );
        }

        return $aCortar;
    }
}
