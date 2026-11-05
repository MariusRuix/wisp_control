<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Events\PagoRegistrado;
use App\Http\Requests\CancelacionRequest;
use App\Http\Requests\PagoRequest;
use App\Models\Cliente;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PagoController extends Controller
{
    /** Pagos recibidos en un rango (corte de caja). */
    public function index(Request $request): View
    {
        $desde = $this->fecha($request->input('desde')) ?? CarbonImmutable::today();
        $hasta = $this->fecha($request->input('hasta')) ?? $desde;
        $metodo = MetodoPago::tryFrom((string) $request->input('metodo'));

        $base = Pago::query()
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->when($metodo, fn ($q) => $q->where('metodo', $metodo->value));

        return view('cobros.pagos', [
            'pagos' => (clone $base)->with(['cliente', 'usuario'])->latest('fecha')->latest('id')->paginate($this->porPagina($request, 50))->withQueryString(),
            'total' => (float) (clone $base)->vigentes()->sum('monto'),
            'porMetodo' => (clone $base)->vigentes()->toBase()->selectRaw('metodo, SUM(monto) total, COUNT(*) n')->groupBy('metodo')->get(),
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }

    public function store(PagoRequest $request, Cliente $cliente): RedirectResponse
    {
        $pago = $cliente->pagos()->create([...$request->validated(), 'user_id' => $request->user()?->id]);

        PagoRegistrado::dispatch($pago);

        return redirect()->route('clientes.show', $cliente)
            ->with('ok', 'Pago de $'.number_format((float) $pago->monto, 2).' registrado.');
    }

    public function cancelar(CancelacionRequest $request, Pago $pago): RedirectResponse
    {
        if (! $pago->estaCancelado()) {
            $pago->update($request->datosCancelacion());
        }

        return redirect()->route('clientes.show', $pago->cliente_id)->with('aviso', 'Pago cancelado.');
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $valor)->startOfDay();
    }
}
