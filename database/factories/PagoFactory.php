<?php

namespace Database\Factories;

use App\Enums\MedioPago;
use App\Models\Empresa;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
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
            'pedido_id' => Pedido::factory(),
            'usuario_id' => User::factory(),
            'medio' => MedioPago::Efectivo,
            'monto' => fake()->randomFloat(2, 1, 50),
            'idempotency_key' => fake()->unique()->uuid(),
        ];
    }
}
