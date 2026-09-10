<?php

namespace App\Filament\Resources\AreaPreparacions\Schemas;

use App\Filament\Support\ToggleEstado;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AreaPreparacionForm
{
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
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->scopedUnique(
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query->where('sede_id', $get('sede_id')),
                    ),
                TextInput::make('orden')
                    ->numeric()
                    ->default(0)
                    ->required(),
                ToggleEstado::make(valorActivo: 'activa', valorInactivo: 'inactiva'),
                TextInput::make('impresora_ip')
                    ->label('IP de la impresora')
                    ->helperText('Opcional — sin esto, el área sigue funcionando solo con el KDS. Debe poder alcanzarse desde el servidor (IP pública con el puerto redirigido, no una IP privada de la red local).')
                    ->ip()
                    ->nullable(),
                TextInput::make('impresora_puerto')
                    ->label('Puerto de la impresora')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->nullable()
                    ->requiredWith('impresora_ip'),
            ]);
    }
}
