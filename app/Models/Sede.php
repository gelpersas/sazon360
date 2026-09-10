<?php

namespace App\Models;

use App\Enums\EstadoCaja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empresa_id', 'nombre', 'estado'])]
class Sede extends Model
{
    use HasFactory;

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function accesos(): HasMany
    {
        return $this->hasMany(Acceso::class);
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    public function cajaAbierta(): ?Caja
    {
        return $this->cajas()->where('estado', EstadoCaja::Abierta)->first();
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
