@extends('layouts.app')
@section('titulo', 'Inicio')

@section('contenido')
@php
    $c = $r['clientes']; $k = $r['cobranza']; $n = $r['red'];
    $mx = fn (float $v) => '$'.number_format($v, $v >= 1000 ? 0 : 2);
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Hola, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}</h1>
        <p class="text-sm text-slate-500">{{ now()->locale('es')->translatedFormat('l j \d\e F, Y') }} · resumen del día</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('clientes.index', ['nuevo' => 1]) }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">+ Nuevo cliente</a>
        <a href="{{ route('cobros.adeudos') }}" class="rounded-md border bg-white px-4 py-2 text-sm hover:bg-slate-50">Ver adeudos</a>
    </div>
</div>

{{-- Cobranza --}}
<h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Cobranza</h2>
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <a href="{{ route('cobros.pagos') }}" class="group rounded-xl border bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow">
        <div class="text-sm text-slate-500">Cobrado hoy</div>
        <div class="mt-1 text-3xl font-semibold tabular-nums text-emerald-700">{{ $mx($k['cobradoHoy']) }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $k['pagosHoy'] }} pago(s) registrados</div>
    </a>

    <a href="{{ route('cobros.pagos', ['desde' => now()->startOfMonth()->toDateString(), 'hasta' => now()->toDateString()]) }}"
       class="group rounded-xl border bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow">
        <div class="text-sm text-slate-500">Cobrado en el mes</div>
        <div class="mt-1 text-3xl font-semibold tabular-nums">{{ $mx($k['cobradoMes']) }}</div>
        @if ($k['avanceMes'] !== null)
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $k['avanceMes'] }}%"></div>
            </div>
            <div class="mt-1 text-xs text-slate-500">{{ $k['avanceMes'] }}% de {{ $mx($k['facturadoMes']) }} facturado</div>
        @else
            <div class="mt-1 text-xs text-slate-500">Sin mensualidades este mes</div>
        @endif
    </a>

    <a href="{{ route('cobros.adeudos') }}" class="group rounded-xl border bg-white p-5 shadow-sm transition hover:border-red-300 hover:shadow">
        <div class="text-sm text-slate-500">Adeudo vencido</div>
        <div @class(['mt-1 text-3xl font-semibold tabular-nums', 'text-red-700' => $k['vencido'] > 0, 'text-slate-400' => $k['vencido'] <= 0])>{{ $mx($k['vencido']) }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $k['deudores'] }} cliente(s) · {{ $k['enGracia'] }} en gracia · {{ $k['promesas'] }} con promesa</div>
    </a>

    <a href="{{ route('cobros.adeudos') }}" @class([
        'group rounded-xl border p-5 shadow-sm transition hover:shadow',
        'border-red-200 bg-red-50' => $k['seCortan'] > 0,
        'bg-white' => $k['seCortan'] === 0,
    ])>
        <div class="text-sm text-slate-500">Para corte automático</div>
        <div @class(['mt-1 text-3xl font-semibold tabular-nums', 'text-red-700' => $k['seCortan'] > 0])>{{ $k['seCortan'] }}</div>
        <div class="mt-1 text-xs {{ $k['corteAutomatico'] ? 'text-slate-500' : 'text-amber-700' }}">
            @if (! $k['corteAutomatico'])
                Corte automático apagado
            @elseif (now()->format('H:i') < $k['horaCorte'])
                Se cortan hoy a las {{ $k['horaCorte'] }}
            @else
                Próximo corte: mañana a las {{ $k['horaCorte'] }}
            @endif
        </div>
    </a>
</div>

{{-- Clientes --}}
<h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Clientes</h2>
<div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <a href="{{ route('clientes.index') }}" class="rounded-xl border bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow">
        <div class="text-sm text-slate-500">Clientes activos</div>
        <div class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($c['activos']) }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $c['nuevosMes'] }} alta(s) este mes</div>
    </a>
    <a href="{{ route('clientes.index', ['vista' => 'cortados']) }}" class="rounded-xl border bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow">
        <div class="text-sm text-slate-500">Con servicio cortado</div>
        <div @class(['mt-1 text-3xl font-semibold tabular-nums', 'text-red-700' => $c['cortados'] > 0])>{{ number_format($c['cortados']) }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $c['activos'] > 0 ? round($c['cortados'] / $c['activos'] * 100) : 0 }}% de los clientes</div>
    </a>
    <a href="{{ route('clientes.index', ['vista' => 'sin_onu']) }}" class="rounded-xl border bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow">
        <div class="text-sm text-slate-500">Sin ONU ligada</div>
        <div @class(['mt-1 text-3xl font-semibold tabular-nums', 'text-amber-700' => $c['sinOnu'] > 0])>{{ number_format($c['sinOnu']) }}</div>
        <div class="mt-1 text-xs text-slate-500">No se pueden cortar ni reconectar</div>
    </a>
    <a href="{{ route('cobros.adeudos') }}" class="rounded-xl border bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow">
        <div class="text-sm text-slate-500">Promesas de pago</div>
        <div class="mt-1 text-3xl font-semibold tabular-nums text-blue-700">{{ $k['promesas'] }}</div>
        <div class="mt-1 text-xs text-slate-500">Deben, pero no se cortan todavía</div>
    </a>
</div>

{{-- Red y soporte --}}
<h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Red y soporte</h2>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <a href="{{ route('fallas.index') }}" @class([
        'rounded-xl border p-5 shadow-sm transition hover:shadow',
        'border-amber-200 bg-amber-50' => $n['fallasAbiertas'] > 0,
        'bg-white' => $n['fallasAbiertas'] === 0,
    ])>
        <div class="text-sm text-slate-500">Fallas pendientes</div>
        <div class="mt-1 text-3xl font-semibold tabular-nums">{{ $n['fallasAbiertas'] + $n['fallasEnProceso'] }}</div>
        <div class="mt-1 text-xs text-slate-500">{{ $n['fallasAbiertas'] }} sin atender · {{ $n['fallasEnProceso'] }} en proceso</div>
    </a>
    <a href="{{ route('fallas.index', ['mias' => 1]) }}" class="rounded-xl border bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow">
        <div class="text-sm text-slate-500">Asignadas a mí</div>
        <div class="mt-1 text-3xl font-semibold tabular-nums">{{ $n['fallasMias'] }}</div>
        <div class="mt-1 text-xs text-slate-500">Fallas abiertas o en proceso</div>
    </a>
    <a href="{{ route('onus.index', ['estado' => 'problema']) }}" class="rounded-xl border bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow">
        <div class="text-sm text-slate-500">ONUs con problema de red</div>
        <div @class(['mt-1 text-3xl font-semibold tabular-nums', 'text-red-700' => $n['onusProblema'] > 0])>{{ $n['onusProblema'] }}</div>
        <div class="mt-1 text-xs text-slate-500">LOS, sin energía u offline (con servicio activo)</div>
    </a>
    <a href="{{ route('bitacora.index') }}" @class([
        'rounded-xl border p-5 shadow-sm transition hover:shadow',
        'border-red-200 bg-red-50' => $n['erroresSmartOlt'] > 0 || $n['syncAtrasada'],
        'bg-white' => ! ($n['erroresSmartOlt'] > 0 || $n['syncAtrasada']),
    ])>
        <div class="text-sm text-slate-500">SmartOLT</div>
        <div @class(['mt-1 text-3xl font-semibold tabular-nums', 'text-red-700' => $n['erroresSmartOlt'] > 0])>{{ $n['erroresSmartOlt'] }}</div>
        <div class="mt-1 text-xs text-slate-500">
            error(es) en 24 h · {{ $n['enCola'] }} en cola<br>
            <span @class(['text-red-700' => $n['syncAtrasada']])>
                Última sincronización: {{ $n['ultimaSync'] ? $n['ultimaSync']->diffForHumans() : 'nunca' }}
            </span>
            @if ($n['clientesOnuEliminada'] > 0)
                <br><span class="text-amber-700">{{ $n['clientesOnuEliminada'] }} cliente(s) con una ONU que ya no existe</span>
            @endif
        </div>
    </a>
</div>
@endsection
