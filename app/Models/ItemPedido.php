<?php

namespace App\Models;

use App\Enums\EstadoPedido;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use RuntimeException;

#[Fillable(['pedido_id', 'producto_id', 'area_preparacion_id', 'comanda_id', 'nombre_producto', 'precio_unitario', 'cantidad', 'notas'])]
class ItemPedido extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'precio_unitario' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ItemPedido $item): void {
            $pedido = $item->pedido ?? Pedido::find($item->pedido_id);

            if ($pedido && $pedido->estado !== EstadoPedido::Abierto) {
                throw new InvalidArgumentException('No se pueden agregar ítems a un pedido que no está abierto.');
            }

            if ($item->cantidad !== null && $item->cantidad < 1) {
                throw new InvalidArgumentException('La cantidad debe ser al menos 1.');
            }
        });

        static::deleting(function (ItemPedido $item): void {
            if ($item->comanda_id !== null) {
                throw new RuntimeException('No se puede quitar un ítem ya enviado a cocina — anula la comanda en su lugar.');
            }
        });
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function areaPreparacion(): BelongsTo
    {
        return $this->belongsTo(AreaPreparacion::class);
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    public function subtotal(): string
    {
        return bcmul((string) $this->precio_unitario, (string) $this->cantidad, 2);
    }

    public function actualizarCantidad(int $cantidad): void
    {
        if ($cantidad < 1) {
            throw new InvalidArgumentException('La cantidad debe ser al menos 1.');
        }

        if ($this->comanda_id !== null) {
            throw new RuntimeException('No se puede modificar la cantidad de un ítem ya enviado a cocina.');
        }

        $this->update(['cantidad' => $cantidad]);
    }

    public function actualizarNotas(?string $notas): void
    {
        if ($this->comanda_id !== null) {
            throw new RuntimeException('No se puede modificar la nota de un ítem ya enviado a cocina.');
        }

        $this->update(['notas' => $notas]);
    }
}
