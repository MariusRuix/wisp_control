@extends('layouts.app')
@section('titulo', 'Entrar')

@section('contenido')
<div class="mx-auto mt-16 max-w-sm rounded-xl border bg-white p-6 shadow-sm">
    <h1 class="mb-1 text-lg font-semibold">Control de servicio</h1>
    <p class="mb-6 text-sm text-slate-500">Entra con tu usuario de operador.</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="mb-1 block font-medium">Correo</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-md border px-3 py-2 focus:border-blue-500 focus:outline-none">
        </label>
        <label class="block text-sm">
            <span class="mb-1 block font-medium">Contraseña</span>
            <input type="password" name="password" required
                   class="w-full rounded-md border px-3 py-2 focus:border-blue-500 focus:outline-none">
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="recordar" value="1"> Recordarme
        </label>
        <button class="w-full rounded-md bg-blue-600 py-2 font-medium text-white hover:bg-blue-700">Entrar</button>
    </form>
</div>
@endsection
