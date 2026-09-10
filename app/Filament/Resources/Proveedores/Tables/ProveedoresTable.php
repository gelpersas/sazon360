<?php

namespace App\Filament\Resources\Proveedores\Tables;

use App\Filament\Support\BorradoSeguro;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProveedoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contacto')
                    ->label('Contacto')
                    ->placeholder('—'),
                TextColumn::make('telefono')
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->placeholder('—'),
                TextColumn::make('estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'activo' ? 'success' : 'gray'),
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
                        ->action(fn ($records) => BorradoSeguro::variosRegistros($records, 'proveedores')),
                ]),
            ]);
    }
}
