@extends('layouts.app')
@section('titulo', 'Bitácora')

@section('contenido')
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Bitácora de cortes y reconexiones</h1>
        <p class="text-sm text-slate-500">Quién hizo qué, cuándo y qué respondió SmartOLT.</p>
    </div>
    <form method="GET" class="flex gap-2">
        <select name="estado" class="rounded-md border px-3 py-2 text-sm" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            @foreach (\App\Enums\EstadoAccion::cases() as $e)
                <option value="{{ $e->value }}" @selected(request('estado') === $e->value)>{{ $e->etiqueta() }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="overflow-x-auto rounded-lg border bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="px-3 py-2">Fecha</th>
            <th class="px-3 py-2">Cliente</th>
            <th class="px-3 py-2">Acción</th>
            <th class="px-3 py-2">Estado</th>
            <th class="px-3 py-2">Operador</th>
            <th class="px-3 py-2">Motivo / Error</th>
        </tr>
        </thead>
        <tbody class="divide-y">
        @forelse ($acciones as $a)
            <tr>
                <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-500">{{ $a->created_at->format('d/m/Y H:i') }}</td>
                <td class="px-3 py-2">
                    @if ($a->onu?->cliente)
                        <a href="{{ route('clientes.show', $a->onu->cliente) }}" class="font-medium text-blue-700 hover:underline">{{ $a->onu->cliente->nombre }}</a>
                    @else
                        <div class="font-medium">{{ $a->onu?->nombre ?? '—' }}</div>
                    @endif
                    <div class="font-mono text-xs text-slate-400">{{ $a->onu?->sn }}</div>
                </td>
                <td class="px-3 py-2">{{ $a->tipo->etiqueta() }}</td>
                <td class="px-3 py-2">
                    <span class="rounded-full px-2 py-0.5 text-xs {{ $a->estado->color() }}">{{ $a->estado->etiqueta() }}</span>
                    @if ($a->intentos > 1)<span class="text-xs text-slate-400">({{ $a->intentos }} intentos)</span>@endif
                </td>
                <td class="px-3 py-2 text-xs">
                    {{ $a->usuario?->name ?? 'Sistema' }}
                    @if ($a->automatica)<span class="ml-1 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-800">AUTO</span>@endif
                </td>
                <td class="px-3 py-2 text-xs">
                    <div>{{ $a->motivo }}</div>
                    @if ($a->error)<div class="text-red-700">{{ $a->error }}</div>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-3 py-10 text-center text-slate-500">Aún no hay movimientos.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@include('partials.paginacion', ['p' => $acciones])
@endsection
