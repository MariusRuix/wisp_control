<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\EstadoCuenta;
use App\Services\PoliticaCortes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AdeudoController extends Controller
{
    public function index(Request $request, EstadoCuenta $cuentas, PoliticaCortes $politica): View
    {
        $hoy = now()->toDateString();

        $base = Cliente::query()
            ->select('clientes.*')
            ->conSaldos($hoy)
            ->buscar($request->string('q')->toString());

        // Filtrar por el subselect: envolver para poder usar los alias en WHERE
        $consulta = Cliente::query()
            ->fromSub($base, 'clientes')
            ->whereRaw('cargos_vencidos - total_pagos > 0.004')
            ->with('onu.accionPendiente')
            ->orderByRaw('cargos_vencidos - total_pagos DESC');

        $totalVencido = (float) (clone $consulta)->toBase()->sum(DB::raw('cargos_vencidos - total_pagos'));
        $clientes = $consulta->paginate($this->porPagina($request))->withQueryString();

        $clientes->getCollection()->transform(function (Cliente $c) use ($cuentas, $politica) {
            $resumen = $cuentas->resumen($c);
            $c->setAttribute('resumen', $resumen);
            $c->setAttribute('decision', $politica->decidir($c, $resumen));

            return $c;
        });

        return view('cobros.adeudos', [
            'clientes' => $clientes,
            'totalVencido' => $totalVencido,
            'diasGracia' => (int) config('cobros.dias_gracia'),
            'corteAutomatico' => (bool) config('cobros.corte_automatico'),
            'horaCorte' => (string) config('cobros.hora_corte'),
        ]);
    }
}
