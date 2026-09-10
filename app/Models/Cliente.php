<?php

namespace App\Models;

use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoPersona;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Tercero" del lado del cliente (para asociar a una venta y facturarla a
 * nombre de alguien identificado, en vez del "consumidor final" genérico) —
 * ver docs/DECISIONES.md. Separado de `Proveedor` a propósito: son
 * conceptos de negocio distintos (a quién se le compra vs. a quién se le
 * vende) y unificarlos habría obligado a tocar Compras, que ya funciona.
 *
 * `nombres`/`apellidos` (persona natural) y `razon_social` (persona
 * jurídica) son campos separados, no uno solo que cambia de significado
 * (ver DEC-050) — `nombre_comercial` es válido para ambos tipos y es un
 * campo real y distinto en el esquema de Factus (`trade_name`, separado de
 * `company`/`names` — confirmado contra el SDK oficial, no adivinado).
 */
#[Fillable(['empresa_id', 'tipo_persona', 'tipo_documento', 'numero_documento', 'dv', 'nombres', 'apellidos', 'razon_social', 'nombre_comercial', 'direccion', 'telefono', 'email', 'estado'])]
class Cliente extends Model
{
    use HasFactory;

    protected $appends = ['nombre_completo'];

    protected function casts(): array
    {
        return [
            'tipo_persona' => TipoPersona::class,
            'tipo_documento' => TipoDocumentoCliente::class,
        ];
    }

    /**
     * Nombre a mostrar en listados/selectores/factura — no es un campo de
     * base de datos, se arma según el tipo de persona. Para facturar, ver
     * FactusProveedor::construirCliente() (usa `razon_social`/`nombres`+
     * `apellidos` directamente, no este accessor, para no depender de cómo
     * se formatea la vista).
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->tipo_persona === TipoPersona::Juridica) {
                return (string) $this->razon_social;
            }

            return trim("{$this->nombres} {$this->apellidos}");
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
