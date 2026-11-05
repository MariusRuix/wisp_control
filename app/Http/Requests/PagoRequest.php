<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('monto')) {
            $this->merge(['monto' => str_replace([',', '$', ' '], '', (string) $this->input('monto'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'metodo' => ['required', Rule::enum(MetodoPago::class)],
            'referencia' => ['nullable', 'string', 'max:100'],
            'notas' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'fecha.before_or_equal' => 'La fecha del pago no puede ser futura.',
        ];
    }
}
