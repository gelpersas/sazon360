<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoMovimientoCaja: string implements HasColor, HasLabel
{
    case Ingreso = 'ingreso';
    case Egreso = 'egreso';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Egreso => 'Egreso',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ingreso => 'success',
            self::Egreso => 'danger',
        };
    }
}
