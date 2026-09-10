<?php

namespace App\Support;

use App\Models\Pedido;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recibo PDF de una venta (ver docs/DECISIONES.md DEC-067) — reutilizado
 * desde la tabla y la vista de detalle de VentaResource, para no duplicar
 * la generación en dos Actions distintas.
 */
class ReciboPdf
{
    /**
     * Livewire::SupportFileDownloads solo dispara la descarga del navegador
     * si el retorno de una Action es StreamedResponse/BinaryFileResponse
     * (ver vendor/livewire/livewire/src/Features/SupportFileDownloads) — el
     * Response plano que devuelve Pdf::download() se descarta en silencio,
     * sin error visible. Por eso se envuelve el contenido ya generado en un
     * streamDownload() real en vez de devolver la respuesta de dompdf tal cual.
     */
    public static function generar(Pedido $pedido): StreamedResponse
    {
        $pedido->loadMissing(['empresa', 'sede', 'mesa', 'cliente', 'usuario', 'items', 'pagos.usuario']);

        $pdf = Pdf::loadView('pdf.recibo', ['pedido' => $pedido])->output();

        return response()->streamDownload(
            fn () => print ($pdf),
            "recibo-venta-{$pedido->id}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
