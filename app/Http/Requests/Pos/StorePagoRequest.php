<?php

namespace App\Http\Requests\Pos;

use App\Enums\MedioPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medio' => ['required', Rule::enum(MedioPago::class)],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
