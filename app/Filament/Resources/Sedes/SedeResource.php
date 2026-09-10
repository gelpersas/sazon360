<?php

namespace App\Filament\Resources\Sedes;

use App\Filament\Resources\Sedes\Pages\ListSedes;
use App\Filament\Resources\Sedes\Schemas\SedeForm;
use App\Filament\Resources\Sedes\Tables\SedesTable;
use App\Models\Sede;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SedeResource extends Resource
{
    protected static ?string $model = Sede::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function getModelLabel(): string
    {
        return 'sede';
    }

    public static function getPluralModelLabel(): string
    {
        return 'sedes';
    }

    /**
     * Solo administración central lo ve en el menú — ver la lista de todas
     * las sedes de la empresa no aporta nada al trabajo diario de
     * administración de sede (a diferencia de Categorías/Productos/Insumos/
     * Proveedores, que sí tienen valor de consulta aunque no pueda editarlos).
     * Ocultar del menú no bloquea el acceso directo por URL — eso lo sigue
     * gobernando SedePolicy/getEloquentQuery(), sin cambios; quien ya
     * administra su propia sede sigue pudiendo verla/editarla si entra por
     * el link directo.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $empresa = Filament::getTenant();
        $user = Auth::user();

        return $empresa !== null && $user !== null && $user->esAdminCentralDe($empresa);
    }

    public static function form(Schema $schema): Schema
    {
        return SedeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SedesTable::configure($table);
    }

    /**
     * El tenancy de Filament ya filtra por empresa; aquí se agrega el
     * segundo nivel (sede) para que administración de sede solo vea su
     * propia sede, no todas las de la empresa (ver MesaResource, mismo patrón).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $empresa = Filament::getTenant();
        $user = Auth::user();

        if (! $empresa || ! $user || $user->esAdminCentralDe($empresa)) {
            return $query;
        }

        return $query->whereIn('id', $user->sedesAccesibles($empresa)->pluck('id'));
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
            'index' => ListSedes::route('/'),
        ];
    }
}
