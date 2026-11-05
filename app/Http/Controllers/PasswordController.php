<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** El operador cambia su propia contraseña. */
final class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('usuarios.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10), 'different:actual'],
        ], [
            'actual.current_password' => 'La contraseña actual no es correcta.',
            'password.different' => 'La nueva contraseña debe ser distinta a la actual.',
        ], ['password' => 'nueva contraseña']);

        $request->user()->update(['password' => $datos['password']]);

        // Cierra la sesión en otros equipos y conserva esta
        Auth::logoutOtherDevices($datos['password']);

        return redirect()->route('inicio')->with('ok', 'Contraseña actualizada.');
    }
}
