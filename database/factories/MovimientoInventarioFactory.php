<?php

namespace Database\Factories;

use App\Enums\TipoMovimientoInventario;
use App\Models\Empresa;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoInventario>
 */
class MovimientoInventarioFactory extends Factory
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
            'usuario_id' => User::factory(),
            'tipo' => TipoMovimientoInventario::Entrada,
            'cantidad' => fake()->randomFloat(3, 1, 50),
            'motivo' => fake()->sentence(4),
        ];
    }
}
