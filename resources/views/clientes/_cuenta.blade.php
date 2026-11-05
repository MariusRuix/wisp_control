@php
    $campo = 'w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none';
    $saldo = $resumen['saldo'];
@endphp

<section class="mt-4 rounded-lg border bg-white p-5" id="cuenta">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Estado de cuenta</h2>
        <div class="flex flex-wrap gap-6 text-right">
            <div>
                <div class="text-xs text-slate-500">{{ $saldo < 0 ? 'Saldo a favor' : 'Saldo' }}</div>
                <div @class(['text-xl font-semibold tabular-nums', 'text-emerald-700' => $saldo <= 0, 'text-slate-900' => $saldo > 0])>
                    ${{ number_format(abs($saldo), 2) }}
                </div>
            </div>
            <div>
                <div class="text-xs text-slate-500">Vencido</div>
                <div @class(['text-xl font-semibold tabular-nums', 'text-red-700' => $resumen['vencido'] > 0, 'text-slate-400' => $resumen['vencido'] <= 0])>
                    ${{ number_format($resumen['vencido'], 2) }}
                </div>
                @if ($resumen['dias_atraso'] > 0)
                    <div class="text-xs text-red-700">{{ $resumen['dias_atraso'] }} día(s) de atraso</div>
                @endif
                @if ($cliente->protegidoDeCorte())
                    <div class="text-xs text-blue-700">Promesa de pago hasta {{ $cliente->no_cortar_hasta->format('d/m/Y') }}</div>
                @endif
            </div>
        </div>
    </div>

    @unless ($cliente->trashed())
        <div class="mb-5 grid gap-4 lg:grid-cols-2">
            {{-- Registrar pago --}}
            <form method="POST" action="{{ route('pagos.store', $cliente) }}" class="rounded-md border border-emerald-200 bg-emerald-50/50 p-4">
                @csrf
                <h3 class="mb-3 text-sm font-semibold text-emerald-900">Registrar pago</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm"><span class="mb-1 block">Monto *</span>
                        <input name="monto" inputmode="decimal" required value="{{ old('monto', $saldo > 0 ? number_format($saldo, 2, '.', '') : '') }}" class="{{ $campo }}">
                    </label>
                    <label class="block text-sm"><span class="mb-1 block">Fecha *</span>
                        <input name="fecha" type="date" required max="{{ now()->toDateString() }}" value="{{ old('fecha', now()->toDateString()) }}" class="{{ $campo }}">
                    </label>
                    <label class="block text-sm"><span class="mb-1 block">Método *</span>
                        <select name="metodo" class="{{ $campo }}">
                            @foreach (\App\Enums\MetodoPago::cases() as $m)
                                <option value="{{ $m->value }}" @selected(old('metodo') === $m->value)>{{ $m->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm"><span class="mb-1 block">Referencia</span>
                        <input name="referencia" maxlength="100" value="{{ old('referencia') }}" placeholder="Folio, rastreo…" class="{{ $campo }}">
                    </label>
                </div>
                <button class="mt-3 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Guardar pago</button>
            </form>

            {{-- Cargo extra --}}
            <form method="POST" action="{{ route('cargos.store', $cliente) }}" class="rounded-md border p-4">
                @csrf
                <h3 class="mb-3 text-sm font-semibold">Agregar cargo extra</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-sm sm:col-span-2"><span class="mb-1 block">Concepto *</span>
                        <input name="concepto" required maxlength="255" placeholder="Instalación, saldo anterior, reconexión…" class="{{ $campo }}">
                    </label>
                    <label class="block text-sm"><span class="mb-1 block">Monto *</span>
                        <input name="monto" inputmode="decimal" required class="{{ $campo }}">
                    </label>
                    <label class="block text-sm"><span class="mb-1 block">Vence *</span>
                        <input name="vence_el" type="date" required value="{{ now()->toDateString() }}" class="{{ $campo }}">
                    </label>
                </div>
                <button class="mt-3 rounded-md border bg-white px-4 py-2 text-sm hover:bg-slate-50">Agregar cargo</button>
            </form>
        </div>
    @endunless

    @if ($movimientos->isEmpty())
        <p class="text-sm text-slate-500">Sin cargos ni pagos. La mensualidad se genera sola el día 1 de cada mes
            (o con <code class="rounded bg-slate-100 px-1">php artisan cobros:generar-cargos</code>).</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="py-2 pr-4">Fecha</th>
                    <th class="py-2 pr-4">Concepto</th>
                    <th class="py-2 pr-4 text-right">Cargo</th>
                    <th class="py-2 pr-4 text-right">Abono</th>
                    <th class="py-2 pr-4 text-right">Saldo</th>
                    <th class="py-2"></th>
                </tr>
                </thead>
                <tbody class="divide-y">
                @foreach ($movimientos as $m)
                    @php($obj = $m['modelo'])
                    @php($cancelado = $obj->estaCancelado())
                    <tr @class(['text-slate-400' => $cancelado])>
                        <td class="whitespace-nowrap py-2 pr-4 text-xs">{{ $m['fecha']->format('d/m/Y') }}</td>
                        <td class="py-2 pr-4">
                            @if ($m['tipo'] === 'cargo')
                                <span @class(['line-through' => $cancelado])>{{ $obj->concepto }}</span>
                                <span class="text-xs text-slate-400">· vence</span>
                            @else
                                <span @class(['line-through' => $cancelado])>Pago · {{ $obj->metodo->etiqueta() }}</span>
                                @if ($obj->referencia)<span class="text-xs text-slate-500">· {{ $obj->referencia }}</span>@endif
                                <span class="text-xs text-slate-400">· {{ $obj->usuario?->name }}</span>
                            @endif
                            @if ($cancelado)
                                <div class="text-xs text-red-700">Cancelado {{ $obj->cancelado_en->format('d/m/Y') }}: {{ $obj->motivo_cancelacion }}</div>
                            @endif
                        </td>
                        <td class="py-2 pr-4 text-right tabular-nums">{{ $m['tipo'] === 'cargo' ? '$'.number_format((float) $obj->monto, 2) : '' }}</td>
                        <td class="py-2 pr-4 text-right tabular-nums text-emerald-700">{{ $m['tipo'] === 'pago' ? '$'.number_format((float) $obj->monto, 2) : '' }}</td>
                        <td class="py-2 pr-4 text-right tabular-nums">${{ number_format($m['saldo'], 2) }}</td>
                        <td class="py-2 text-right">
                            @if (! $cancelado && ! $cliente->trashed() && auth()->user()->can('cancelar-movimientos'))
                                <details class="inline-block text-left">
                                    <summary class="cursor-pointer text-xs text-slate-500 hover:text-red-700">Cancelar</summary>
                                    <form method="POST" class="mt-2 flex gap-2"
                                          action="{{ $m['tipo'] === 'cargo' ? route('cargos.cancelar', $obj) : route('pagos.cancelar', $obj) }}">
                                        @csrf
                                        <input name="motivo" required minlength="3" maxlength="255" placeholder="Motivo"
                                               class="w-40 rounded-md border px-2 py-1 text-xs">
                                        <button class="rounded-md bg-red-600 px-2 py-1 text-xs text-white">OK</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $movimientos->links('vendor.pagination.wisp', ['ancla' => 'cuenta']) }}</div>
    @endif
</section>
