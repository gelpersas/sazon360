<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoPedido: string implements HasColor, HasLabel
{
    case Abierto = 'abierto';
    case Cobrado = 'cobrado';
    case Anulado = 'anulado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::Cobrado => 'Cobrado',
            self::Anulado => 'Anulado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Abierto => 'warning',
            self::Cobrado => 'success',
            self::Anulado => 'gray',
        };
    }
}
