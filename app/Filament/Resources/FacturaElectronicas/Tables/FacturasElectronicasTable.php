<?php

namespace App\Filament\Resources\FacturaElectronicas\Tables;

use App\Enums\EstadoFactura;
use App\Models\FacturaElectronica;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FacturasElectronicasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('pedido_id')
                    ->label('Pedido')
                    ->prefix('#')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('pedido.sede.nombre')
                    ->label('Sede')
                    ->searchable(),
                TextColumn::make('proveedor')
                    ->badge(),
                TextColumn::make('estado')
                    ->badge(),
                TextColumn::make('numero')
                    ->label('Número')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('cufe')
                    ->label('CUFE')
                    ->placeholder('—')
                    ->limit(20)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('error_mensaje')
                    ->label('Error')
                    ->placeholder('—')
                    ->limit(40)
                    ->color('danger')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(EstadoFactura::class),
            ])
            ->recordActions([
                self::verFacturaAction(),
                self::reintentarAction(),
            ]);
    }

    private static function verFacturaAction(): Action
    {
        return Action::make('verFactura')
            ->label('Ver factura')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->url(fn (FacturaElectronica $record): string => $record->pdf_url)
            ->openUrlInNewTab()
            ->visible(fn (FacturaElectronica $record): bool => filled($record->pdf_url) && Auth::user()->can('view', $record));
    }

    private static function reintentarAction(): Action
    {
        return Action::make('reintentar')
            ->label('Reintentar')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (FacturaElectronica $record): bool => $record->estado === EstadoFactura::Rechazada && Auth::user()->can('update', $record))
            ->action(function (FacturaElectronica $record): void {
                $record->reintentar();

                Notification::make()->success()->title('Emisión reencolada')->send();
            });
    }
}
