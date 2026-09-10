<?php

namespace Database\Factories;

use App\Enums\EstadoPedido;
use App\Enums\TipoPedido;
use App\Models\Empresa;
use App\Models\Pedido;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
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
            'usuario_id' => User::factory(),
            'tipo' => TipoPedido::Mostrador,
            'estado' => EstadoPedido::Abierto,
            'idempotency_key' => fake()->unique()->uuid(),
        ];
    }
}
