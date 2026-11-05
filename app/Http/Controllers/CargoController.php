<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelacionRequest;
use App\Http\Requests\CargoRequest;
use App\Models\Cargo;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;

final class CargoController extends Controller
{
    public function store(CargoRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->cargos()->create([...$request->validated(), 'periodo' => null, 'user_id' => $request->user()?->id]);

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Cargo agregado.');
    }

    /** Cancelar una mensualidad evita que se vuelva a generar ese mes. */
    public function cancelar(CancelacionRequest $request, Cargo $cargo): RedirectResponse
    {
        if (! $cargo->estaCancelado()) {
            $cargo->update($request->datosCancelacion());
        }

        return redirect()->route('clientes.show', $cargo->cliente_id)->with('aviso', 'Cargo cancelado.');
    }
}
