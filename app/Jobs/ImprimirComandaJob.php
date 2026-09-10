<?php

namespace App\Jobs;

use App\Models\Comanda;
use App\Services\Impresion\TicketComanda;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;

/**
 * Imprime el ticket de una comanda en la impresora configurada en su área
 * (ver docs/DECISIONES.md) — en cola, no bloquea el flujo de venta si la
 * impresora está apagada/no responde (ver .claude/rules/laravel.md,
 * "impresión" es justo el ejemplo que ya pedía Jobs con cola). Los
 * reintentos automáticos de la cola cubren "impresora apagada
 * momentáneamente" sin lógica de reintento propia.
 */
class ImprimirComandaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public Comanda $comanda) {}

    public function handle(): void
    {
        $area = $this->comanda->areaPreparacion;

        if (! $area->tieneImpresora()) {
            return;
        }

        // Timeout corto (segundos): si la impresora no responde, falla
        // rápido y deja que la cola reintente, en vez de colgar el worker.
        $conector = new NetworkPrintConnector($area->impresora_ip, $area->impresora_puerto, 5);
        $printer = new Printer($conector);

        try {
            TicketComanda::imprimir($this->comanda, $printer);
        } finally {
            $printer->close();
        }
    }
}
