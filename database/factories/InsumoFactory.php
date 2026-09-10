<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Insumo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Insumo>
 */
class InsumoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nombre' => fake()->unique()->word(),
            'unidad_medida' => 'g',
            'stock_minimo' => 500,
            'estado' => 'activo',
        ];
    }
}
