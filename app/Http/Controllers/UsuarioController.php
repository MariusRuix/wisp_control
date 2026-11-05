<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        return view('usuarios.index', [
            'usuarios' => User::query()->orderByDesc('activo')->orderBy('name')
                ->paginate($this->porPagina($request))->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('usuarios.create', ['usuario' => new User(['rol' => Rol::Operador])]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $usuario = User::create($request->datos());

        return redirect()->route('usuarios.index')->with('ok', "Operador «{$usuario->name}» creado.");
    }

    public function edit(User $usuario): View
    {
        return view('usuarios.edit', ['usuario' => $usuario]);
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $nuevoRol = Rol::from((string) $request->validated('rol'));

        if ($nuevoRol !== $usuario->rol) {
            if ($request->user()->is($usuario)) {
                return back()->withInput()->withErrors(['rol' => 'No puedes cambiar tu propio rol; pídeselo a otro administrador.']);
            }
            if ($usuario->esAdmin() && $this->esUltimoAdmin($usuario)) {
                return back()->withInput()->withErrors(['rol' => 'Debe quedar al menos un administrador activo.']);
            }
        }

        $usuario->update($request->datos());

        if ($request->filled('password') && ! $request->user()->is($usuario)) {
            // Cierra sus sesiones abiertas en otros equipos
            $this->cerrarSesiones($usuario);
        }

        return redirect()->route('usuarios.index')->with('ok', 'Cambios guardados.');
    }

    /** Baja = desactivar. Se conserva para la bitácora y los pagos que registró. */
    public function desactivar(Request $request, User $usuario): RedirectResponse
    {
        if ($request->user()->is($usuario)) {
            return back()->withErrors(['usuario' => 'No puedes desactivar tu propio usuario.']);
        }

        if (User::where('activo', true)->count() <= 1) {
            return back()->withErrors(['usuario' => 'Debe quedar al menos un operador activo.']);
        }

        if ($usuario->esAdmin() && $this->esUltimoAdmin($usuario)) {
            return back()->withErrors(['usuario' => 'Debe quedar al menos un administrador activo.']);
        }

        $usuario->update(['activo' => false]);
        $this->cerrarSesiones($usuario);

        return redirect()->route('usuarios.index')->with('ok', "«{$usuario->name}» ya no puede entrar.");
    }

    public function activar(User $usuario): RedirectResponse
    {
        $usuario->update(['activo' => true]);

        return redirect()->route('usuarios.index')->with('ok', "«{$usuario->name}» puede volver a entrar.");
    }

    private function esUltimoAdmin(User $usuario): bool
    {
        return ! User::query()
            ->where('activo', true)
            ->where('rol', Rol::Admin->value)
            ->whereKeyNot($usuario->id)
            ->exists();
    }

    private function cerrarSesiones(User $usuario): void
    {
        $usuario->forceFill(['remember_token' => null])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
        }
    }
}
