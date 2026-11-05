<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $usuario */
        $usuario = $this->route('usuario');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            // Al crear es obligatoria; al editar, vacía = no cambiar
            'password' => [$usuario ? 'nullable' : 'required', 'confirmed', Password::min(10)],
            'rol' => ['required', Rule::enum(Rol::class)],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña', 'rol' => 'rol'];
    }

    /** @return array<string, string> */
    public function datos(): array
    {
        $d = $this->safe()->only(['name', 'email', 'rol']);
        // La propia contraseña solo se cambia en "Mi contraseña" (pide la actual)
        $esPropio = $this->route('usuario')?->is($this->user()) ?? false;
        if ($this->filled('password') && ! $esPropio) {
            $d['password'] = (string) $this->validated('password');
        }

        return $d;
    }
}
