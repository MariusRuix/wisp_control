<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $llave = Str::lower($datos['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($llave, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($llave).' segundos.',
            ]);
        }

        if (! Auth::attempt($datos, $request->boolean('recordar'))) {
            RateLimiter::hit($llave, 60);

            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }

        RateLimiter::clear($llave);
        $request->session()->regenerate();

        return redirect()->intended(route('onus.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
