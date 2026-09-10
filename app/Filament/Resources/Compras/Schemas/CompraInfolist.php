<?php

namespace App\Filament\Resources\Compras\Schemas;

use App\Models\Compra;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CompraInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('sede.nombre')->label('Sede'),
                        TextEntry::make('proveedor.nombre')->label('Proveedor'),
                        TextEntry::make('numero_factura_proveedor')->label('N.º de factura')->placeholder('—'),
                        TextEntry::make('usuario.name')->label('Registrada por'),
                        TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                        TextEntry::make('total')
                            ->label('Total')
                            ->state(fn (Compra $record) => $record->total())
                            ->money('usd'),
                    ]),
                TextEntry::make('notas')->placeholder('—')->columnSpanFull(),
            ]);
    }
}
