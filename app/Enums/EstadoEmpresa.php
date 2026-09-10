<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoEmpresa: string implements HasColor, HasLabel
{
    case Activa = 'activa';
    case Suspendida = 'suspendida';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Suspendida => 'Suspendida',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Activa => 'success',
            self::Suspendida => 'warning',
            self::Cancelada => 'danger',
        };
    }
}
