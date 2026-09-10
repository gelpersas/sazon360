<?php

namespace Database\Factories;

use App\Models\AreaPreparacion;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemPedido>
 */
class ItemPedidoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->words(2, true);

        return [
            'pedido_id' => Pedido::factory(),
            'producto_id' => Producto::factory(),
            'area_preparacion_id' => AreaPreparacion::factory(),
            'nombre_producto' => $nombre,
            'precio_unitario' => fake()->randomFloat(2, 1, 20),
            'cantidad' => fake()->numberBetween(1, 3),
        ];
    }
}
