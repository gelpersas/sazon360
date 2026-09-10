<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'asignaciones' => ['required', 'array', 'min:1'],
            'asignaciones.*.item_pedido_id' => ['required', 'integer'],
            'asignaciones.*.porcentaje' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ];
    }
}
