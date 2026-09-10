<?php

namespace App\Filament\Resources\Inventarios;

use App\Filament\Resources\Inventarios\Pages\ListInventarios;
use App\Filament\Resources\Inventarios\Tables\InventariosTable;
use App\Models\Inventario;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Sin páginas de crear/editar: las filas de Inventario solo cambian a
 * través de Inventario::registrarMovimiento() (acción "Registrar
 * movimiento" en el listado) o del descuento automático al cobrar — nunca
 * se editan campos sueltos a mano. Ver docs/DECISIONES.md DEC-017.
 */
class InventarioResource extends Resource
{
    protected static ?string $model = Inventario::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario y compras';

    public static function getModelLabel(): string
    {
        return 'inventario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'inventario';
    }

    public static function table(Table $table): Table
    {
        return InventariosTable::configure($table);
    }

    /**
     * Mismo patrón que MesaResource/CajaResource: además del tenant
     * (empresa), un administración de sede solo ve el inventario de su sede.
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
            'index' => ListInventarios::route('/'),
        ];
    }
}
