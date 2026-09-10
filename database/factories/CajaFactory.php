<?php

namespace Database\Factories;

use App\Enums\EstadoCaja;
use App\Models\Caja;
use App\Models\Empresa;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Caja>
 */
class CajaFactory extends Factory
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
            'usuario_apertura_id' => User::factory(),
            'monto_inicial' => fake()->randomFloat(2, 20, 100),
            'estado' => EstadoCaja::Abierta,
            'abierta_at' => now(),
        ];
    }

    public function cerrada(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoCaja::Cerrada,
            'usuario_cierre_id' => User::factory(),
            'monto_cierre_esperado' => 0,
            'monto_cierre_real' => 0,
            'diferencia' => 0,
            'cerrada_at' => now(),
        ]);
    }
}
