@extends('layouts.app')
@section('titulo', 'Configuración · SmartOLT')

@section('contenido')
@php
    $campo = 'w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none';
    $origen = fn (string $c) => $cfg->origen($c) === 'panel'
        ? '<span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-800">PANEL</span>'
        : '<span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">.ENV</span>';
    $ultimo = $cfg->ultimoCambio();
    $nombresRutas = [
        'disable' => 'Cortar 1 ONU', 'enable' => 'Reconectar 1 ONU',
        'bulk_disable' => 'Cortar varias', 'bulk_enable' => 'Reconectar varias',
        'all_details' => 'Lista de ONUs', 'all_statuses' => 'Estados de red', 'prueba' => 'Probar conexión',
    ];
@endphp

<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Configuración · API de SmartOLT</h1>
        <p class="text-sm text-slate-500">
            Solo administradores. Lo que guardes aquí tiene prioridad sobre el archivo .env.
            @if ($ultimo) Último cambio: {{ $ultimo->updated_at->format('d/m/Y H:i') }} por {{ $ultimo->autor?->name ?? '—' }}. @endif
        </p>
    </div>
    <div class="flex items-center gap-2 text-sm">
        @if ($cfg->estaCompleta())
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800">Configurada</span>
        @else
            <span class="rounded-full bg-red-100 px-3 py-1 text-red-800">Falta subdominio o token</span>
        @endif
    </div>
</div>

@if ($cfg->tokenIlegible())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        El token guardado no se puede leer (probablemente cambió APP_KEY). Escribe el token de nuevo y guarda.
    </div>
@endif

<div class="grid gap-4 lg:grid-cols-3">
    <form method="POST" action="{{ route('configuracion.smartolt.guardar') }}" class="flex flex-col gap-5 rounded-lg border bg-white p-5 lg:col-span-2" autocomplete="off">
        @csrf @method('PUT')

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">
                <span class="mb-1 flex items-center gap-2 font-medium">Subdominio * {!! $origen('subdomain') !!}</span>
                <div class="flex items-center rounded-md border focus-within:border-blue-500">
                    <span class="pl-3 text-slate-400">https://</span>
                    <input name="subdomain" required value="{{ old('subdomain', $cfg->subdominio()) }}" placeholder="miempresa"
                           class="w-full bg-transparent px-1 py-2 text-sm focus:outline-none">
                    <span class="pr-3 text-slate-400">.smartolt.com</span>
                </div>
                <span class="mt-1 block text-xs text-slate-500">Puedes pegar la dirección completa de tu panel de SmartOLT.</span>
            </label>

            <label class="block text-sm sm:col-span-2">
                <span class="mb-1 flex items-center gap-2 font-medium">Token (API key) {!! $origen('token') !!}</span>
                <input name="token" type="password" autocomplete="new-password" spellcheck="false"
                       placeholder="{{ $cfg->tokenEnmascarado() ? 'Guardado: '.$cfg->tokenEnmascarado().' — déjalo vacío para no cambiarlo' : 'Pega aquí el API key' }}"
                       class="{{ $campo }} font-mono">
                <span class="mt-1 block text-xs text-slate-500">
                    En SmartOLT: Settings › API KEYS. Se guarda cifrado y nunca se vuelve a mostrar completo.
                </span>
            </label>

            <label class="block text-sm">
                <span class="mb-1 flex items-center gap-2 font-medium">Tiempo de espera (segundos) * {!! $origen('timeout') !!}</span>
                <input name="timeout" type="number" min="5" max="120" required value="{{ old('timeout', $cfg->timeout()) }}" class="{{ $campo }}">
            </label>

            <label class="block text-sm">
                <span class="mb-1 flex items-center gap-2 font-medium">Campo para cortes masivos * {!! $origen('bulk_field') !!}</span>
                <input name="bulk_field" required value="{{ old('bulk_field', $cfg->campoBulk()) }}" class="{{ $campo }} font-mono">
            </label>
        </div>

        <details class="rounded-md border p-4" @if ($errors->has('endpoints.*')) open @endif>
            <summary class="cursor-pointer text-sm font-medium">Avanzado: rutas de la API</summary>
            <p class="mb-3 mt-2 text-xs text-slate-500">
                Cámbialas solo si SmartOLT usa otra ruta en tu cuenta. Vacía = valor de fábrica. <code>{id}</code> = ID de la ONU en SmartOLT.
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (\App\Services\ConfiguracionSmartOlt::RUTAS as $r)
                    <label class="block text-xs">
                        <span class="mb-1 flex items-center gap-2 font-medium text-slate-700">{{ $nombresRutas[$r] }} {!! $origen("endpoints.$r") !!}</span>
                        <input name="endpoints[{{ $r }}]" value="{{ old("endpoints.$r", $cfg->ruta($r)) }}" class="{{ $campo }} font-mono text-xs">
                    </label>
                @endforeach
            </div>
        </details>

        <div class="flex flex-wrap justify-end gap-2">
            <button name="probar" value="1" class="rounded-md border bg-white px-4 py-2 text-sm hover:bg-slate-50">Guardar y probar</button>
            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Guardar</button>
        </div>
    </form>

    <div class="space-y-4">
        <section class="rounded-lg border bg-white p-5">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Conexión</h2>
            <p class="mb-3 text-sm text-slate-600">Pide a SmartOLT la lista de OLTs para comprobar subdominio y token. No corta ni cambia nada.</p>
            <form method="POST" action="{{ route('configuracion.smartolt.probar') }}">@csrf
                <button @disabled(! $cfg->estaCompleta()) class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50">Probar conexión</button>
            </form>
        </section>

        <section class="rounded-lg border bg-white p-5">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-500">ONUs</h2>
            <dl class="mb-3 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">En el panel</dt><dd class="font-medium">{{ number_format($onus) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Última sincronización</dt>
                    <dd>{{ $ultimaSync ? \Illuminate\Support\Carbon::parse($ultimaSync)->format('d/m/Y H:i') : 'Nunca' }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('configuracion.smartolt.sincronizar') }}">@csrf
                <button @disabled(! $cfg->estaCompleta()) class="w-full rounded-md border bg-white px-4 py-2 text-sm hover:bg-slate-50 disabled:opacity-50">Sincronizar ahora</button>
            </form>
            <p class="mt-2 text-xs text-slate-500">Se procesa en la cola (necesita el proceso de la cola corriendo).</p>
        </section>

        <section class="rounded-lg border border-red-200 bg-white p-5">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-red-700">Restablecer</h2>
            <p class="mb-3 text-sm text-slate-600">Borra lo guardado aquí y vuelve a usar el .env.</p>
            <form method="POST" action="{{ route('configuracion.smartolt.restablecer') }}"
                  onsubmit="return confirm('¿Borrar la configuración del panel y usar la del .env?')">@csrf
                <button class="w-full rounded-md border border-red-200 bg-white px-4 py-2 text-sm text-red-700 hover:bg-red-50">Usar el .env</button>
            </form>
        </section>
    </div>
</div>
@endsection
