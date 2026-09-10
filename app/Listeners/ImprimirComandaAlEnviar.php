<?php

namespace App\Listeners;

use App\Enums\EstadoComanda;
use App\Events\ComandaActualizada;
use App\Jobs\ImprimirComandaJob;

/**
 * Imprime solo cuando la comanda se crea (estado Pendiente al disparar
 * Pedido::enviarComanda()) — ComandaActualizada también se dispara en cada
 * avance de estado (Comanda::avanzar()), y no tendría sentido reimprimir un
 * ticket físico cada vez que cocina toca "listo" en el KDS. Reutiliza el
 * mismo evento que ya notifica al KDS (ver .claude/rules/laravel.md,
 * "Eventos + Listeners, no llamadas directas") en vez de enganchar la
 * impresión directo en Pedido::enviarComanda().
 */
class ImprimirComandaAlEnviar
{
    public function handle(ComandaActualizada $event): void
    {
        if ($event->comanda->estado !== EstadoComanda::Pendiente) {
            return;
        }

        if (! $event->comanda->areaPreparacion->tieneImpresora()) {
            return;
        }

        ImprimirComandaJob::dispatch($event->comanda);
    }
}
