<?php

namespace App\Filament\Resources\Compras\RelationManagers;

use App\Models\CompraItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Solo lectura: los ítems de una compra ya movieron inventario al
 * registrarse (Compra::registrar()) — no se agregan, editan ni borran desde
 * aquí, a diferencia de RecetaItemsRelationManager (que sí es un catálogo
 * editable). Ver docs/DECISIONES.md (Fase 7).
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Ítems';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('insumo_id')
            ->columns([
                TextColumn::make('insumo.nombre')
                    ->label('Insumo'),
                TextColumn::make('cantidad'),
                TextColumn::make('insumo.unidad_medida')
                    ->label('Unidad'),
                TextColumn::make('costo_unitario')
                    ->label('Costo unitario')
                    ->money('usd'),
                TextColumn::make('subtotal')
                    ->state(fn (CompraItem $record) => $record->subtotal())
                    ->money('usd'),
            ])
            ->filters([
                //
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
