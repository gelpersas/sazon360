<?php

namespace App\Filament\Resources\Cajas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class CajaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextEntry::make('sede.nombre')->label('Sede'),
                        TextEntry::make('estado')
                            ->badge(),
                        TextEntry::make('usuarioApertura.name')->label('Abierta por'),
                        TextEntry::make('abierta_at')->label('Apertura')->dateTime('d/m/Y H:i'),
                        TextEntry::make('usuarioCierre.name')->label('Cerrada por')->placeholder('—'),
                        TextEntry::make('cerrada_at')->label('Cierre')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ]),
                Grid::make(4)
                    ->schema([
                        TextEntry::make('monto_inicial')->label('Monto inicial')->money('usd'),
                        TextEntry::make('monto_cierre_esperado')->label('Esperado al cierre')->money('usd')->placeholder('—'),
                        TextEntry::make('monto_cierre_real')->label('Real contado')->money('usd')->placeholder('—'),
                        TextEntry::make('diferencia')->label('Diferencia')->money('usd')->placeholder('—')
                            ->color(fn (?string $state): ?string => $state === null ? null : ((float) $state === 0.0 ? 'success' : 'danger')),
                    ]),
                TextEntry::make('nota_apertura')->label('Nota de apertura')->placeholder('—')->columnSpanFull(),
                TextEntry::make('nota_cierre')->label('Nota de cierre')->placeholder('—')->columnSpanFull(),
            ]);
    }
}
