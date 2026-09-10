<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoGrupoMesa: string implements HasColor, HasLabel
{
    case Activo = 'activo';
    case Disuelto = 'disuelto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Disuelto => 'Disuelto',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activo => 'warning',
            self::Disuelto => 'gray',
        };
    }
}
