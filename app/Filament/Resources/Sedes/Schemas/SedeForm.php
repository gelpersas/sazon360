<?php

namespace App\Filament\Resources\Sedes\Schemas;

use App\Filament\Support\ToggleEstado;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SedeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->scopedUnique(),
                ToggleEstado::make(valorActivo: 'activa', valorInactivo: 'inactiva'),
            ]);
    }
}
