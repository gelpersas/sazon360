<?php

namespace App\Filament\Resources\Productos\Tables;

use App\Filament\Support\BorradoSeguro;
use App\Models\Producto;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // getStateUsing() en vez de ->disk('public') porque
                // ImageColumn resuelve la URL con Storage::url(), que
                // antepone APP_URL completo — rompe la miniatura si se
                // navega por un host distinto (ver Producto::imagenUrl(),
                // que ya construye una ruta relativa correcta).
                ImageColumn::make('imagen_path')
                    ->label('')
                    ->getStateUsing(fn (Producto $record): ?string => $record->imagen_url)
                    ->square(),
                TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('descripcion')
                    ->label('Detalle')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->sortable(),
                TextColumn::make('precio')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'activo' => 'success',
                        'agotado' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                        'agotado' => 'Agotado',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(fn ($records) => BorradoSeguro::variosRegistros($records, 'productos')),
                ]),
            ]);
    }
}
