@extends('layouts.app')
@section('titulo', 'Nuevo operador')

@section('contenido')
<div class="mx-auto max-w-xl">
    <a href="{{ route('usuarios.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Usuarios</a>
    <h1 class="mb-5 mt-1 text-xl font-semibold">Nuevo operador</h1>
    <form method="POST" action="{{ route('usuarios.store') }}" class="rounded-lg border bg-white p-5">
        @include('usuarios._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('usuarios.index') }}" class="rounded-md border px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Crear</button>
        </div>
    </form>
</div>
@endsection
