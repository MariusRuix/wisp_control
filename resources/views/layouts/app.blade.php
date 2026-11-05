<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Control de servicio') · {{ config('app.name') }}</title>
    {{-- Tailwind por CDN para empezar rápido; en producción puedes pasarlo a Vite. --}}
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
@auth
    <header class="border-b bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-3">
            <a href="{{ route('inicio') }}" class="font-semibold text-slate-900">{{ config('app.name') }}</a>
            <nav class="flex gap-4 text-sm">
                <a href="{{ route('inicio') }}" @class(['font-medium text-blue-700' => request()->routeIs('inicio'), 'text-slate-600 hover:text-slate-900'])>Inicio</a>
                <a href="{{ route('clientes.index') }}" @class(['font-medium text-blue-700' => request()->routeIs('clientes.*'), 'text-slate-600 hover:text-slate-900'])>Clientes</a>
                <a href="{{ route('cobros.adeudos') }}" @class(['font-medium text-blue-700' => request()->routeIs('cobros.adeudos'), 'text-slate-600 hover:text-slate-900'])>Adeudos</a>
                <a href="{{ route('cobros.pagos') }}" @class(['font-medium text-blue-700' => request()->routeIs('cobros.pagos'), 'text-slate-600 hover:text-slate-900'])>Pagos</a>
                @php($fallasPendientes = \App\Models\Falla::pendientes()->count())
                <a href="{{ route('fallas.index') }}" @class(['font-medium text-blue-700' => request()->routeIs('fallas.*'), 'text-slate-600 hover:text-slate-900'])>
                    Fallas @if ($fallasPendientes)<span class="ml-0.5 rounded-full bg-red-600 px-1.5 text-[10px] font-semibold text-white">{{ $fallasPendientes }}</span>@endif
                </a>
                <a href="{{ route('onus.index') }}" @class(['font-medium text-blue-700' => request()->routeIs('onus.*'), 'text-slate-600 hover:text-slate-900'])>ONUs</a>
                <a href="{{ route('bitacora.index') }}" @class(['font-medium text-blue-700' => request()->routeIs('bitacora.*'), 'text-slate-600 hover:text-slate-900'])>Bitácora</a>
                @can('administrar-usuarios')
                    <a href="{{ route('usuarios.index') }}" @class(['font-medium text-blue-700' => request()->routeIs('usuarios.*'), 'text-slate-600 hover:text-slate-900'])>Usuarios</a>
                @endcan
                @can('configurar-sistema')
                    <a href="{{ route('configuracion.smartolt') }}" @class(['font-medium text-blue-700' => request()->routeIs('configuracion.*'), 'text-slate-600 hover:text-slate-900'])>Configuración</a>
                @endcan
            </nav>
            <div class="ml-auto flex items-center gap-3 text-sm">
                <a href="{{ route('password.edit') }}" title="Cambiar mi contraseña" class="hidden text-slate-500 hover:text-slate-900 sm:inline">{{ auth()->user()->name }} @if (auth()->user()->rol)<span class="text-xs text-slate-400">· {{ auth()->user()->rol->etiqueta() }}</span>@endif</a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="rounded-md border px-3 py-1.5 hover:bg-slate-50">Salir</button>
                </form>
            </div>
        </div>
    </header>
@endauth

<main class="mx-auto max-w-7xl px-4 py-6">
    @foreach (['ok' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'aviso' => 'border-amber-200 bg-amber-50 text-amber-800'] as $clave => $estilo)
        @if (session($clave))
            <div class="mb-4 rounded-md border px-4 py-3 text-sm {{ $estilo }}">{{ session($clave) }}</div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @yield('contenido')
</main>
</body>
</html>
