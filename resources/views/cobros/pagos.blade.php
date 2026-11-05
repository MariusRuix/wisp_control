@extends('layouts.app')
@section('titulo', 'Pagos recibidos')

@section('contenido')
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Pagos recibidos</h1>
        <p class="text-sm text-slate-500">
            {{ $desde->format('d/m/Y') }}@if (! $desde->eq($hasta)) al {{ $hasta->format('d/m/Y') }}@endif ·
            total <span class="font-semibold text-emerald-700">${{ number_format($total, 2) }}</span>
        </p>
    </div>
    <form method="GET" class="flex flex-wrap items-end gap-2 text-sm">
        <label>Desde <input type="date" name="desde" value="{{ $desde->toDateString() }}" class="rounded-md border px-3 py-2"></label>
        <label>Hasta <input type="date" name="hasta" value="{{ $hasta->toDateString() }}" class="rounded-md border px-3 py-2"></label>
        <select name="metodo" class="rounded-md border px-3 py-2">
            <option value="">Todos los métodos</option>
            @foreach (\App\Enums\MetodoPago::cases() as $m)
                <option value="{{ $m->value }}" @selected(request('metodo') === $m->value)>{{ $m->etiqueta() }}</option>
            @endforeach
        </select>
        <button class="rounded-md border bg-white px-3 py-2 hover:bg-slate-50">Ver</button>
    </form>
</div>

@if ($porMetodo->isNotEmpty())
    <div class="mb-4 flex flex-wrap gap-3">
        @foreach ($porMetodo as $fila)
            <div class="rounded-lg border bg-white px-4 py-2 text-sm">
                <div class="text-xs text-slate-500">{{ \App\Enums\MetodoPago::tryFrom($fila->metodo)?->etiqueta() ?? $fila->metodo }} · {{ $fila->n }}</div>
                <div class="font-semibold tabular-nums">${{ number_format((float) $fila->total, 2) }}</div>
            </div>
        @endforeach
    </div>
@endif

<div class="overflow-x-auto rounded-lg border bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="px-3 py-2">Fecha</th>
            <th class="px-3 py-2">Cliente</th>
            <th class="px-3 py-2">Método</th>
            <th class="px-3 py-2">Referencia</th>
            <th class="px-3 py-2">Recibió</th>
            <th class="px-3 py-2 text-right">Monto</th>
        </tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($pagos as $p)
            <tr @class(['text-slate-400' => $p->estaCancelado()])>
                <td class="whitespace-nowrap px-3 py-2 text-xs">{{ $p->fecha->format('d/m/Y') }}</td>
                <td class="px-3 py-2">
                    <a href="{{ route('clientes.show', $p->cliente_id) }}#cuenta" class="text-blue-700 hover:underline">{{ $p->cliente?->nombre }}</a>
                    @if ($p->estaCancelado())<div class="text-xs text-red-700">Cancelado: {{ $p->motivo_cancelacion }}</div>@endif
                </td>
                <td class="px-3 py-2">{{ $p->metodo->etiqueta() }}</td>
                <td class="px-3 py-2 text-xs">{{ $p->referencia }}</td>
                <td class="px-3 py-2 text-xs">{{ $p->usuario?->name }}</td>
                <td @class(['px-3 py-2 text-right tabular-nums', 'line-through' => $p->estaCancelado()])>${{ number_format((float) $p->monto, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-3 py-10 text-center text-slate-500">No hay pagos en este periodo.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@include('partials.paginacion', ['p' => $pagos])
@endsection
