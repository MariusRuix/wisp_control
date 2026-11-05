{{--
    Pie de las listas principales: "Mostrando X–Y de Z", selector de registros por página y páginas.
    @include('partials.paginacion', ['p' => $clientes])
--}}
@php($opciones = \App\Http\Controllers\Controller::OPCIONES_POR_PAGINA)
<div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
    <div class="flex flex-wrap items-center gap-3">
        <span>
            @if ($p->total() > 0)
                Mostrando <b>{{ number_format($p->firstItem()) }}–{{ number_format($p->lastItem()) }}</b> de <b>{{ number_format($p->total()) }}</b>
            @else
                Sin resultados
            @endif
        </span>
        @if ($p->total() > min($opciones))
            <label class="flex items-center gap-1">
                <span class="text-slate-500">Por página</span>
                <select class="rounded-md border bg-white px-2 py-1"
                        onchange="window.location = this.value">
                    @foreach ($opciones as $n)
                        <option value="{{ request()->fullUrlWithQuery(['por_pagina' => $n, $p->getPageName() => null]) }}" @selected($p->perPage() === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>
    {{ $p->links() }}
</div>
