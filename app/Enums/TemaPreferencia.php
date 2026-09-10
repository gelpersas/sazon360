<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Preferencia de tema visual del usuario (Filament + POS táctil comparten la
 * misma columna `users.tema`, ya que ambas superficies autentican contra el
 * mismo modelo `User` — ver docs/DECISIONES.md). "Auto" sigue
 * `prefers-color-scheme` del sistema; Claro/Oscuro lo fuerzan.
 */
enum TemaPreferencia: string implements HasLabel
{
    case Claro = 'claro';
    case Oscuro = 'oscuro';
    case Auto = 'auto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Claro => 'Claro',
            self::Oscuro => 'Oscuro',
            self::Auto => 'Automático',
        };
    }
}
