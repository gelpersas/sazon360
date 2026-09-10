<?php

namespace App\Filament\Resources\TrasladoInventarios;

use App\Filament\Resources\TrasladoInventarios\Pages\ListTrasladoInventarios;
use App\Filament\Resources\TrasladoInventarios\Tables\TrasladoInventariosTable;
use App\Models\TrasladoInventario;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Sin páginas de crear/editar: un traslado solo se genera vía
 * TrasladoInventario::realizar() (acción "Nuevo traslado" en el listado) —
 * mismo patrón que InventarioResource.
 */
class TrasladoInventarioResource extends Resource
{
    protected static ?string $model = TrasladoInventario::class;

    protected static ?string $slug = 'traslados-inventario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario y compras';

    public static function getModelLabel(): string
    {
        return 'traslado';
    }

    public static function getPluralModelLabel(): string
    {
        return 'traslados de inventario';
    }

    public static function table(Table $table): Table
    {
        return TrasladoInventariosTable::configure($table);
    }

    /**
     * A diferencia de InventarioResource (una sola sede por fila), un
     * traslado involucra dos — se ve si el usuario tiene acceso a
     * cualquiera de las dos.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $empresa = Filament::getTenant();
        $user = Auth::user();

        if (! $empresa || ! $user || $user->esAdminCentralDe($empresa)) {
            return $query;
        }

        $sedeIds = $user->sedesAccesibles($empresa)->pluck('id');

        return $query->where(
            fn (Builder $q) => $q->whereIn('sede_origen_id', $sedeIds)->orWhereIn('sede_destino_id', $sedeIds)
        );
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
            'index' => ListTrasladoInventarios::route('/'),
        ];
    }
}
