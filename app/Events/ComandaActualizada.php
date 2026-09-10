<?php

namespace App\Events;

use App\Models\Comanda;
use App\Models\ItemPedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifica al KDS que una comanda se creó o cambió de estado — reemplaza el
 * polling del MVP (ver docs/DECISIONES.md DEC-002 y Fase 8). Se dispara
 * desde Pedido::enviarComanda() (creación) y Comanda::avanzar() (cambio de
 * estado), no desde los controladores — mismo criterio que el resto del
 * dominio (los efectos secundarios viven en el modelo, no en el controlador).
 *
 * `ShouldBroadcastNow` en vez de `ShouldBroadcast`: notificar al KDS es
 * sensible a la latencia (es justamente lo que reemplaza al polling), así
 * que se envía sincrónicamente en el propio request en vez de pasar por la
 * cola — no depende de que `queue:work` esté corriendo.
 */
class ComandaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Comanda $comanda) {}

    /**
     * `dispatch()` normal deja que un fallo de broadcast (ej. Reverb caído)
     * tumbe con un 500 la acción real de negocio (enviar comanda, avanzar
     * comanda) — se confirmó así en producción: `ComandaActualizada` con
     * `ShouldBroadcastNow` corre sincrónicamente dentro del propio request
     * (ver docblock de la clase), así que una excepción de broadcast
     * (`Illuminate\Broadcasting\BroadcastException`, típicamente Reverb sin
     * levantar) se propaga hacia arriba sin que nada la atrape. El aviso en
     * tiempo real al KDS es un "mejor esfuerzo" que reemplaza el polling
     * (ver el docblock de la clase) — nunca debería poder bloquear que la
     * comanda se cree/avance de verdad en base de datos. Usar esto en vez
     * de `dispatch()` desde Pedido::enviarComanda()/Comanda::avanzar().
     */
    public static function dispatchSeguro(Comanda $comanda): void
    {
        try {
            static::dispatch($comanda);
        } catch (Throwable $e) {
            Log::warning('No se pudo notificar al KDS en tiempo real (¿Reverb no está corriendo?).', [
                'comanda_id' => $comanda->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("sede.{$this->comanda->pedido->sede_id}.comandas"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'comanda.actualizada';
    }

    /**
     * Mismo formato que ComandaController::index() — así el store del KDS
     * puede usar el mismo parseo tanto para la carga inicial (HTTP) como
     * para las actualizaciones en vivo (WebSocket).
     */
    public function broadcastWith(): array
    {
        $comanda = $this->comanda;
        $comanda->loadMissing(['areaPreparacion', 'pedido.mesa', 'items']);

        return [
            'id' => $comanda->id,
            'estado' => $comanda->estado->value,
            'area' => $comanda->areaPreparacion->nombre,
            'pedido_id' => $comanda->pedido_id,
            'mesa' => $comanda->pedido->mesa?->nombre,
            'tipo_pedido' => $comanda->pedido->tipo->value,
            'created_at' => $comanda->created_at->toIso8601String(),
            'items' => $comanda->items->map(fn (ItemPedido $item) => [
                'nombre_producto' => $item->nombre_producto,
                'cantidad' => $item->cantidad,
                'notas' => $item->notas,
            ])->values(),
        ];
    }
}
