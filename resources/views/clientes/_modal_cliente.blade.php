{{--
    Modal de alta o edición de cliente. Usa el mismo formulario que las páginas
    /clientes/create y /clientes/{id}/edit (que siguen funcionando sin JavaScript).

    Parámetros:
      $modo     'nuevo' | 'editar'
      $cliente  Cliente (nuevo o existente)
      $onus     ONUs disponibles para el selector
    Se abre con enlaces data-abrir-modal="modal-cliente-{modo}", con ?nuevo=1 / ?editar=1
    o solo cuando el guardado anterior desde este modal tuvo errores.
--}}
@php
    $idModal = 'modal-cliente-'.$modo;
    $esNuevo = $modo === 'nuevo';
    $huboError = $errors->any() && old('_modal') === $modo;
    $abrirAlCargar = request()->boolean($modo) || $huboError;
@endphp

<dialog id="{{ $idModal }}" aria-labelledby="{{ $idModal }}-titulo"
        class="w-full max-w-3xl rounded-xl p-0 shadow-xl backdrop:bg-slate-900/50">
    <form method="POST" action="{{ $esNuevo ? route('clientes.store') : route('clientes.update', $cliente) }}" class="flex max-h-[90vh] flex-col">
        @unless ($esNuevo) @method('PUT') @endunless
        <input type="hidden" name="_modal" value="{{ $modo }}">

        <div class="flex items-center justify-between border-b px-5 py-4">
            <h2 id="{{ $idModal }}-titulo" class="text-lg font-semibold">
                {{ $esNuevo ? 'Nuevo cliente' : 'Editar · '.$cliente->nombre }}
            </h2>
            <button type="button" data-cerrar-modal aria-label="Cerrar"
                    class="rounded-md px-2 py-1 text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700">&times;</button>
        </div>

        <div class="overflow-y-auto px-5 py-4">
            @if ($huboError)
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            @include('clientes._form', ['cliente' => $cliente, 'onus' => $onus])
        </div>

        <div class="flex justify-end gap-2 border-t bg-slate-50 px-5 py-3">
            <button type="button" data-cerrar-modal class="rounded-md border bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar</button>
            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                {{ $esNuevo ? 'Guardar cliente' : 'Guardar cambios' }}
            </button>
        </div>
    </form>
</dialog>

<script>
    (() => {
        const modal = document.getElementById(@js($idModal));
        if (!modal || typeof modal.showModal !== 'function') return; // navegador viejo: se usan los enlaces normales

        const abrir = () => {
            modal.showModal();
            modal.querySelector('input[name="nombre"]')?.focus();
        };

        document.querySelectorAll('[data-abrir-modal="' + modal.id + '"]').forEach(a =>
            a.addEventListener('click', e => { e.preventDefault(); abrir(); }));
        modal.querySelectorAll('[data-cerrar-modal]').forEach(b => b.addEventListener('click', () => modal.close()));
        modal.addEventListener('click', e => { if (e.target === modal) modal.close(); }); // clic fuera cierra

        @if ($abrirAlCargar)
            abrir(); // se-abre-al-cargar
        @endif
    })();
</script>
