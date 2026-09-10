<?php

namespace App\Filament\Resources\Accesos;

use App\Filament\Resources\Accesos\Pages\ListAccesos;
use App\Filament\Resources\Accesos\Schemas\AccesoForm;
use App\Filament\Resources\Accesos\Tables\AccesosTable;
use App\Models\Acceso;
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
 * Gestiona `Acceso` (usuario + rol + sede) — no un `UserResource` aparte,
 * porque `User` no tiene `empresa_id` propio (es un modelo global, un mismo
 * usuario puede tener accesos en más de una empresa) y por lo tanto no
 * hereda el scoping automático de tenancy de Filament (ver DEC-005). `Acceso`
 * sí tiene `empresa_id` real, así que este Resource queda correctamente
 * aislado por tenant sin overrides adicionales — ver docs/DECISIONES.md
 * DEC-037.
 */
class AccesoResource extends Resource
{
    protected static ?string $model = Acceso::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    public static function getModelLabel(): string
    {
        return 'usuario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'usuarios';
    }

    public static function form(Schema $schema): Schema
    {
        return AccesoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccesosTable::configure($table);
    }

    /**
     * Administración de sede solo ve/gestiona los accesos de sus propias
     * sedes (y nunca uno de administración central) — mismo patrón que
     * SedeResource/MesaResource, reforzado además por AccesoPolicy.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $empresa = Filament::getTenant();
        $user = Auth::user();

        if (! $empresa || ! $user || $user->esAdminCentralDe($empresa)) {
            return $query;
        }

        return $query
            ->whereIn('sede_id', $user->sedesAccesibles($empresa)->pluck('id'))
            ->where('rol', '!=', 'administracion_central');
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
            'index' => ListAccesos::route('/'),
        ];
    }
}
