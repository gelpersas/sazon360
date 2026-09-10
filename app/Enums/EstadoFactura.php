<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoFactura: string implements HasColor, HasLabel
{
    case Pendiente = 'pendiente';
    case Emitida = 'emitida';
    case Rechazada = 'rechazada';
    case Anulada = 'anulada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Emitida => 'Emitida',
            self::Rechazada => 'Rechazada',
            self::Anulada => 'Anulada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Emitida => 'success',
            self::Rechazada => 'danger',
            self::Anulada => 'gray',
        };
    }
}
