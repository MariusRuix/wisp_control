@extends('layouts.app')
@section('titulo', 'Clientes')

@section('contenido')
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Clientes</h1>
        <p class="text-sm text-slate-500">
            {{ number_format($totales['activos']) }} activos · {{ number_format($totales['bajas']) }} de baja
        </p>
    </div>
    {{-- Abre el modal; sin JavaScript lleva a la página de alta --}}
    <a href="{{ route('clientes.create') }}" data-abrir-modal="modal-cliente-nuevo"
       class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">+ Nuevo cliente</a>
</div>

@include('clientes._modal_cliente', ['modo' => 'nuevo', 'cliente' => $nuevo, 'onus' => $onusDisponibles])

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre, número, teléfono, dirección, SN…"
           class="w-72 rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
    <select name="vista" class="rounded-md border px-3 py-2 text-sm" onchange="this.form.submit()">
        <option value="">Activos</option>
        <option value="cortados" @selected(request('vista') === 'cortados')>Con servicio cortado</option>
        <option value="sin_onu" @selected(request('vista') === 'sin_onu')>Sin ONU ligada</option>
        <option value="bajas" @selected(request('vista') === 'bajas')>De baja</option>
    </select>
    <button class="rounded-md border bg-white px-3 py-2 text-sm hover:bg-slate-50">Buscar</button>
</form>

<div class="overflow-x-auto rounded-lg border bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="px-3 py-2">Cliente</th>
            <th class="px-3 py-2">Contacto</th>
            <th class="px-3 py-2 text-right">Mensualidad</th>
            <th class="px-3 py-2 text-center">Día de pago</th>
            <th class="px-3 py-2 text-right">Saldo</th>
            <th class="px-3 py-2">ONU</th>
            <th class="px-3 py-2">Servicio</th>
        </tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($clientes as $c)
            <tr class="hover:bg-slate-50">
                <td class="px-3 py-2">
                    <a href="{{ route('clientes.show', $c) }}" class="font-medium text-blue-700 hover:underline">{{ $c->nombre }}</a>
                    <div class="text-xs text-slate-500">
                        @if ($c->numero)#{{ $c->numero }} · @endif{{ $c->direccion }}
                    </div>
                </td>
                <td class="px-3 py-2 text-xs">
                    <div>{{ $c->telefono }}</div>
                    <div class="text-slate-500">{{ $c->email }}</div>
                </td>
                <td class="px-3 py-2 text-right tabular-nums">${{ number_format((float) $c->mensualidad, 2) }}</td>
                <td class="px-3 py-2 text-center tabular-nums">{{ $c->dia_pago }}</td>
                @php($saldo = $c->saldo())
                <td @class(['px-3 py-2 text-right tabular-nums', 'font-medium text-red-700' => $c->vencido() > 0, 'text-emerald-700' => $saldo < 0])>
                    {{ $saldo < 0 ? '-' : '' }}${{ number_format(abs($saldo), 2) }}
                </td>
                <td class="px-3 py-2 font-mono text-xs">{{ $c->onu?->sn ?? '—' }}</td>
                <td class="px-3 py-2">@include('clientes._estado', ['cliente' => $c])</td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-3 py-10 text-center text-slate-500">
                No hay clientes en esta vista.
                <a href="{{ route('clientes.create') }}" data-abrir-modal="modal-cliente-nuevo" class="text-blue-700 hover:underline">Dar de alta el primero</a>
                o importa un CSV con <code class="rounded bg-slate-100 px-1">php artisan clientes:importar archivo.csv</code>.
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@include('partials.paginacion', ['p' => $clientes])
@endsection
