<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empresa_id', 'nombre', 'contacto', 'telefono', 'email', 'estado'])]
class Proveedor extends Model
{
    use HasFactory;

    // Pluralización en español explícita — el default de Eloquent daría
    // "proveedors" (pluraliza en inglés: solo agrega "s").
    protected $table = 'proveedores';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}
