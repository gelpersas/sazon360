<?php

namespace App\Filament\Resources\Mesas;

use App\Filament\Resources\Mesas\Pages\ListMesas;
use App\Filament\Resources\Mesas\Schemas\MesaForm;
use App\Filament\Resources\Mesas\Tables\MesasTable;
use App\Models\Mesa;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MesaResource extends Resource
{
    protected static ?string $model = Mesa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function getModelLabel(): string
    {
        return 'mesa';
    }

    public static function getPluralModelLabel(): string
    {
        return 'mesas';
    }

    public static function form(Schema $schema): Schema
    {
        return MesaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MesasTable::configure($table);
    }

    /**
     * El tenancy de Filament ya filtra por empresa; aquí se agrega el
     * segundo nivel (sede) para que administración de sede solo vea las
     * mesas de su propia sede, no las de otras sedes de la misma empresa.
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
            'index' => ListMesas::route('/'),
        ];
    }
}
