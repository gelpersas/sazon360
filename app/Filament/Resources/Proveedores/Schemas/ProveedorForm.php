<?php

namespace App\Filament\Resources\Proveedores\Schemas;

use App\Filament\Support\ToggleEstado;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProveedorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->scopedUnique(),
                TextInput::make('contacto')
                    ->label('Persona de contacto')
                    ->maxLength(255),
                TextInput::make('telefono')
                    ->tel()
                    ->maxLength(50),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                ToggleEstado::make(),
            ]);
    }
}
