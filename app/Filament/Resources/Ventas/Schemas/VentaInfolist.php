<?php

namespace App\Filament\Resources\Ventas\Schemas;

use App\Models\Pedido;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class VentaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        TextEntry::make('sede.nombre')->label('Sede'),
                        TextEntry::make('tipo')->label('Tipo')->badge(),
                        TextEntry::make('estado')->label('Estado')->badge(),
                        TextEntry::make('mesa.nombre')->label('Mesa')->placeholder('—'),
                        TextEntry::make('cliente.nombre')->label('Cliente')->placeholder('—'),
                        TextEntry::make('usuario.name')->label('Atendido por'),
                        TextEntry::make('created_at')->label('Creado')->dateTime('d/m/Y H:i'),
                        TextEntry::make('cerrado_at')->label('Cerrado')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('total')
                            ->label('Total')
                            ->state(fn (Pedido $record) => $record->total())
                            ->money('usd'),
                    ]),
                TextEntry::make('notas')->label('Notas')->placeholder('—')->columnSpanFull(),
            ]);
    }
}
