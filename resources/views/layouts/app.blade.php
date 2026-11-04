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
            <a href="{{ route('onus.index') }}" class="font-semibold text-slate-900">{{ config('app.name') }}</a>
            <nav class="flex gap-4 text-sm">
                <a href="{{ route('onus.index') }}" @class(['font-medium text-blue-700' => request()->routeIs('onus.*'), 'text-slate-600 hover:text-slate-900'])>Clientes</a>
                <a href="{{ route('bitacora.index') }}" @class(['font-medium text-blue-700' => request()->routeIs('bitacora.*'), 'text-slate-600 hover:text-slate-900'])>Bitácora</a>
            </nav>
            <div class="ml-auto flex items-center gap-3 text-sm">
                <span class="hidden text-slate-500 sm:inline">{{ auth()->user()->name }}</span>
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
