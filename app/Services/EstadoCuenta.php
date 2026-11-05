<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cargo;
use App\Models\Cliente;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cálculos de cuenta de un cliente. Los pagos se aplican a los cargos más
 * antiguos primero (FIFO), sin guardar la aplicación: se deduce de los totales.
 */
final class EstadoCuenta
{
    /**
     * @return array{saldo: float, vencido: float, dias_atraso: int, vence_mas_antiguo: ?CarbonImmutable}
     */
    public function resumen(Cliente $cliente, ?CarbonImmutable $hoy = null): array
    {
        $hoy ??= CarbonImmutable::today();

        $cargos = $cliente->cargos()->vigentes()->orderBy('vence_el')->orderBy('id')->get(['monto', 'vence_el']);
        $pagado = (float) $cliente->pagos()->vigentes()->sum('monto');
        $totalCargos = (float) $cargos->sum('monto');

        $restante = $pagado;
        $primerPendiente = null;
        foreach ($cargos as $c) {
            $restante -= (float) $c->monto;
            if ($restante < -0.004) {
                $primerPendiente = CarbonImmutable::parse($c->vence_el);
                break;
            }
        }

        $vencidos = (float) $cargos->filter(fn ($c) => $c->vence_el->lt($hoy))->sum('monto');
        $atraso = $primerPendiente !== null && $primerPendiente->lt($hoy)
            ? (int) $primerPendiente->diffInDays($hoy)
            : 0;

        return [
            'saldo' => round($totalCargos - $pagado, 2),
            'vencido' => max(0.0, round($vencidos - $pagado, 2)),
            'dias_atraso' => $atraso,
            'vence_mas_antiguo' => $primerPendiente,
        ];
    }

    /**
     * Cargos y pagos en orden cronológico con saldo acumulado (más reciente primero).
     *
     * @return Collection<int, array{fecha: CarbonImmutable, tipo: string, modelo: Cargo|Pago, cargo: float, abono: float, saldo: ?float}>
     */
    public function movimientos(Cliente $cliente, int $limite = 50): Collection
    {
        $cargos = $cliente->cargos()->with('usuario')->get()->map(fn (Cargo $c) => [
            'fecha' => CarbonImmutable::parse($c->vence_el),
            'tipo' => 'cargo',
            'modelo' => $c,
            'cargo' => $c->estaCancelado() ? 0.0 : (float) $c->monto,
            'abono' => 0.0,
            'orden' => $c->created_at?->getTimestamp() ?? 0,
        ]);

        $pagos = $cliente->pagos()->with('usuario')->get()->map(fn (Pago $p) => [
            'fecha' => CarbonImmutable::parse($p->fecha),
            'tipo' => 'pago',
            'modelo' => $p,
            'cargo' => 0.0,
            'abono' => $p->estaCancelado() ? 0.0 : (float) $p->monto,
            'orden' => $p->created_at?->getTimestamp() ?? 0,
        ]);

        $saldo = 0.0;

        return $cargos->concat($pagos)
            ->sortBy([['fecha', 'asc'], ['orden', 'asc']])
            ->values()
            ->map(function (array $m) use (&$saldo) {
                $saldo = round($saldo + $m['cargo'] - $m['abono'], 2);
                $m['saldo'] = $saldo;

                return $m;
            })
            ->reverse()
            ->take($limite)
            ->values();
    }
}
