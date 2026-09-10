<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sub_cuenta_id', 'item_pedido_id', 'porcentaje'])]
class SubCuentaItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'porcentaje' => 'decimal:2',
        ];
    }

    public function subCuenta(): BelongsTo
    {
        return $this->belongsTo(SubCuenta::class);
    }

    public function itemPedido(): BelongsTo
    {
        return $this->belongsTo(ItemPedido::class);
    }

    public function monto(): string
    {
        return bcmul($this->itemPedido->subtotal(), bcdiv((string) $this->porcentaje, '100', 4), 2);
    }
}
