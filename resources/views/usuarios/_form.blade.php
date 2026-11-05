@php
    $campo = 'w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none';
    // Con varios formularios en la misma página (modales), solo el que se envió reutiliza lo capturado
    $usarOld = $usarOld ?? true;
    $val = fn (string $c, $def) => $usarOld ? old($c, $def) : $def;
@endphp
@csrf
<div class="grid gap-4 sm:grid-cols-2">
    <label class="block text-sm sm:col-span-2">
        <span class="mb-1 block font-medium">Nombre *</span>
        <input name="name" value="{{ $val('name', $usuario->name) }}" required maxlength="255" class="{{ $campo }}">
    </label>
    <label class="block text-sm sm:col-span-2">
        <span class="mb-1 block font-medium">Correo (para entrar) *</span>
        <input name="email" type="email" value="{{ $val('email', $usuario->email) }}" required class="{{ $campo }}">
    </label>
    @php($esPropio = $usuario->exists && auth()->user()->is($usuario))
    <div class="block text-sm sm:col-span-2">
        <span class="mb-1 block font-medium">Rol *</span>
        @if ($esPropio)
            <input type="hidden" name="rol" value="{{ $usuario->rol?->value }}">
        @endif
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach (\App\Enums\Rol::cases() as $r)
                <label @class(['flex cursor-pointer gap-2 rounded-md border p-3', 'opacity-60' => $esPropio])>
                    <input type="radio" name="rol" value="{{ $r->value }}" class="mt-1"
                           @checked($val('rol', $usuario->rol?->value) === $r->value) @disabled($esPropio)>
                    <span><span class="font-medium">{{ $r->etiqueta() }}</span>
                        <span class="block text-xs text-slate-500">{{ $r->descripcion() }}</span></span>
                </label>
            @endforeach
        </div>
        @if ($esPropio)<span class="mt-1 block text-xs text-slate-500">Tu propio rol solo lo puede cambiar otro administrador.</span>@endif
    </div>
    @if ($usuario->exists && auth()->user()->is($usuario))
        <p class="text-xs text-slate-500 sm:col-span-2">Tu contraseña se cambia en <a href="{{ route('password.edit') }}" class="text-blue-700 hover:underline">Mi contraseña</a>.</p>
    @else
    <label class="block text-sm">
        <span class="mb-1 block font-medium">Contraseña {{ $usuario->exists ? '(dejar vacía para no cambiar)' : '*' }}</span>
        <input name="password" type="password" autocomplete="new-password" @required(! $usuario->exists) minlength="10" class="{{ $campo }}">
        <span class="mt-1 block text-xs text-slate-500">Mínimo 10 caracteres.</span>
    </label>
    <label class="block text-sm">
        <span class="mb-1 block font-medium">Confirmar contraseña</span>
        <input name="password_confirmation" type="password" autocomplete="new-password" class="{{ $campo }}">
    </label>
    @endif
</div>
