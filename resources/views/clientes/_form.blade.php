@php
    $campo = 'w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none';
    $err = fn ($n) => $errors->has($n) ? ' border-red-400' : '';
@endphp

@csrf
<div class="grid gap-4 sm:grid-cols-2">
    <label class="block text-sm sm:col-span-2">
        <span class="mb-1 block font-medium">Nombre *</span>
        <input name="nombre" value="{{ old('nombre', $cliente->nombre) }}" required maxlength="255" class="{{ $campo }}{{ $err('nombre') }}">
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Número de cliente</span>
        <input name="numero" value="{{ old('numero', $cliente->numero) }}" maxlength="50" placeholder="Contrato o ID en tu otra base"
               class="{{ $campo }}{{ $err('numero') }}">
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Teléfono</span>
        <input name="telefono" type="tel" value="{{ old('telefono', $cliente->telefono) }}" maxlength="30" class="{{ $campo }}{{ $err('telefono') }}">
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Correo</span>
        <input name="email" type="email" value="{{ old('email', $cliente->email) }}" class="{{ $campo }}{{ $err('email') }}">
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Dirección</span>
        <input name="direccion" value="{{ old('direccion', $cliente->direccion) }}" maxlength="255" class="{{ $campo }}{{ $err('direccion') }}">
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Mensualidad (MXN) *</span>
        <input name="mensualidad" inputmode="decimal" value="{{ old('mensualidad', $cliente->mensualidad) }}" required placeholder="350.00"
               class="{{ $campo }}{{ $err('mensualidad') }}">
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Día de pago *</span>
        <input name="dia_pago" type="number" min="1" max="28" value="{{ old('dia_pago', $cliente->dia_pago) }}" required
               class="{{ $campo }}{{ $err('dia_pago') }}">
        <span class="mt-1 block text-xs text-slate-500">Del 1 al 28.</span>
    </label>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">Fecha de alta *</span>
        <input name="fecha_alta" type="date" value="{{ old('fecha_alta', $cliente->fecha_alta?->format('Y-m-d')) }}" required
               class="{{ $campo }}{{ $err('fecha_alta') }}">
    </label>

    <div class="block text-sm sm:col-span-2">
        <span class="mb-1 block font-medium">ONU de SmartOLT</span>
        <input type="search" id="filtro-onu" placeholder="Filtrar por SN, nombre o zona…" class="{{ $campo }} mb-2">
        <select name="onu_id" id="onu_id" size="6" class="{{ $campo }}{{ $err('onu_id') }} font-mono">
            <option value="">— Sin ONU —</option>
            @foreach ($onus as $o)
                <option value="{{ $o->id }}" @selected((string) old('onu_id', $cliente->onu_id) === (string) $o->id)>
                    {{ $o->sn ?? $o->external_id }} · {{ $o->nombre }}{{ $o->zona ? ' · '.$o->zona : '' }}
                </option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-slate-500">
            Solo aparecen ONUs que no están ligadas a otro cliente.
            @if ($onus->isEmpty()) No hay ONUs: ejecuta <code>php artisan smartolt:sincronizar</code>. @endif
        </span>
    </div>

    <label class="block text-sm">
        <span class="mb-1 block font-medium">No cortar automáticamente hasta</span>
        <input name="no_cortar_hasta" type="date" value="{{ old('no_cortar_hasta', $cliente->no_cortar_hasta?->format('Y-m-d')) }}"
               class="{{ $campo }}{{ $err('no_cortar_hasta') }}">
        <span class="mt-1 block text-xs text-slate-500">Promesa de pago o convenio. Vacío = se corta normal.</span>
    </label>

    <label class="block text-sm sm:col-span-2">
        <span class="mb-1 block font-medium">Notas</span>
        <textarea name="notas" rows="3" maxlength="5000" class="{{ $campo }}{{ $err('notas') }}">{{ old('notas', $cliente->notas) }}</textarea>
    </label>
</div>

<script>
    document.getElementById('filtro-onu').addEventListener('input', e => {
        const t = e.target.value.toLowerCase();
        [...document.getElementById('onu_id').options].forEach(o => {
            o.hidden = o.value !== '' && t !== '' && !o.text.toLowerCase().includes(t);
        });
    });
</script>
