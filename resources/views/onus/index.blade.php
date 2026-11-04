@extends('layouts.app')
@section('titulo', 'Clientes')

@section('contenido')
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Clientes</h1>
        <p class="text-sm text-slate-500">
            {{ number_format($totales['todos']) }} ONUs ·
            <span class="text-red-700">{{ number_format($totales['cortados']) }} cortadas</span>
        </p>
    </div>

    <form method="GET" class="flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre, SN, dirección, ID…"
               class="w-64 rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
        <select name="estado" class="rounded-md border px-3 py-2 text-sm">
            <option value="">Todos</option>
            <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
            <option value="cortados" @selected(request('estado') === 'cortados')>Cortados</option>
        </select>
        @if ($zonas->isNotEmpty())
            <select name="zona" class="rounded-md border px-3 py-2 text-sm">
                <option value="">Todas las zonas</option>
                @foreach ($zonas as $z)
                    <option value="{{ $z }}" @selected(request('zona') === $z)>{{ $z }}</option>
                @endforeach
            </select>
        @endif
        <button class="rounded-md border bg-white px-3 py-2 text-sm hover:bg-slate-50">Buscar</button>
    </form>
</div>

{{-- Formulario de acciones masivas: los checkboxes de la tabla apuntan aquí con form="lote" --}}
<form id="lote" method="POST" action="{{ route('acciones.store') }}"
      class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border bg-white px-4 py-3 text-sm"
      onsubmit="return confirmarLote(event)">
    @csrf
    <span class="text-slate-600"><b id="seleccionados">0</b> seleccionados</span>
    <input type="text" name="motivo" maxlength="255" placeholder="Motivo (opcional): falta de pago, baja…"
           class="min-w-56 flex-1 rounded-md border px-3 py-1.5">
    <button name="tipo" value="cortar" class="rounded-md bg-red-600 px-3 py-1.5 font-medium text-white hover:bg-red-700">Cortar seleccionados</button>
    <button name="tipo" value="reconectar" class="rounded-md bg-emerald-600 px-3 py-1.5 font-medium text-white hover:bg-emerald-700">Reconectar seleccionados</button>
</form>

<div class="overflow-x-auto rounded-lg border bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="px-3 py-2"><input type="checkbox" id="todos" aria-label="Seleccionar todos"></th>
            <th class="px-3 py-2">Cliente</th>
            <th class="px-3 py-2">SN / ID</th>
            <th class="px-3 py-2">Zona / OLT</th>
            <th class="px-3 py-2">Red</th>
            <th class="px-3 py-2">Servicio</th>
            <th class="px-3 py-2 text-right">Acción</th>
        </tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($onus as $onu)
            @php($cortada = $onu->estaCortada())
            <tr class="{{ $cortada ? 'bg-red-50/40' : '' }}">
                <td class="px-3 py-2">
                    <input type="checkbox" name="onus[]" value="{{ $onu->id }}" form="lote" class="sel">
                </td>
                <td class="px-3 py-2">
                    <div class="font-medium">{{ $onu->nombre ?: '—' }}</div>
                    <div class="text-xs text-slate-500">{{ $onu->direccion }}</div>
                    @if ($onu->cliente_externo_id)
                        <div class="text-xs text-slate-400">Cliente #{{ $onu->cliente_externo_id }}</div>
                    @endif
                </td>
                <td class="px-3 py-2 font-mono text-xs">
                    <div>{{ $onu->sn }}</div>
                    <div class="text-slate-400">{{ $onu->external_id }}</div>
                </td>
                <td class="px-3 py-2 text-xs">
                    <div>{{ $onu->zona }}</div>
                    <div class="text-slate-400">{{ $onu->olt_nombre }}</div>
                </td>
                <td class="px-3 py-2">
                    @php($red = $onu->estado_red)
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs',
                        'bg-emerald-100 text-emerald-800' => $red === 'Online',
                        'bg-red-100 text-red-800' => $red === 'LOS',
                        'bg-amber-100 text-amber-800' => $red === 'Power fail',
                        'bg-slate-100 text-slate-600' => ! in_array($red, ['Online', 'LOS', 'Power fail'], true),
                    ])>{{ $red ?: 'N/D' }}</span>
                </td>
                <td class="px-3 py-2">
                    @if ($onu->accionPendiente)
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">
                            {{ $onu->accionPendiente->tipo->etiqueta() }} en cola…
                        </span>
                    @elseif ($cortada)
                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Cortado</span>
                    @else
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Activo</span>
                    @endif
                </td>
                <td class="px-3 py-2 text-right">
                    @unless ($onu->accionPendiente)
                        <form method="POST" action="{{ route('acciones.store') }}" class="inline"
                              onsubmit="return confirm({{ Js::from(($cortada ? '¿Reconectar' : '¿Cortar').' el servicio de '.($onu->nombre ?: $onu->sn).'?') }})">
                            @csrf
                            <input type="hidden" name="onus[]" value="{{ $onu->id }}">
                            <input type="hidden" name="tipo" value="{{ $cortada ? 'reconectar' : 'cortar' }}">
                            <button @class([
                                'rounded-md px-3 py-1 text-xs font-medium text-white',
                                'bg-emerald-600 hover:bg-emerald-700' => $cortada,
                                'bg-red-600 hover:bg-red-700' => ! $cortada,
                            ])>{{ $cortada ? 'Reconectar' : 'Cortar' }}</button>
                        </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-3 py-10 text-center text-slate-500">
                No hay clientes. Ejecuta <code class="rounded bg-slate-100 px-1">php artisan smartolt:sincronizar</code>.
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $onus->links() }}</div>

<script>
    const casillas = () => [...document.querySelectorAll('.sel')];
    const contar = () => document.getElementById('seleccionados').textContent = casillas().filter(c => c.checked).length;
    document.getElementById('todos').addEventListener('change', e => { casillas().forEach(c => c.checked = e.target.checked); contar(); });
    casillas().forEach(c => c.addEventListener('change', contar));

    function confirmarLote(e) {
        const n = casillas().filter(c => c.checked).length;
        const accion = e.submitter?.value === 'reconectar' ? 'RECONECTAR' : 'CORTAR';
        if (n === 0) { alert('Selecciona al menos un cliente.'); return false; }
        return confirm(`¿${accion} el servicio de ${n} cliente(s)?`);
    }
</script>
@endsection
