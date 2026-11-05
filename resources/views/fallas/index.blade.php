@extends('layouts.app')
@section('titulo', 'Fallas')

@section('contenido')
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Reportes de fallas</h1>
        <p class="text-sm text-slate-500">
            <span class="text-red-700">{{ $conteo['abierta'] }} abierta(s)</span> ·
            <span class="text-amber-700">{{ $conteo['en_proceso'] }} en proceso</span>
            · Para reportar una falla, abre la ficha del cliente.
        </p>
    </div>
    <form method="GET" class="flex flex-wrap items-center gap-2 text-sm">
        <select name="estado" class="rounded-md border px-3 py-2" onchange="this.form.submit()">
            <option value="">Pendientes (abiertas y en proceso)</option>
            @foreach (\App\Enums\EstadoFalla::cases() as $e)
                <option value="{{ $e->value }}" @selected($estado === $e)>{{ $e->etiqueta() }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-1 text-slate-600">
            <input type="checkbox" name="mias" value="1" @checked(request()->boolean('mias')) onchange="this.form.submit()"> Solo las mías
        </label>
    </form>
</div>

<div class="overflow-x-auto rounded-lg border bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="px-3 py-2">Reportada</th>
            <th class="px-3 py-2">Cliente</th>
            <th class="px-3 py-2">Descripción</th>
            <th class="px-3 py-2">ONU</th>
            <th class="px-3 py-2">Atiende</th>
            <th class="px-3 py-2">Estado</th>
            <th class="px-3 py-2"></th>
        </tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($fallas as $f)
            <tr class="align-top hover:bg-slate-50">
                <td class="whitespace-nowrap px-3 py-2 text-xs">
                    {{ $f->reportada_el->format('d/m/Y H:i') }}
                    @unless ($f->estaResuelta())
                        <div class="text-slate-400">hace {{ $f->reportada_el->diffForHumans(null, true) }}</div>
                    @endunless
                </td>
                <td class="px-3 py-2">
                    <a href="{{ route('clientes.show', $f->cliente_id) }}#fallas" class="font-medium text-blue-700 hover:underline">{{ $f->cliente?->nombre }}</a>
                    <div class="text-xs text-slate-500">{{ $f->cliente?->telefono }} · {{ $f->cliente?->direccion }}</div>
                </td>
                <td class="max-w-md px-3 py-2">
                    <div class="line-clamp-3 whitespace-pre-line">{{ $f->descripcion }}</div>
                    @if ($f->solucion)<div class="mt-1 text-xs text-emerald-800"><b>Solución:</b> {{ $f->solucion }}</div>@endif
                </td>
                <td class="px-3 py-2 text-xs">
                    <div class="font-mono">{{ $f->cliente?->onu?->sn ?? '—' }}</div>
                    <div class="text-slate-500">ahora: {{ $f->cliente?->onu?->estado_red ?? 'N/D' }}</div>
                    @if ($f->estado_red_al_reportar)<div class="text-slate-400">al reportar: {{ $f->estado_red_al_reportar }}</div>@endif
                </td>
                <td class="px-3 py-2 text-xs">{{ $f->asignado?->name ?? 'Sin asignar' }}</td>
                <td class="px-3 py-2"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs {{ $f->estado->color() }}">{{ $f->estado->etiqueta() }}</span></td>
                <td class="px-3 py-2 text-right">
                    <a href="{{ route('fallas.edit', $f) }}" class="rounded-md border bg-white px-3 py-1 text-xs hover:bg-slate-50">Actualizar</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-3 py-10 text-center text-slate-500">No hay fallas en esta vista.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@include('partials.paginacion', ['p' => $fallas])
@endsection
