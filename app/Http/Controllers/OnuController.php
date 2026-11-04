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
            ->with('accionPendiente')
            ->buscar($request->string('q')->toString())
            ->when($estado === 'cortados', fn ($q) => $q->where('estado_admin', 'Disabled'))
            ->when($estado === 'activos', fn ($q) => $q->where('estado_admin', '!=', 'Disabled'))
            ->when($zona !== '', fn ($q) => $q->where('zona', $zona))
            ->orderBy('nombre')
            ->paginate(50)
            ->withQueryString();

        return view('onus.index', [
            'onus' => $onus,
            'zonas' => Onu::query()->whereNotNull('zona')->distinct()->orderBy('zona')->pluck('zona'),
            'totales' => [
                'todos' => Onu::count(),
                'cortados' => Onu::where('estado_admin', 'Disabled')->count(),
            ],
        ]);
    }
}
