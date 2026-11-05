{{--
    Modal de alta o edición de operador (las páginas /usuarios/create y /usuarios/{id}/edit
    siguen funcionando sin JavaScript).
      $usuario  User nuevo o existente
    Id del modal: modal-usuario-nuevo o modal-usuario-{id}. Se reabre solo el modal
    cuyo guardado falló (campo oculto _modal).
--}}
@php
    $clave = $usuario->exists ? 'usuario-'.$usuario->id : 'usuario-nuevo';
    $idModal = 'modal-'.$clave;
    $huboError = $errors->any() && old('_modal') === $clave;
    $abrirAlCargar = $huboError || (! $usuario->exists && request()->boolean('nuevo'));
    $esPropio = $usuario->exists && auth()->user()->is($usuario);
@endphp

<dialog id="{{ $idModal }}" aria-labelledby="{{ $idModal }}-titulo"
        class="w-full max-w-xl rounded-xl p-0 text-left shadow-xl backdrop:bg-slate-900/50">
    <form method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}"
          class="flex max-h-[90vh] flex-col" autocomplete="off">
        @if ($usuario->exists) @method('PUT') @endif
        <input type="hidden" name="_modal" value="{{ $clave }}">

        <div class="flex items-center justify-between border-b px-5 py-4">
            <h2 id="{{ $idModal }}-titulo" class="text-lg font-semibold">
                {{ ! $usuario->exists ? 'Nuevo operador' : ($esPropio ? 'Mis datos' : 'Editar · '.$usuario->name) }}
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

            @include('usuarios._form', ['usuario' => $usuario, 'usarOld' => $huboError])

            @if ($usuario->exists && ! $esPropio)
                <p class="mt-4 text-xs text-slate-500">Si le cambias la contraseña, se cerrarán sus sesiones abiertas.</p>
            @endif
        </div>

        <div class="flex justify-end gap-2 border-t bg-slate-50 px-5 py-3">
            <button type="button" data-cerrar-modal class="rounded-md border bg-white px-4 py-2 text-sm hover:bg-slate-50">Cancelar</button>
            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                {{ $usuario->exists ? 'Guardar cambios' : 'Crear operador' }}
            </button>
        </div>
    </form>
</dialog>

<script>
    (() => {
        const modal = document.getElementById(@js($idModal));
        if (!modal || typeof modal.showModal !== 'function') return;

        const abrir = () => {
            modal.showModal();
            modal.querySelector('input[name="name"]')?.focus();
        };

        document.querySelectorAll('[data-abrir-modal="' + modal.id + '"]').forEach(a =>
            a.addEventListener('click', e => { e.preventDefault(); abrir(); }));
        modal.querySelectorAll('[data-cerrar-modal]').forEach(b => b.addEventListener('click', () => modal.close()));
        modal.addEventListener('click', e => { if (e.target === modal) modal.close(); });
        // Al cerrar sin guardar, deja el formulario como estaba
        modal.addEventListener('close', () => { if (!@js($huboError)) modal.querySelector('form').reset(); });

        @if ($abrirAlCargar)
            abrir(); // se-abre-al-cargar
        @endif
    })();
</script>
