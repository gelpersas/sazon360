<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MedioPago: string implements HasLabel
{
    case Efectivo = 'efectivo';
    case Tarjeta = 'tarjeta';
    case Transferencia = 'transferencia';

    public function getLabel(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Tarjeta => 'Tarjeta',
            self::Transferencia => 'Transferencia',
        };
    }
}
