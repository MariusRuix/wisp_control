<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoFalla;
use App\Http\Requests\FallaRequest;
use App\Models\Cliente;
use App\Models\Falla;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FallaController extends Controller
{
    /** Por defecto: abiertas y en proceso, la más antigua primero. */
    public function index(Request $request): View
    {
        $estado = EstadoFalla::tryFrom((string) $request->input('estado'));
        $soloMias = $request->boolean('mias');

        $fallas = Falla::query()
            ->with(['cliente.onu', 'asignado'])
            ->when($estado, fn ($q) => $q->where('estado', $estado->value), fn ($q) => $q->pendientes())
            ->when($soloMias, fn ($q) => $q->where('asignado_a', $request->user()->id))
            ->when($estado === EstadoFalla::Resuelta, fn ($q) => $q->latest('resuelta_el'), fn ($q) => $q->oldest('reportada_el'))
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return view('fallas.index', [
            'fallas' => $fallas,
            'estado' => $estado,
            'conteo' => [
                EstadoFalla::Abierta->value => Falla::where('estado', EstadoFalla::Abierta->value)->count(),
                EstadoFalla::EnProceso->value => Falla::where('estado', EstadoFalla::EnProceso->value)->count(),
            ],
        ]);
    }

    public function store(FallaRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->loadMissing('onu');

        $cliente->fallas()->create([
            'descripcion' => $request->validated('descripcion'),
            'reportada_el' => $request->validated('reportada_el'),
            'estado' => $request->validated('asignado_a') ? EstadoFalla::EnProceso : EstadoFalla::Abierta,
            'asignado_a' => $request->validated('asignado_a'),
            'creado_por' => $request->user()->id,
            'estado_red_al_reportar' => $cliente->onu?->estado_red,
        ]);

        return redirect()->route('clientes.show', $cliente)->withFragment('fallas')->with('ok', 'Falla registrada.');
    }

    public function edit(Falla $falla): View
    {
        $falla->load(['cliente.onu', 'asignado', 'creador']);

        return view('fallas.edit', [
            'falla' => $falla,
            'operadores' => User::paraAsignar($falla->asignado_a),
        ]);
    }

    public function update(FallaRequest $request, Falla $falla): RedirectResponse
    {
        $nuevo = EstadoFalla::from((string) $request->validated('estado'));

        $falla->update([
            ...$request->safe()->only(['descripcion', 'asignado_a', 'solucion']),
            'estado' => $nuevo,
            // Fecha de cierre al resolver; se limpia si se reabre
            'resuelta_el' => $nuevo === EstadoFalla::Resuelta ? ($falla->resuelta_el ?? now()) : null,
        ]);

        return redirect()->to($request->input('volver') === 'cliente'
            ? route('clientes.show', $falla->cliente_id).'#fallas'
            : route('fallas.index'))
            ->with('ok', 'Falla actualizada: '.$nuevo->etiqueta().'.');
    }
}
