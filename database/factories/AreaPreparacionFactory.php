<?php

namespace Database\Factories;

use App\Models\AreaPreparacion;
use App\Models\Empresa;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaPreparacion>
 */
class AreaPreparacionFactory extends Factory
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
            'nombre' => 'Cocina '.fake()->unique()->numberBetween(1, 100000),
            'orden' => 0,
            'estado' => 'activa',
        ];
    }
}
