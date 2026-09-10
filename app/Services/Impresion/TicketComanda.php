<?php

namespace App\Services\Impresion;

use App\Models\Comanda;
use Mike42\Escpos\Printer;

/**
 * Construye el ticket ESC/POS de una comanda (ver docs/DECISIONES.md) —
 * recibe un `Printer` ya armado en vez de abrirlo/cerrarlo ella misma, para
 * poder probar el contenido con un `MemoryPrintConnector` sin necesitar un
 * socket real (`Printer::close()` finaliza el conector, así que cerrarlo
 * queda a cargo de quien lo abrió — ver App\Jobs\ImprimirComandaJob).
 */
class TicketComanda
{
    public static function imprimir(Comanda $comanda, Printer $printer): void
    {
        $comanda->loadMissing(['areaPreparacion', 'pedido.mesa', 'items']);

        $printer->initialize();

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(2, 2);
        $printer->text($comanda->areaPreparacion->nombre."\n");
        $printer->setTextSize(1, 1);
        $printer->text(str_repeat('-', 32)."\n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("Pedido #{$comanda->pedido_id}\n");
        $printer->text(
            $comanda->pedido->mesa
                ? "Mesa: {$comanda->pedido->mesa->nombre}\n"
                : 'Tipo: '.$comanda->pedido->tipo->getLabel()."\n"
        );
        $printer->text($comanda->created_at->format('d/m/Y H:i')."\n");
        $printer->text(str_repeat('-', 32)."\n");

        foreach ($comanda->items as $item) {
            $printer->text("{$item->cantidad}x {$item->nombre_producto}\n");

            if (filled($item->notas)) {
                $printer->text("   Nota: {$item->notas}\n");
            }
        }

        $printer->feed(3);
        $printer->cut();
    }
}
