<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelacionRequest extends FormRequest
{
    /** Cancelar pagos o cargos: solo Administrador. */
    public function authorize(): bool
    {
        return $this->user()?->can('cancelar-movimientos') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:3', 'max:255']];
    }

    public function messages(): array
    {
        return ['motivo.required' => 'Escribe el motivo de la cancelación.'];
    }

    /** @return array{cancelado_en: \Illuminate\Support\Carbon, cancelado_por: int|null, motivo_cancelacion: string} */
    public function datosCancelacion(): array
    {
        return [
            'cancelado_en' => now(),
            'cancelado_por' => $this->user()?->id,
            'motivo_cancelacion' => (string) $this->validated('motivo'),
        ];
    }
}
