<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Mesa;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mesa>
 */
class MesaFactory extends Factory
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
            'nombre' => 'Mesa '.fake()->unique()->numberBetween(1, 999),
            'piso' => null,
            'zona' => null,
            'capacidad' => fake()->numberBetween(2, 6),
            'estado' => 'activa',
        ];
    }
}
