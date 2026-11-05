<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cargo extra (instalación, reconexión, equipo, saldo anterior…). */
final class CargoRequest extends FormRequest
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
            'concepto' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'vence_el' => ['required', 'date'],
        ];
    }
}
