<?php

namespace App\Filament\Resources\Ventas\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Solo lectura: un pago registrado no se corrige ni se borra desde el panel
 * (es historial de auditoría, mismo criterio que MovimientoCaja).
 */
class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';

    protected static ?string $title = 'Pagos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('medio')->label('Medio')->badge(),
                TextColumn::make('monto')->label('Monto')->money('usd'),
                TextColumn::make('usuario.name')->label('Cobrado por'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
