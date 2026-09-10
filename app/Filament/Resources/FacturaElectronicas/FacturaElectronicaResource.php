<?php

namespace App\Filament\Resources\FacturaElectronicas;

use App\Filament\Resources\FacturaElectronicas\Pages\ListFacturasElectronicas;
use App\Filament\Resources\FacturaElectronicas\Tables\FacturasElectronicasTable;
use App\Models\FacturaElectronica;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Solo lectura (+ acción "Reintentar"): una factura electrónica no se crea
 * ni edita a mano, solo la genera FacturaElectronica::emitirPara() al
 * cobrarse un pedido — ver docs/DECISIONES.md DEC-018/DEC-019.
 */
class FacturaElectronicaResource extends Resource
{
    protected static ?string $model = FacturaElectronica::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Facturación';

    public static function getModelLabel(): string
    {
        return 'factura electrónica';
    }

    public static function getPluralModelLabel(): string
    {
        return 'facturación electrónica';
    }

    public static function table(Table $table): Table
    {
        return FacturasElectronicasTable::configure($table);
    }

    /**
     * Mismo patrón de scoping por sede que InventarioResource/CajaResource,
     * vía la sede del pedido asociado.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $empresa = Filament::getTenant();
        $user = Auth::user();

        if (! $empresa || ! $user || $user->esAdminCentralDe($empresa)) {
            return $query;
        }

        return $query->whereHas(
            'pedido',
            fn (Builder $pedidoQuery) => $pedidoQuery->whereIn('sede_id', $user->sedesAccesibles($empresa)->pluck('id')),
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
            'index' => ListFacturasElectronicas::route('/'),
        ];
    }
}
