@extends('layouts.app')
@section('titulo', 'Falla #'.$falla->id)

@section('contenido')
@php
    $campo = 'w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none';
    $volverCliente = request('volver') === 'cliente' || old('volver') === 'cliente';
@endphp
<div class="mx-auto max-w-2xl">
    <a href="{{ $volverCliente ? route('clientes.show', $falla->cliente_id).'#fallas' : route('fallas.index') }}"
       class="text-sm text-slate-500 hover:text-slate-800">← {{ $volverCliente ? $falla->cliente?->nombre : 'Fallas' }}</a>

    <h1 class="mb-1 mt-1 flex items-center gap-3 text-xl font-semibold">
        Falla #{{ $falla->id }}
        <span class="rounded-full px-2 py-0.5 text-xs font-normal {{ $falla->estado->color() }}">{{ $falla->estado->etiqueta() }}</span>
    </h1>
    <p class="mb-5 text-sm text-slate-500">
        <a href="{{ route('clientes.show', $falla->cliente_id) }}" class="text-blue-700 hover:underline">{{ $falla->cliente?->nombre }}</a>
        · {{ $falla->cliente?->telefono }} · reportada {{ $falla->reportada_el->format('d/m/Y H:i') }} por {{ $falla->creador?->name ?? '—' }}
        @if ($falla->cliente?->onu) · ONU {{ $falla->cliente->onu->sn }} ({{ $falla->cliente->onu->estado_red ?: 'N/D' }} ahora@if ($falla->estado_red_al_reportar), {{ $falla->estado_red_al_reportar }} al reportar@endif) @endif
    </p>

    <form method="POST" action="{{ route('fallas.update', $falla) }}" class="space-y-4 rounded-lg border bg-white p-5">
        @csrf @method('PUT')
        <input type="hidden" name="volver" value="{{ $volverCliente ? 'cliente' : '' }}">

        <label class="block text-sm"><span class="mb-1 block font-medium">Descripción</span>
            <textarea name="descripcion" rows="3" required maxlength="5000" class="{{ $campo }}">{{ old('descripcion', $falla->descripcion) }}</textarea>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm"><span class="mb-1 block font-medium">Estado *</span>
                <select name="estado" id="estado" class="{{ $campo }}">
                    @foreach (\App\Enums\EstadoFalla::cases() as $e)
                        <option value="{{ $e->value }}" @selected(old('estado', $falla->estado->value) === $e->value)>{{ $e->etiqueta() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm"><span class="mb-1 block font-medium">Quién la atiende</span>
                <select name="asignado_a" class="{{ $campo }}">
                    <option value="">— Sin asignar —</option>
                    @foreach ($operadores as $op)
                        <option value="{{ $op->id }}" @selected((string) old('asignado_a', $falla->asignado_a) === (string) $op->id)>{{ $op->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="block text-sm"><span class="mb-1 block font-medium">Solución <span class="font-normal text-slate-500">(obligatoria para marcarla resuelta)</span></span>
            <textarea name="solucion" rows="3" maxlength="5000" placeholder="Se reinició la ONU, se cambió el conector…" class="{{ $campo }}">{{ old('solucion', $falla->solucion) }}</textarea>
        </label>

        @if ($falla->resuelta_el)
            <p class="text-xs text-slate-500">Resuelta el {{ $falla->resuelta_el->format('d/m/Y H:i') }}. Si la cambias a otro estado, se reabre.</p>
        @endif

        <div class="flex justify-end">
            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Guardar</button>
        </div>
    </form>
</div>
@endsection
