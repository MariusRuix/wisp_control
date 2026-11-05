@extends('layouts.app')
@section('titulo', 'Editar '.$cliente->nombre)

@section('contenido')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('clientes.show', $cliente) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $cliente->nombre }}</a>
    <h1 class="mb-5 mt-1 text-xl font-semibold">Editar cliente</h1>

    <form method="POST" action="{{ route('clientes.update', $cliente) }}" class="rounded-lg border bg-white p-5">
        @method('PUT')
        @include('clientes._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('clientes.show', $cliente) }}" class="rounded-md border px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
            <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection
