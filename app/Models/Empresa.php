<?php

namespace App\Models;

use App\Enums\EstadoEmpresa;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable([
    'nombre', 'slug', 'estado', 'nombre_comercial', 'nit', 'dv',
    'regimen_tributario', 'actividad_economica_ciiu',
    'direccion', 'telefono', 'whatsapp', 'email',
    'logo_path', 'notas_internas',
])]
class Empresa extends Model implements HasName
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estado' => EstadoEmpresa::class,
        ];
    }

    /**
     * El NIT (y su DV) quedan fijos una vez creados — decisión explícita del
     * usuario, no adivinada: una vez que una empresa empieza a operar con un
     * NIT, cambiarlo silenciosamente sería un problema fiscal real, no solo
     * de datos. El DV se valida junto con el NIT porque son un solo dato
     * lógico (el dígito de verificación de ESE NIT).
     */
    protected static function booted(): void
    {
        static::updating(function (Empresa $empresa): void {
            if ($empresa->getOriginal('nit') === null) {
                return;
            }

            if ($empresa->isDirty('nit') || $empresa->isDirty('dv')) {
                throw new InvalidArgumentException('El NIT y su dígito de verificación no se pueden modificar una vez creados.');
            }
        });
    }

    public function getFilamentName(): string
    {
        return $this->nombre;
    }

    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class);
    }

    public function accesos(): HasMany
    {
        return $this->hasMany(Acceso::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    public function areasPreparacion(): HasMany
    {
        return $this->hasMany(AreaPreparacion::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
