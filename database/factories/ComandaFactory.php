<?php

namespace Database\Factories;

use App\Enums\EstadoComanda;
use App\Models\AreaPreparacion;
use App\Models\Comanda;
use App\Models\Empresa;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comanda>
 */
class ComandaFactory extends Factory
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
            'area_preparacion_id' => AreaPreparacion::factory(),
            'estado' => EstadoComanda::Pendiente,
        ];
    }
}
