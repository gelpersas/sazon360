<?php

namespace Database\Factories;

use App\Enums\TipoMovimientoCaja;
use App\Models\Caja;
use App\Models\Empresa;
use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoCaja>
 */
class MovimientoCajaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caja_id' => Caja::factory(),
            'empresa_id' => Empresa::factory(),
            'usuario_id' => User::factory(),
            'tipo' => TipoMovimientoCaja::Ingreso,
            'monto' => fake()->randomFloat(2, 1, 50),
            'descripcion' => fake()->sentence(4),
        ];
    }

    public function egreso(): static
    {
        return $this->state(fn () => ['tipo' => TipoMovimientoCaja::Egreso]);
    }
}
