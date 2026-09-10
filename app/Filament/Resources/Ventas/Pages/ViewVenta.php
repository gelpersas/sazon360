<?php

namespace App\Filament\Resources\Ventas\Pages;

use App\Filament\Resources\Ventas\VentaResource;
use App\Models\Pedido;
use App\Support\ReciboPdf;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewVenta extends ViewRecord
{
    protected static string $resource = VentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recibo')
                ->label('Descargar recibo')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn (Pedido $record) => ReciboPdf::generar($record)),
        ];
    }
}
