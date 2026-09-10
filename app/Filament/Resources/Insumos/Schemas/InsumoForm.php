<?php

namespace App\Filament\Resources\Insumos\Schemas;

use App\Filament\Support\ToggleEstado;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InsumoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->scopedUnique(),
                Select::make('unidad_medida')
                    ->label('Unidad de medida')
                    ->options([
                        'g' => 'Gramos (g)',
                        'kg' => 'Kilogramos (kg)',
                        'ml' => 'Mililitros (ml)',
                        'l' => 'Litros (l)',
                        'unidad' => 'Unidad',
                    ])
                    ->required(),
                TextInput::make('stock_minimo')
                    ->label('Stock mínimo (para alertas)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                ToggleEstado::make(),
            ]);
    }
}
