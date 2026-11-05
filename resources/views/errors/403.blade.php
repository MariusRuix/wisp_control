@extends('layouts.app')
@section('titulo', 'Sin permiso')

@section('contenido')
<div class="mx-auto mt-16 max-w-md rounded-xl border bg-white p-6 text-center shadow-sm">
    <h1 class="mb-2 text-lg font-semibold">No tienes permiso para esto</h1>
    <p class="mb-5 text-sm text-slate-500">
        Esta acción es solo para administradores (usuarios, cancelaciones de pagos o cargos y cortes masivos).
        Pídele a un administrador que la haga por ti.
    </p>
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('clientes.index') }}"
       class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Regresar</a>
</div>
@endsection
