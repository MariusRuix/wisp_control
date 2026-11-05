<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero' => $this->filled('numero') ? trim((string) $this->input('numero')) : null,
            'onu_id' => $this->filled('onu_id') ? $this->input('onu_id') : null,
            'mensualidad' => $this->filled('mensualidad')
                ? str_replace([',', '$', ' '], '', (string) $this->input('mensualidad'))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Cliente|null $cliente */
        $cliente = $this->route('cliente');

        return [
            'numero' => ['nullable', 'string', 'max:50', Rule::unique('clientes', 'numero')->ignore($cliente?->id)],
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'mensualidad' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'dia_pago' => ['required', 'integer', 'between:1,28'],
            'fecha_alta' => ['required', 'date'],
            'onu_id' => [
                'nullable', 'integer', 'exists:onus,id',
                Rule::unique('clientes', 'onu_id')->ignore($cliente?->id),
            ],
            'no_cortar_hasta' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'onu_id.unique' => 'Esa ONU ya está ligada a otro cliente.',
            'numero.unique' => 'Ya existe un cliente con ese número.',
            'dia_pago.between' => 'El día de pago debe estar entre 1 y 28 (así funciona en todos los meses).',
        ];
    }

    public function attributes(): array
    {
        return [
            'numero' => 'número de cliente',
            'dia_pago' => 'día de pago',
            'fecha_alta' => 'fecha de alta',
            'onu_id' => 'ONU',
            'no_cortar_hasta' => 'no cortar hasta',
        ];
    }
}
