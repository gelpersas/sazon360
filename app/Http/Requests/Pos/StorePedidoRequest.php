<?php

namespace App\Http\Requests\Pos;

use App\Enums\TipoPedido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoPedido::class)],
            'mesa_id' => ['required_if:tipo,mesa', 'nullable', 'integer'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
