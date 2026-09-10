<?php

namespace App\Filament\Resources\TrasladoInventarios\Tables;

use App\Models\TrasladoInventario;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Solo lectura: un traslado, una vez realizado, no se edita ni se
 * deshace — si se hizo mal, se compensa con otro traslado en sentido
 * contrario (mismo criterio que MovimientoCaja/MovimientoInventario).
 */
class TrasladoInventariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('insumo.nombre')
                    ->label('Insumo')
                    ->searchable(),
                TextColumn::make('sedeOrigen.nombre')
                    ->label('Origen'),
                TextColumn::make('sedeDestino.nombre')
                    ->label('Destino'),
                TextColumn::make('cantidad')
                    ->suffix(fn (TrasladoInventario $record) => ' '.$record->insumo->unidad_medida),
                TextColumn::make('usuario.name')
                    ->label('Registrado por'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ]);
    }
}
