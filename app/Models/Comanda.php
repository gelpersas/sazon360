<?php

namespace App\Models;

use App\Enums\EstadoComanda;
use App\Events\ComandaActualizada;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

#[Fillable(['empresa_id', 'pedido_id', 'area_preparacion_id', 'estado'])]
class Comanda extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estado' => EstadoComanda::class,
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function areaPreparacion(): BelongsTo
    {
        return $this->belongsTo(AreaPreparacion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemPedido::class);
    }

    /**
     * Avanza al siguiente estado del flujo del KDS (pendiente → en
     * preparación → lista → entregada). No se puede avanzar una comanda
     * anulada ni una ya entregada (estado terminal).
     */
    public function avanzar(): void
    {
        $siguiente = $this->estado->siguiente();

        if ($siguiente === null) {
            throw new RuntimeException('Esta comanda ya no puede avanzar de estado.');
        }

        $this->update(['estado' => $siguiente]);

        // dispatchSeguro() (no dispatch()): un fallo de broadcast (ej.
        // Reverb caído) nunca debe deshacer/impedir el avance ya guardado —
        // ver el docblock de ComandaActualizada::dispatchSeguro().
        ComandaActualizada::dispatchSeguro($this);
    }

    /**
     * Anula la comanda — se usa cuando el pedido completo se anula con
     * comandas que ya estaban enviadas a cocina (ver Pedido::anular()). No
     * se puede anular una comanda ya entregada (terminal) ni una que ya
     * estaba anulada. A propósito no dispara la notificación al KDS por sí
     * sola: quien la llama dentro de una transacción más grande es
     * responsable de notificar después de confirmar, mismo criterio que
     * Pedido::enviarComanda().
     */
    public function anular(): void
    {
        if (! in_array($this->estado, [EstadoComanda::Pendiente, EstadoComanda::EnPreparacion, EstadoComanda::Lista], true)) {
            throw new RuntimeException('Esta comanda no se puede anular en su estado actual.');
        }

        $this->update(['estado' => EstadoComanda::Anulada]);
    }
}
