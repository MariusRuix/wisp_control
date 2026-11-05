<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EstadoFalla;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sirve para reportar (POST) y para actualizar (PUT) una falla. */
final class FallaRequest extends FormRequest
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
        $esNueva = $this->isMethod('post');

        return [
            'descripcion' => [$esNueva ? 'required' : 'sometimes', 'string', 'max:5000'],
            'reportada_el' => [$esNueva ? 'required' : 'sometimes', 'date', 'before_or_equal:now'],
            'estado' => [$esNueva ? 'nullable' : 'required', Rule::enum(EstadoFalla::class)],
            'asignado_a' => ['nullable', 'integer', 'exists:users,id'],
            'solucion' => [
                Rule::requiredIf(fn () => $this->input('estado') === EstadoFalla::Resuelta->value),
                'nullable', 'string', 'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'solucion.required' => 'Para marcarla como resuelta escribe qué se hizo.',
            'reportada_el.before_or_equal' => 'La fecha del reporte no puede ser futura.',
        ];
    }

    public function attributes(): array
    {
        return ['descripcion' => 'descripción', 'reportada_el' => 'fecha del reporte', 'asignado_a' => 'quién la atiende', 'solucion' => 'solución'];
    }
}
