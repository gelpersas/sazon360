<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoCaja: string implements HasColor, HasLabel
{
    case Abierta = 'abierta';
    case Cerrada = 'cerrada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::Cerrada => 'Cerrada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Abierta => 'success',
            self::Cerrada => 'gray',
        };
    }
}
