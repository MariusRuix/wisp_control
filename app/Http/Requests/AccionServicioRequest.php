<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TipoAccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AccionServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoAccion::class)],
            'onus' => ['required', 'array', 'min:1', 'max:5000'],
            'onus.*' => ['integer', 'exists:onus,id'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['onus.required' => 'Selecciona al menos un cliente.'];
    }

    public function tipo(): TipoAccion
    {
        return TipoAccion::from((string) $this->validated('tipo'));
    }

    /**
     * @return list<int>
     */
    public function onuIds(): array
    {
        return array_map('intval', (array) $this->validated('onus'));
    }
}
