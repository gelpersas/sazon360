<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_persona' => ['required', 'in:natural,juridica'],
            'tipo_documento' => ['required', 'in:13,31,22,41,91'],
            'numero_documento' => ['required', 'string', 'max:50'],
            'dv' => ['nullable', 'string', 'max:1'],
            // Natural: nombres+apellidos. Jurídica: razón social. Nunca
            // ambos a la vez — mismo criterio que el formulario del panel
            // (ver App\Filament\Resources\Clientes\Schemas\ClienteForm,
            // DEC-050).
            'nombres' => ['required_if:tipo_persona,natural', 'nullable', 'string', 'max:255'],
            'apellidos' => ['required_if:tipo_persona,natural', 'nullable', 'string', 'max:255'],
            'razon_social' => ['required_if:tipo_persona,juridica', 'nullable', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
