<?php

namespace App\Filament\Resources\Productos;

use App\Filament\Resources\Productos\Pages\ListProductos;
use App\Filament\Resources\Productos\RelationManagers\RecetaItemsRelationManager;
use App\Filament\Resources\Productos\Schemas\ProductoForm;
use App\Filament\Resources\Productos\Tables\ProductosTable;
use App\Models\Producto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function getModelLabel(): string
    {
        return 'producto';
    }

    public static function getPluralModelLabel(): string
    {
        return 'productos';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RecetaItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductos::route('/'),
        ];
    }
}
