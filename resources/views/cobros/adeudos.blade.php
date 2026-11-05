@extends('layouts.app')
@section('titulo', 'Adeudos')

@section('contenido')
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Adeudos vencidos</h1>
        <p class="text-sm text-slate-500">
            {{ number_format($clientes->total()) }} cliente(s) ·
            <span class="font-medium text-red-700">${{ number_format($totalVencido, 2) }}</span> vencido ·
            gracia antes de corte: {{ $diasGracia }} día(s)
        </p>
        <p @class(['mt-1 text-xs', 'text-emerald-700' => $corteAutomatico, 'text-amber-700' => ! $corteAutomatico])>
            {{ $corteAutomatico ? "Corte automático ACTIVO: diario a las {$horaCorte}." : 'Corte automático APAGADO: la columna "Corte automático" muestra lo que haría.' }}
        </p>
    </div>
    <form method="GET" class="flex gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar cliente…"
               class="w-64 rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
        <button class="rounded-md border bg-white px-3 py-2 text-sm hover:bg-slate-50">Buscar</button>
    </form>
</div>

<div class="overflow-x-auto rounded-lg border bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="px-3 py-2">Cliente</th>
            <th class="px-3 py-2">Teléfono</th>
            <th class="px-3 py-2 text-right">Vencido</th>
            <th class="px-3 py-2 text-right">Saldo total</th>
            <th class="px-3 py-2 text-right">Atraso</th>
            <th class="px-3 py-2">Servicio</th>
            <th class="px-3 py-2">Corte automático</th>
        </tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($clientes as $c)
            @php($r = $c->resumen)
            <tr class="hover:bg-slate-50">
                <td class="px-3 py-2">
                    <a href="{{ route('clientes.show', $c) }}#cuenta" class="font-medium text-blue-700 hover:underline">{{ $c->nombre }}</a>
                    <div class="text-xs text-slate-500">@if ($c->numero)#{{ $c->numero }} · @endif{{ $c->direccion }}</div>
                </td>
                <td class="px-3 py-2 text-xs">{{ $c->telefono }}</td>
                <td class="px-3 py-2 text-right font-medium tabular-nums text-red-700">${{ number_format($r['vencido'], 2) }}</td>
                <td class="px-3 py-2 text-right tabular-nums">${{ number_format($r['saldo'], 2) }}</td>
                <td class="px-3 py-2 text-right">
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs tabular-nums',
                        'bg-red-100 text-red-800' => $r['dias_atraso'] > $diasGracia,
                        'bg-amber-100 text-amber-800' => $r['dias_atraso'] <= $diasGracia,
                    ])>{{ $r['dias_atraso'] }} día(s)</span>
                </td>
                <td class="px-3 py-2">@include('clientes._estado', ['cliente' => $c])</td>
                <td class="px-3 py-2">
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $c->decision->color() }}">{{ $c->decision->etiqueta() }}</span>
                    @if ($c->decision === \App\Enums\DecisionCorte::Promesa)
                        <div class="text-xs text-slate-500">hasta {{ $c->no_cortar_hasta->format('d/m/Y') }}</div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-3 py-10 text-center text-slate-500">Nadie tiene adeudo vencido.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@include('partials.paginacion', ['p' => $clientes])
@endsection
