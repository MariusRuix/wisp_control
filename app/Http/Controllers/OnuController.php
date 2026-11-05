<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Onu;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OnuController extends Controller
{
    public function index(Request $request): View
    {
        $estado = $request->string('estado')->toString();
        $zona = $request->string('zona')->toString();

        $onus = Onu::query()
            ->with(['accionPendiente', 'cliente'])
            ->buscar($request->string('q')->toString())
            ->when($estado !== 'eliminadas', fn ($q) => $q->vigentes())
            ->when($estado === 'eliminadas', fn ($q) => $q->whereNotNull('eliminada_en'))
            ->when($estado === 'sin_cliente', fn ($q) => $q->whereDoesntHave('cliente'))
            ->when($estado === 'cortados', fn ($q) => $q->where('estado_admin', 'Disabled'))
            ->when($estado === 'activos', fn ($q) => $q->where('estado_admin', '!=', 'Disabled'))
            ->when($estado === 'problema', fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('estado_admin')->orWhere('estado_admin', '!=', 'Disabled'))
                ->whereNotNull('estado_red')->where('estado_red', '!=', 'Online'))
            ->when($zona !== '', fn ($q) => $q->where('zona', $zona))
            ->orderBy('nombre')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return view('onus.index', [
            'onus' => $onus,
            'zonas' => Onu::query()->whereNotNull('zona')->distinct()->orderBy('zona')->pluck('zona'),
            'totales' => [
                'todos' => Onu::vigentes()->count(),
                'cortados' => Onu::vigentes()->where('estado_admin', 'Disabled')->count(),
                'sinCliente' => Onu::vigentes()->whereDoesntHave('cliente')->count(),
                'eliminadas' => Onu::whereNotNull('eliminada_en')->count(),
            ],
        ]);
    }
}
