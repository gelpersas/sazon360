<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
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
            'categoria_id' => Categoria::factory(),
            'nombre' => fake()->unique()->words(3, true),
            'descripcion' => null,
            'precio' => fake()->randomFloat(2, 1, 50),
            'estado' => 'activo',
        ];
    }
}
