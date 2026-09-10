<?php

namespace App\Filament\Resources\Cajas;

use App\Filament\Resources\Cajas\Pages\ListCajas;
use App\Filament\Resources\Cajas\Pages\ViewCaja;
use App\Filament\Resources\Cajas\RelationManagers\MovimientosRelationManager;
use App\Filament\Resources\Cajas\Schemas\CajaForm;
use App\Filament\Resources\Cajas\Schemas\CajaInfolist;
use App\Filament\Resources\Cajas\Tables\CajasTable;
use App\Models\Caja;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CajaResource extends Resource
{
    protected static ?string $model = Caja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    public static function getModelLabel(): string
    {
        return 'caja';
    }

    public static function getPluralModelLabel(): string
    {
        return 'cajas';
    }

    public static function form(Schema $schema): Schema
    {
        return CajaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CajaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CajasTable::configure($table);
    }

    /**
     * Mismo patrón que SedeResource/MesaResource: además del tenant
     * (empresa), un administración de sede solo ve las cajas de su sede.
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
            MovimientosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCajas::route('/'),
            'view' => ViewCaja::route('/{record}'),
        ];
    }
}
