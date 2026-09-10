<?php

namespace App\Filament\Resources\AreaPreparacions\Tables;

use App\Filament\Support\BorradoSeguro;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AreaPreparacionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('sede.nombre')
                    ->label('Sede')
                    ->sortable(),
                TextColumn::make('orden')->sortable(),
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
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
                        ->action(fn ($records) => BorradoSeguro::variosRegistros($records, 'áreas de preparación')),
                ]),
            ]);
    }
}
