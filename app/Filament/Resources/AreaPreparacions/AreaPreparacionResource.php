<?php

namespace App\Filament\Resources\AreaPreparacions;

use App\Filament\Resources\AreaPreparacions\Pages\ListAreaPreparacions;
use App\Filament\Resources\AreaPreparacions\Schemas\AreaPreparacionForm;
use App\Filament\Resources\AreaPreparacions\Tables\AreaPreparacionsTable;
use App\Models\AreaPreparacion;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AreaPreparacionResource extends Resource
{
    protected static ?string $model = AreaPreparacion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function getModelLabel(): string
    {
        return 'área de preparación';
    }

    public static function getPluralModelLabel(): string
    {
        return 'áreas de preparación';
    }

    public static function form(Schema $schema): Schema
    {
        return AreaPreparacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AreaPreparacionsTable::configure($table);
    }

    /**
     * Mismo patrón que MesaResource: además del tenant (empresa), un
     * administración de sede solo ve las áreas de su propia sede.
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAreaPreparacions::route('/'),
        ];
    }
}
