@extends('layouts.app')
@section('titulo', 'Usuarios')

@section('contenido')
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-xl font-semibold">Usuarios (operadores)</h1>
        <p class="text-sm text-slate-500">Administrador: todo. Cajero/Técnico: clientes, cobros, fallas y cortes de uno en uno; sin cancelaciones, sin usuarios y sin cortes masivos. Los desactivados no pueden entrar.</p>
    </div>
    <a href="{{ route('usuarios.create') }}" data-abrir-modal="modal-usuario-nuevo" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">+ Nuevo operador</a>
</div>

<div class="overflow-x-auto rounded-lg border bg-white">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
        <tr>
            <th class="px-3 py-2">Nombre</th>
            <th class="px-3 py-2">Correo</th>
            <th class="px-3 py-2">Rol</th>
            <th class="px-3 py-2">Último acceso</th>
            <th class="px-3 py-2">Estado</th>
            <th class="px-3 py-2 text-right">Acciones</th>
        </tr>
        </thead>
        <tbody class="divide-y">
        @foreach ($usuarios as $u)
            @php($soyYo = auth()->user()->is($u))
            <tr @class(['text-slate-400' => ! $u->activo])>
                <td class="px-3 py-2 font-medium">{{ $u->name }} @if ($soyYo)<span class="text-xs font-normal text-slate-400">(tú)</span>@endif</td>
                <td class="px-3 py-2">{{ $u->email }}</td>
                <td class="px-3 py-2"><span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs {{ $u->rol?->color() }}">{{ $u->rol?->etiqueta() ?? '—' }}</span></td>
                <td class="px-3 py-2 text-xs">{{ $u->ultimo_acceso?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                <td class="px-3 py-2">
                    @if ($u->activo)
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Activo</span>
                    @else
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">Desactivado</span>
                    @endif
                </td>
                <td class="px-3 py-2 text-right">
                    <div class="inline-flex gap-2">
                        @if ($soyYo)
                            <a href="{{ route('password.edit') }}" class="rounded-md border bg-white px-3 py-1 text-xs hover:bg-slate-50">Mi contraseña</a>
                        @endif
                        <a href="{{ route('usuarios.edit', $u) }}" data-abrir-modal="modal-usuario-{{ $u->id }}"
                           class="rounded-md border bg-white px-3 py-1 text-xs hover:bg-slate-50">{{ $soyYo ? 'Mis datos' : 'Editar' }}</a>
                        @if ($soyYo)
                            {{-- nadie puede desactivarse a sí mismo --}}
                        @elseif ($u->activo)
                            <form method="POST" action="{{ route('usuarios.desactivar', $u) }}"
                                  onsubmit="return confirm({{ Js::from('¿Desactivar a '.$u->name.'? Ya no podrá entrar; su historial se conserva.') }})">
                                @csrf
                                <button class="rounded-md border border-red-200 bg-white px-3 py-1 text-xs text-red-700 hover:bg-red-50">Desactivar</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('usuarios.activar', $u) }}">@csrf
                                <button class="rounded-md border bg-white px-3 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Reactivar</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@include('partials.paginacion', ['p' => $usuarios])

{{-- Modales: alta y uno por operador para editar --}}
@include('usuarios._modal', ['usuario' => new \App\Models\User(['rol' => \App\Enums\Rol::Operador])])
@foreach ($usuarios as $u)
    @include('usuarios._modal', ['usuario' => $u])
@endforeach
@endsection
