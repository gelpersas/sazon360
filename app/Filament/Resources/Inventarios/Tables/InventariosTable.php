<?php

namespace App\Filament\Resources\Inventarios\Tables;

use App\Models\Inventario;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sede.nombre')
                    ->label('Sede')
                    ->sortable(),
                TextColumn::make('insumo.nombre')
                    ->label('Insumo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cantidad_actual')
                    ->label('Stock actual')
                    ->suffix(fn (Inventario $record) => ' '.$record->insumo->unidad_medida)
                    ->badge()
                    ->color(fn (Inventario $record): string => $record->bajoMinimo() ? 'danger' : 'success'),
                TextColumn::make('insumo.stock_minimo')
                    ->label('Stock mínimo')
                    ->suffix(fn (Inventario $record) => ' '.$record->insumo->unidad_medida),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ]);
    }
}
