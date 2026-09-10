<?php

namespace App\Filament\Resources\Compras;

use App\Filament\Resources\Compras\Pages\ListCompras;
use App\Filament\Resources\Compras\Pages\ViewCompra;
use App\Filament\Resources\Compras\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Compras\Schemas\CompraForm;
use App\Filament\Resources\Compras\Schemas\CompraInfolist;
use App\Filament\Resources\Compras\Tables\ComprasTable;
use App\Models\Compra;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CompraResource extends Resource
{
    protected static ?string $model = Compra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario y compras';

    public static function getModelLabel(): string
    {
        return 'compra';
    }

    public static function getPluralModelLabel(): string
    {
        return 'compras';
    }

    public static function form(Schema $schema): Schema
    {
        return CompraForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompraInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComprasTable::configure($table);
    }

    /**
     * Mismo patrón que CajaResource: además del tenant (empresa), un
     * administración de sede solo ve las compras de su sede.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $empresa = Filament::getTenant();
        $user = Auth::user();

        if (! $empresa || ! $user || $user->esAdminCentralDe($empresa)) {
            return $query;
        }

        return $query->whereIn('sede_id', $user->sedesAccesibles($empresa)->pluck('id'));
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompras::route('/'),
            'view' => ViewCompra::route('/{record}'),
        ];
    }
}
