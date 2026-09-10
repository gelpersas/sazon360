<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoComanda: string implements HasColor, HasLabel
{
    case Pendiente = 'pendiente';
    case EnPreparacion = 'en_preparacion';
    case Lista = 'lista';
    case Entregada = 'entregada';
    case Anulada = 'anulada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnPreparacion => 'En preparación',
            self::Lista => 'Lista',
            self::Entregada => 'Entregada',
            self::Anulada => 'Anulada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::EnPreparacion => 'warning',
            self::Lista => 'success',
            self::Entregada => 'success',
            self::Anulada => 'danger',
        };
    }

    /**
     * Siguiente estado en el flujo normal del KDS (pendiente → en
     * preparación → lista → entregada). Null si ya no avanza (terminal).
     */
    public function siguiente(): ?self
    {
        return match ($this) {
            self::Pendiente => self::EnPreparacion,
            self::EnPreparacion => self::Lista,
            self::Lista => self::Entregada,
            self::Entregada, self::Anulada => null,
        };
    }
}
