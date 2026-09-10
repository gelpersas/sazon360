<?php

namespace App\Filament\Resources\Mesas\Tables;

use App\Filament\Support\BorradoSeguro;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MesasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sede.nombre')
                    ->label('Sede')
                    ->sortable(),
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('piso')
                    ->toggleable(),
                TextColumn::make('zona')
                    ->toggleable(),
                TextColumn::make('capacidad')
                    ->toggleable(),
                TextColumn::make('estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'activa' ? 'success' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(fn ($records) => BorradoSeguro::variosRegistros($records, 'mesas')),
                ]),
            ]);
    }
}
