@extends('layouts.app')
@section('titulo', $cliente->nombre)

@section('contenido')
@php($onu = $cliente->onu)
<a href="{{ route('clientes.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Clientes</a>

<div class="mb-5 mt-1 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="flex items-center gap-3 text-xl font-semibold">
            {{ $cliente->nombre }} @include('clientes._estado')
        </h1>
        <p class="text-sm text-slate-500">
            @if ($cliente->numero)Cliente #{{ $cliente->numero }} · @endif
            Alta {{ $cliente->fecha_alta?->format('d/m/Y') }}
            @if ($cliente->trashed()) · Baja {{ $cliente->deleted_at->format('d/m/Y') }} @endif
        </p>
    </div>

    <div class="flex flex-wrap gap-2">
        @if ($cliente->trashed())
            <form method="POST" action="{{ route('clientes.restore', $cliente->id) }}">@csrf
                <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Reactivar</button>
            </form>
        @else
            @if ($onu && ! $onu->accionPendiente && $onu->existeEnSmartOlt())
                @php($cortada = $onu->estaCortada())
                <form method="POST" action="{{ route('acciones.store') }}"
                      onsubmit="return confirm({{ Js::from(($cortada ? '¿Reconectar' : '¿Cortar').' el servicio de '.$cliente->nombre.'?') }})">
                    @csrf
                    <input type="hidden" name="onus[]" value="{{ $onu->id }}">
                    <input type="hidden" name="tipo" value="{{ $cortada ? 'reconectar' : 'cortar' }}">
                    <button @class([
                        'rounded-md px-4 py-2 text-sm font-medium text-white',
                        'bg-emerald-600 hover:bg-emerald-700' => $cortada,
                        'bg-red-600 hover:bg-red-700' => ! $cortada,
                    ])>{{ $cortada ? 'Reconectar servicio' : 'Cortar servicio' }}</button>
                </form>
            @endif
            <a href="{{ route('clientes.edit', $cliente) }}" data-abrir-modal="modal-cliente-editar" class="rounded-md border bg-white px-4 py-2 text-sm hover:bg-slate-50">Editar</a>
            <form method="POST" action="{{ route('clientes.destroy', $cliente) }}"
                  onsubmit="return confirm({{ Js::from('¿Dar de baja a '.$cliente->nombre.'? Su ONU quedará libre para otro cliente. No se corta el servicio automáticamente.') }})">
                @csrf @method('DELETE')
                <button class="rounded-md border border-red-200 bg-white px-4 py-2 text-sm text-red-700 hover:bg-red-50">Dar de baja</button>
            </form>
        @endif
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <section class="rounded-lg border bg-white p-5 lg:col-span-2">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Datos</h2>
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Teléfono</dt><dd>{{ $cliente->telefono ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Correo</dt><dd>{{ $cliente->email ?: '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-slate-500">Dirección</dt><dd>{{ $cliente->direccion ?: '—' }}</dd></div>
            <div><dt class="text-slate-500">Mensualidad</dt><dd class="text-base font-semibold tabular-nums">${{ number_format((float) $cliente->mensualidad, 2) }}</dd></div>
            <div><dt class="text-slate-500">Día de pago</dt><dd class="text-base font-semibold">{{ $cliente->dia_pago }} de cada mes</dd></div>
            @if ($cliente->notas)
                <div class="sm:col-span-2"><dt class="text-slate-500">Notas</dt><dd class="whitespace-pre-line">{{ $cliente->notas }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="rounded-lg border bg-white p-5">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">ONU</h2>
        @if ($onu)
            @unless ($onu->existeEnSmartOlt())
                <div class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Esta ONU ya no existe en SmartOLT (desde {{ $onu->eliminada_en->format('d/m/Y') }}). No se puede cortar ni reconectar:
                    @unless ($cliente->trashed())<a href="{{ route('clientes.edit', $cliente) }}" data-abrir-modal="modal-cliente-editar" class="font-medium underline">liga la ONU nueva</a>.@endunless
                </div>
            @endunless
            <dl class="space-y-2 text-sm">
                <div><dt class="text-slate-500">SN</dt><dd class="font-mono">{{ $onu->sn }}</dd></div>
                <div><dt class="text-slate-500">ID SmartOLT</dt><dd class="font-mono text-xs">{{ $onu->external_id }}</dd></div>
                <div><dt class="text-slate-500">Zona / OLT</dt><dd>{{ $onu->zona ?: '—' }} · {{ $onu->olt_nombre ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Red</dt><dd>{{ $onu->estado_red ?: 'N/D' }}</dd></div>
                <div><dt class="text-slate-500">Servicio</dt><dd>
                    @if ($onu->accionPendiente)
                        <span class="text-amber-700">{{ $onu->accionPendiente->tipo->etiqueta() }} en cola…</span>
                    @else
                        {{ $onu->estaCortada() ? 'Cortado' : 'Activo' }}
                    @endif
                </dd></div>
            </dl>
        @else
            <p class="text-sm text-slate-500">Sin ONU ligada.
                @unless ($cliente->trashed())<a href="{{ route('clientes.edit', $cliente) }}" data-abrir-modal="modal-cliente-editar" class="text-blue-700 hover:underline">Ligar una</a>@endunless
            </p>
        @endif
    </section>
</div>

@include('clientes._cuenta')

@include('clientes._fallas')

<section class="mt-4 rounded-lg border bg-white p-5" id="cortes">
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Cortes y reconexiones</h2>
    @if ($acciones->isEmpty())
        <p class="text-sm text-slate-500">Sin movimientos.</p>
    @else
        <table class="min-w-full text-sm">
            <tbody class="divide-y">
            @foreach ($acciones as $a)
                <tr>
                    <td class="whitespace-nowrap py-2 pr-4 text-xs text-slate-500">{{ $a->created_at->format('d/m/Y H:i') }}</td>
                    <td class="py-2 pr-4">{{ $a->tipo->etiqueta() }}</td>
                    <td class="py-2 pr-4"><span class="rounded-full px-2 py-0.5 text-xs {{ $a->estado->color() }}">{{ $a->estado->etiqueta() }}</span></td>
                    <td class="py-2 pr-4 text-xs">{{ $a->usuario?->name ?? 'Sistema' }}@if ($a->automatica) · auto @endif</td>
                    <td class="py-2 text-xs">{{ $a->motivo }} @if ($a->error)<span class="text-red-700">{{ $a->error }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="mt-3">{{ $acciones->links('vendor.pagination.wisp', ['ancla' => 'cortes']) }}</div>
    @endif
</section>
@unless ($cliente->trashed())
    @include('clientes._modal_cliente', ['modo' => 'editar', 'cliente' => $cliente, 'onus' => $onusDisponibles])
@endunless
@endsection
