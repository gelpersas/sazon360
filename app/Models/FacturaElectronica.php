<?php

namespace App\Models;

use App\Enums\EstadoFactura;
use App\Jobs\EmitirFacturaElectronicaJob;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['empresa_id', 'pedido_id', 'proveedor', 'estado', 'numero', 'cufe', 'pdf_url', 'xml_url', 'error_mensaje', 'respuesta_proveedor'])]
class FacturaElectronica extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estado' => EstadoFactura::class,
            'respuesta_proveedor' => 'array',
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

    /**
     * Crea (si no existe) la factura `pendiente` de un pedido y encola su
     * emisión. Idempotente por `pedido_id` único: llamarla de nuevo sobre un
     * pedido que ya tiene factura pendiente/rechazada reencola la emisión en
     * vez de duplicar la fila (ver reintentar()); si ya está emitida, no
     * hace nada.
     */
    public static function emitirPara(Pedido $pedido): self
    {
        $factura = static::firstOrCreate(
            ['pedido_id' => $pedido->id],
            [
                'empresa_id' => $pedido->empresa_id,
                'proveedor' => config('facturacion.proveedor'),
                'estado' => EstadoFactura::Pendiente,
            ],
        );

        if ($factura->estado !== EstadoFactura::Emitida) {
            // afterCommit(): se llama desde dentro de la transacción de
            // Pago::registrar() — el Job no debe procesar la factura antes
            // de que el pago/pedido cobrado queden realmente confirmados.
            EmitirFacturaElectronicaJob::dispatch($factura)->afterCommit();
        }

        return $factura;
    }

    /**
     * Vuelve a intentar una emisión rechazada (acción "Reintentar" en
     * Filament) — no crea una fila nueva, reutiliza esta misma.
     */
    public function reintentar(): void
    {
        $this->update(['estado' => EstadoFactura::Pendiente, 'error_mensaje' => null]);

        EmitirFacturaElectronicaJob::dispatch($this)->afterCommit();
    }

    public function marcarEmitida(?string $numero, ?string $cufe, ?string $pdfUrl, ?string $xmlUrl, array $respuestaCruda): void
    {
        $this->update([
            'estado' => EstadoFactura::Emitida,
            'numero' => $numero,
            'cufe' => $cufe,
            'pdf_url' => $pdfUrl,
            'xml_url' => $xmlUrl,
            'error_mensaje' => null,
            'respuesta_proveedor' => $respuestaCruda,
        ]);
    }

    public function marcarRechazada(string $mensajeError, array $respuestaCruda = []): void
    {
        $this->update([
            'estado' => EstadoFactura::Rechazada,
            'error_mensaje' => $mensajeError,
            'respuesta_proveedor' => $respuestaCruda,
        ]);
    }
}
