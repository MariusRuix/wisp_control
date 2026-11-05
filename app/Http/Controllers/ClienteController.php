<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\AccionServicio;
use App\Models\Cliente;
use App\Models\Onu;
use App\Models\User;
use App\Services\EstadoCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $vista = $request->string('vista')->toString();

        $clientes = Cliente::query()
            ->select('clientes.*')
            ->conSaldos()
            ->with('onu')
            ->when($vista === 'bajas', fn ($q) => $q->onlyTrashed())
            ->when($vista === 'sin_onu', fn ($q) => $q->whereNull('onu_id'))
            ->when($vista === 'cortados', fn ($q) => $q->whereHas('onu', fn ($o) => $o->where('estado_admin', 'Disabled')))
            ->buscar($request->string('q')->toString())
            ->orderBy('nombre')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return view('clientes.index', [
            'clientes' => $clientes,
            // Para el modal de "Nuevo cliente"
            'nuevo' => $this->clienteNuevo(),
            'onusDisponibles' => $this->onusDisponibles(),
            'totales' => [
                'activos' => Cliente::count(),
                'bajas' => Cliente::onlyTrashed()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('clientes.create', [
            'cliente' => $this->clienteNuevo(),
            'onus' => $this->onusDisponibles(),
        ]);
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        $cliente = Cliente::create($request->validated());

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Cliente dado de alta.');
    }

    public function show(Request $request, Cliente $cliente, EstadoCuenta $cuenta): View
    {
        $cliente->load('onu.accionPendiente');

        // Cada sección de la ficha pagina por separado (su propio parámetro en la URL)
        $acciones = AccionServicio::query()->with('usuario')
            ->when($cliente->onu_id, fn ($q, $id) => $q->where('onu_id', $id), fn ($q) => $q->whereRaw('1 = 0'))
            ->latest()->latest('id')
            ->paginate(10, ['*'], 'cortes_pag')->withQueryString();

        $todos = $cuenta->movimientos($cliente, PHP_INT_MAX);
        $paginaMov = Paginator::resolveCurrentPage('mov_pag');
        $movimientos = (new LengthAwarePaginator($todos->forPage($paginaMov, 15)->values(), $todos->count(), 15, $paginaMov, [
            'path' => $request->url(),
            'pageName' => 'mov_pag',
        ]))->withQueryString();

        return view('clientes.show', [
            'cliente' => $cliente,
            'acciones' => $acciones,
            'resumen' => $cuenta->resumen($cliente),
            'movimientos' => $movimientos,
            'fallas' => $cliente->fallas()->with(['asignado', 'creador'])->latest('reportada_el')->latest('id')
                ->paginate(10, ['*'], 'fallas_pag')->withQueryString(),
            'operadores' => User::paraAsignar(),
            // Para el modal de "Editar"
            'onusDisponibles' => $cliente->trashed() ? collect() : $this->onusDisponibles($cliente),
        ]);
    }

    public function edit(Cliente $cliente): View
    {
        return view('clientes.edit', [
            'cliente' => $cliente,
            'onus' => $this->onusDisponibles($cliente),
        ]);
    }

    public function update(ClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Cambios guardados.');
    }

    /** Baja: libera la ONU (para poder reasignarla) y deja constancia en notas. */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        $nota = 'Baja '.now()->format('d/m/Y H:i');
        if ($cliente->onu) {
            $nota .= ' — se liberó la ONU '.($cliente->onu->sn ?? $cliente->onu->external_id);
        }

        $cliente->update([
            'onu_id' => null,
            'notas' => trim(($cliente->notas ?? '')."\n".$nota),
        ]);
        $cliente->delete();

        return redirect()->route('clientes.index')->with('ok', "Cliente «{$cliente->nombre}» dado de baja.");
    }

    public function restore(int $id): RedirectResponse
    {
        $cliente = Cliente::onlyTrashed()->findOrFail($id);
        $cliente->restore();

        return redirect()->route('clientes.show', [$cliente, 'editar' => 1])
            ->with('aviso', 'Cliente reactivado. Si sigue con servicio, vuelve a ligar su ONU.');
    }

    private function clienteNuevo(): Cliente
    {
        return new Cliente(['fecha_alta' => now(), 'dia_pago' => min(28, (int) now()->format('j'))]);
    }

    /**
     * ONUs sin cliente (más la actual del cliente que se edita).
     *
     * @return Collection<int, Onu>
     */
    private function onusDisponibles(?Cliente $cliente = null): Collection
    {
        return Onu::query()
            ->where(fn ($q) => $q->whereNull('eliminada_en')
                ->whereNotIn('id', Cliente::query()->whereNotNull('onu_id')->select('onu_id')))
            ->when($cliente?->onu_id, fn ($q, $id) => $q->orWhere('id', $id))
            ->orderBy('nombre')
            ->get(['id', 'sn', 'nombre', 'zona', 'external_id']);
    }
}
