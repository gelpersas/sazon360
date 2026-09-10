<?php

namespace App\Filament\Resources\Clientes\Tables;

use App\Filament\Support\BorradoSeguro;
use App\Models\Cliente;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClientesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 'nombre_completo' es un accessor calculado (nombres+
                // apellidos o razón social según el tipo — ver
                // App\Models\Cliente), no una columna real: no se puede
                // ordenar/buscar por ella en SQL directamente, por eso
                // ->searchable() lleva una query explícita en vez de la
                // forma corta.
                TextColumn::make('nombre_completo')
                    ->label('Nombre')
                    ->description(fn (Cliente $record): ?string => filled($record->nombre_comercial) ? $record->nombre_comercial : null)
                    // "nombres || ' ' || apellidos", no solo columnas
                    // sueltas: buscar "Juan Pérez" no debe depender de que
                    // el término quepa entero en una sola columna — mismo
                    // bug real corregido en Pos\ClienteController::index().
                    ->searchable(query: function ($query, string $search) {
                        $termino = mb_strtolower($search);

                        return $query->where(fn ($q) => $q
                            ->whereRaw("LOWER(COALESCE(nombres, '') || ' ' || COALESCE(apellidos, '')) LIKE ?", ["%{$termino}%"])
                            ->orWhereRaw('LOWER(razon_social) LIKE ?', ["%{$termino}%"])
                            ->orWhereRaw('LOWER(nombre_comercial) LIKE ?', ["%{$termino}%"]));
                    }),
                TextColumn::make('tipo_persona')
                    ->badge(),
                TextColumn::make('numero_documento')
                    ->label('Documento')
                    ->description(fn (Cliente $record): ?string => $record->tipo_documento?->getLabel())
                    ->searchable(),
                TextColumn::make('telefono')
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->placeholder('—'),
                TextColumn::make('estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'activo' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(fn ($records) => BorradoSeguro::variosRegistros($records, 'clientes')),
                ]),
            ]);
    }
}
