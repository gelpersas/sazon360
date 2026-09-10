<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Acceso;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Acceso>
 */
class AccesoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'empresa_id' => Empresa::factory(),
            'sede_id' => null,
            'rol' => Rol::AdministracionCentral,
        ];
    }

    public function administracionCentral(): static
    {
        return $this->state(fn () => ['rol' => Rol::AdministracionCentral, 'sede_id' => null]);
    }

    public function enSede(int $sedeId, Rol $rol = Rol::AdministracionSede): static
    {
        return $this->state(fn () => ['rol' => $rol, 'sede_id' => $sedeId]);
    }
}
