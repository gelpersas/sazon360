<?php

namespace Database\Factories;

use App\Models\Insumo;
use App\Models\Producto;
use App\Models\RecetaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecetaItem>
 */
class RecetaItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'insumo_id' => Insumo::factory(),
            'cantidad' => fake()->randomFloat(3, 0.01, 1),
        ];
    }
}
