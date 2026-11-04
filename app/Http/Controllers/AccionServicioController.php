<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TipoAccion;
use App\Http\Requests\AccionServicioRequest;
use App\Models\AccionServicio;
use App\Services\ProgramadorCortes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccionServicioController extends Controller
{
    public function index(Request $request): View
    {
        $acciones = AccionServicio::query()
            ->with(['onu', 'usuario'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->string('estado')->toString()))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('bitacora.index', ['acciones' => $acciones]);
    }

    public function store(AccionServicioRequest $request, ProgramadorCortes $programador): RedirectResponse
    {
        $n = $programador->programar(
            $request->onuIds(),
            $request->tipo(),
            $request->user(),
            $request->validated('motivo'),
        );

        $verbo = $request->tipo() === TipoAccion::Cortar ? 'corte' : 'reconexión';

        return back()->with(
            $n > 0 ? 'ok' : 'aviso',
            $n > 0
                ? "Se enviaron {$n} solicitud(es) de {$verbo} a SmartOLT. Revisa la bitácora en unos segundos."
                : 'No se encoló nada: esos clientes ya tienen una acción pendiente.',
        );
    }
}
