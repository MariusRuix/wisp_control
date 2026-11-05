{{-- Paginación del panel (en español). Uso: $lista->links() o ->links('vendor.pagination.wisp', ['ancla' => 'seccion']) --}}
@php($ancla = isset($ancla) && $ancla ? '#'.$ancla : '')
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="flex flex-wrap items-center gap-1 text-sm">
        @if ($paginator->onFirstPage())
            <span class="rounded-md border px-3 py-1.5 text-slate-300">&laquo; Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}{{ $ancla }}" rel="prev" class="rounded-md border bg-white px-3 py-1.5 hover:bg-slate-50">&laquo; Anterior</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-slate-400">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="rounded-md border border-blue-600 bg-blue-600 px-3 py-1.5 font-medium text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}{{ $ancla }}" class="rounded-md border bg-white px-3 py-1.5 hover:bg-slate-50" aria-label="Página {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}{{ $ancla }}" rel="next" class="rounded-md border bg-white px-3 py-1.5 hover:bg-slate-50">Siguiente &raquo;</a>
        @else
            <span class="rounded-md border px-3 py-1.5 text-slate-300">Siguiente &raquo;</span>
        @endif
    </nav>
@endif
