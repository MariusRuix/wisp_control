<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ConfiguracionSmartOlt;
use Illuminate\Foundation\Http\FormRequest;

final class ConfiguracionSmartOltRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configurar-sistema') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Aceptar que peguen la URL completa: https://miempresa.smartolt.com/
        $sub = strtolower(trim((string) $this->input('subdomain')));
        $sub = (string) preg_replace('#^https?://#', '', $sub);
        $sub = (string) preg_replace('#\.smartolt\.com.*$#', '', $sub);

        $this->merge(['subdomain' => trim($sub, '/ ')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rutas = [];
        foreach (ConfiguracionSmartOlt::RUTAS as $r) {
            $rutas["endpoints.{$r}"] = ['nullable', 'string', 'max:200', 'starts_with:/api/'];
        }

        return [
            'subdomain' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/'],
            'token' => ['nullable', 'string', 'min:10', 'max:255'],
            'timeout' => ['required', 'integer', 'between:5,120'],
            'bulk_field' => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/'],
            'endpoints' => ['nullable', 'array'],
            ...$rutas,
        ];
    }

    public function messages(): array
    {
        return [
            'subdomain.regex' => 'El subdominio solo lleva letras, números y guiones (lo que va antes de .smartolt.com).',
            'endpoints.*.starts_with' => 'Las rutas deben empezar con /api/.',
            'bulk_field.regex' => 'El campo bulk solo lleva letras minúsculas y guion bajo.',
        ];
    }

    public function attributes(): array
    {
        return ['subdomain' => 'subdominio', 'token' => 'token (API key)', 'timeout' => 'tiempo de espera', 'bulk_field' => 'campo bulk'];
    }
}
