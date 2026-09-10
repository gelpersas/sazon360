<?php

namespace App\Filament\Resources\Productos\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * La receta de un producto: qué insumos y cuánto de cada uno consume una
 * unidad vendida. Ver docs/DECISIONES.md DEC-014 (cuándo se descuenta).
 */
class RecetaItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'recetaItems';

    protected static ?string $title = 'Receta';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('insumo_id')
                    ->label('Insumo')
                    ->relationship('insumo', 'nombre')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('cantidad')
                    ->label('Cantidad por unidad vendida')
                    ->numeric()
                    ->minValue(0.001)
                    ->step(0.001)
                    ->required(),
            ]);
    }

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
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar insumo a la receta'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
