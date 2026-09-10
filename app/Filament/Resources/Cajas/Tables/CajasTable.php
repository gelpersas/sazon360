<?php

namespace App\Filament\Resources\Cajas\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CajasTable
{
    /**
     * Sin EditAction (apertura/cierre son acciones dedicadas, no un
     * formulario genérico) ni DeleteBulkAction (es el historial/auditoría
     * de caja — no se borra, ver docs/REGLAS-NEGOCIO.md "Caja").
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('abierta_at', 'desc')
            ->columns([
                TextColumn::make('sede.nombre')
                    ->label('Sede')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('usuarioApertura.name')
                    ->label('Abierta por')
                    ->searchable(),
                TextColumn::make('abierta_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('cerrada_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('diferencia')
                    ->label('Diferencia')
                    ->money('usd')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options([
                        'abierta' => 'Abierta',
                        'cerrada' => 'Cerrada',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
