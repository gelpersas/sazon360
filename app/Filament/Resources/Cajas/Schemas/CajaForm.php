<?php

namespace App\Filament\Resources\Cajas\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class CajaForm
{
    /**
     * Solo se usa para "Abrir caja" (CreateCaja) — no hay Edit genérico, ver
     * docs/DECISIONES.md DEC-008 y CajaResource::getPages().
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sede_id')
                    ->label('Sede')
                    ->options(function () {
                        $empresa = Filament::getTenant();

                        if (! $empresa) {
                            return [];
                        }

                        return Auth::user()
                            ->sedesAccesibles($empresa)
                            ->pluck('nombre', 'id');
                    })
                    ->required()
                    ->native(false),
                TextInput::make('monto_inicial')
                    ->label('Monto inicial (fondo de caja)')
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0)
                    ->required(),
                Textarea::make('nota_apertura')
                    ->label('Nota de apertura')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }
}
