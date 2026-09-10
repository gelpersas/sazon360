<?php

namespace App\Filament\Resources\Compras\Tables;

use App\Models\Compra;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ComprasTable
{
    /**
     * Sin EditAction/DeleteAction: una compra registrada ya movió inventario
     * — ver docs/DECISIONES.md (Fase 7).
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('sede.nombre')
                    ->label('Sede'),
                TextColumn::make('proveedor.nombre')
                    ->label('Proveedor')
                    ->searchable(),
                TextColumn::make('numero_factura_proveedor')
                    ->label('N.º factura')
                    ->placeholder('—'),
                TextColumn::make('total')
                    ->label('Total')
                    ->state(fn (Compra $record) => $record->total())
                    ->money('usd'),
                TextColumn::make('usuario.name')
                    ->label('Registrada por'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
