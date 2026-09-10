<?php

namespace App\Filament\Resources\Ventas\RelationManagers;

use App\Models\ItemPedido;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Solo lectura: los ítems de una venta ya cobrada no se crean/editan/borran
 * desde el panel (eso pasa en el POS mientras el pedido sigue abierto).
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Ítems';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre_producto')
            ->columns([
                TextColumn::make('nombre_producto')->label('Producto'),
                TextColumn::make('cantidad')->label('Cantidad'),
                TextColumn::make('precio_unitario')->label('Precio unitario')->money('usd'),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->state(fn (ItemPedido $record) => $record->subtotal())
                    ->money('usd'),
                TextColumn::make('notas')->label('Notas')->placeholder('—'),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
