<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TipoMovimientoInventario: string implements HasColor, HasLabel
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case Ajuste = 'ajuste';
    case Merma = 'merma';

    public function getLabel(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
            self::Ajuste => 'Ajuste (fija el stock)',
            self::Merma => 'Merma (pérdida/daño)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Entrada => 'success',
            self::Salida => 'danger',
            self::Ajuste => 'warning',
            self::Merma => 'danger',
        };
    }

    /**
     * Una merma resta stock igual que una salida — es una salida con un
     * motivo específico (pérdida/daño), no un mecanismo distinto. Ver
     * Inventario::registrarMovimiento().
     */
    public function resta(): bool
    {
        return $this === self::Salida || $this === self::Merma;
    }
}
