<?php

namespace App\Models;

use App\Enums\TipoMovimientoInventario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'sede_id', 'insumo_id', 'usuario_id', 'pedido_id', 'compra_id', 'traslado_inventario_id', 'tipo', 'cantidad', 'motivo'])]
class MovimientoInventario extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoInventario::class,
            'cantidad' => 'decimal:3',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function trasladoInventario(): BelongsTo
    {
        return $this->belongsTo(TrasladoInventario::class);
    }
}
