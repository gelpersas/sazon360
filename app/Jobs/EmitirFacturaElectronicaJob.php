<?php

namespace App\Jobs;

use App\Contracts\ProveedorFacturacionElectronica;
use App\Models\FacturaElectronica;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Llama al proveedor de facturación electrónica configurado
 * (App\Contracts\ProveedorFacturacionElectronica, ver DEC-019) para una
 * factura ya creada en estado `pendiente`. No bloquea el cobro del pedido:
 * se encola desde FacturaElectronica::emitirPara(), después de confirmado
 * el pago — un fallo aquí dinero ya cobrado sin comprobante emitido, no un
 * cobro fallido.
 */
class EmitirFacturaElectronicaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public FacturaElectronica $factura) {}

    public function handle(ProveedorFacturacionElectronica $proveedor): void
    {
        try {
            $resultado = $proveedor->emitir($this->factura);
        } catch (Throwable $e) {
            $this->factura->marcarRechazada($e->getMessage());

            return;
        }

        if ($resultado->exitoso) {
            $this->factura->marcarEmitida(
                numero: $resultado->numero,
                cufe: $resultado->cufe,
                pdfUrl: $resultado->pdfUrl,
                xmlUrl: $resultado->xmlUrl,
                respuestaCruda: $resultado->respuestaCruda,
            );

            return;
        }

        $this->factura->marcarRechazada($resultado->mensajeError ?? 'Rechazada por el proveedor sin mensaje.', $resultado->respuestaCruda);
    }
}
