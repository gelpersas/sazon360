<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoPedido: string implements HasLabel
{
    case Mesa = 'mesa';
    case Mostrador = 'mostrador';
    case ParaLlevar = 'para_llevar';
    case Domicilio = 'domicilio';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mesa => 'Mesa',
            self::Mostrador => 'Mostrador',
            self::ParaLlevar => 'Para llevar',
            self::Domicilio => 'Domicilio',
        };
    }

    public function requiereMesa(): bool
    {
        return $this === self::Mesa;
    }
}
