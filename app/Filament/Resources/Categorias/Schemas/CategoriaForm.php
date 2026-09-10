<?php

namespace App\Filament\Resources\Categorias\Schemas;

use App\Filament\Support\ToggleEstado;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->scopedUnique(),
                TextInput::make('orden')
                    ->numeric()
                    ->default(0)
                    ->required(),
                ToggleEstado::make(valorActivo: 'activa', valorInactivo: 'inactiva'),
            ]);
    }
}
