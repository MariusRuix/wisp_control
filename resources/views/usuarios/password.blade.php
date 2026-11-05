@extends('layouts.app')
@section('titulo', 'Mi contraseña')

@section('contenido')
@php($campo = 'w-full rounded-md border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none')
<div class="mx-auto max-w-md">
    <h1 class="mb-5 text-xl font-semibold">Cambiar mi contraseña</h1>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4 rounded-lg border bg-white p-5">
        @csrf @method('PUT')
        <label class="block text-sm">
            <span class="mb-1 block font-medium">Contraseña actual</span>
            <input name="actual" type="password" required autocomplete="current-password" class="{{ $campo }}">
        </label>
        <label class="block text-sm">
            <span class="mb-1 block font-medium">Nueva contraseña</span>
            <input name="password" type="password" required minlength="10" autocomplete="new-password" class="{{ $campo }}">
            <span class="mt-1 block text-xs text-slate-500">Mínimo 10 caracteres. Se cerrará tu sesión en otros equipos.</span>
        </label>
        <label class="block text-sm">
            <span class="mb-1 block font-medium">Confirmar nueva contraseña</span>
            <input name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $campo }}">
        </label>
        <button class="w-full rounded-md bg-blue-600 py-2 text-sm font-medium text-white hover:bg-blue-700">Guardar</button>
    </form>
</div>
@endsection
