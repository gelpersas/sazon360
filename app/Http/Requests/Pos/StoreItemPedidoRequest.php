<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer'],
            'area_preparacion_id' => ['required', 'integer'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'notas' => ['nullable', 'string', 'max:255'],
        ];
    }
}
