<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class AbrirCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monto_inicial' => ['required', 'numeric', 'min:0'],
            'nota' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
