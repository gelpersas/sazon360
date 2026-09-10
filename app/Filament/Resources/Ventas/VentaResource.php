<?php

namespace App\Filament\Resources\Ventas;

use App\Filament\Resources\Ventas\Pages\ListVentas;
use App\Filament\Resources\Ventas\Pages\ViewVenta;
use App\Filament\Resources\Ventas\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Ventas\RelationManagers\PagosRelationManager;
use App\Filament\Resources\Ventas\Schemas\VentaInfolist;
use App\Filament\Resources\Ventas\Tables\VentasTable;
use App\Models\Pedido;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Historial de ventas — solo lectura (ver docs/DECISIONES.md DEC-067): una
 * venta se genera desde el POS, nunca desde el panel, así que no hay páginas
 * Create/Edit/Delete, mismo patrón que CajaResource.
 */
class VentaResource extends Resource
{
    protected static ?string $model = Pedido::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    public static function getModelLabel(): string
    {
        return 'venta';
    }

    public static function getPluralModelLabel(): string
    {
        return 'ventas';
    }

    public static function infolist(Schema $schema): Schema
    {
        return VentaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VentasTable::configure($table);
    }

    /**
     * Mismo patrón que CajaResource/SedeResource/MesaResource: además del
     * tenant (empresa), administración de sede solo ve las ventas de su sede.
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
            PagosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVentas::route('/'),
            'view' => ViewVenta::route('/{record}'),
        ];
    }
}
