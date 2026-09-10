<?php

namespace App\Filament\Resources\Compras\Pages;

use App\Filament\Resources\Compras\CompraResource;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Sede;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ListCompras extends ListRecords
{
    protected static string $resource = CompraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->registrarCompraAction(),
        ];
    }

    /**
     * "Registrar compra" como modal (ver docs/DECISIONES.md DEC-041) —
     * antes era la página CreateCompra, con este mismo cuerpo en
     * handleRecordCreation(). Pasa por Compra::registrar(), que arma
     * encabezado + ítems + aumenta inventario en una sola transacción — no
     * un create() genérico.
     */
    protected function registrarCompraAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Registrar compra')
            ->using(function (array $data): Compra {
                try {
                    return Compra::registrar(
                        sede: Sede::findOrFail($data['sede_id']),
                        proveedor: Proveedor::findOrFail($data['proveedor_id']),
                        usuario: Auth::user(),
                        items: $data['items'],
                        numeroFacturaProveedor: $data['numero_factura_proveedor'] ?? null,
                        notas: $data['notas'] ?? null,
                    );
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo registrar la compra')
                        ->body($e->getMessage())
                        ->send();

                    throw new Halt;
                }
            });
    }
}
