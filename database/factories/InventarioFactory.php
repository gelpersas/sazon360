<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventario>
 */
class InventarioFactory extends Factory
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
            'sede_id' => Sede::factory(),
            'insumo_id' => Insumo::factory(),
            'cantidad_actual' => fake()->randomFloat(3, 0, 1000),
        ];
    }
}
