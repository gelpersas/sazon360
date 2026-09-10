<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Toggle;

/**
 * Switch activo/inactivo reutilizable — reemplaza el `Select` de 2
 * opciones que varios Resources usaban para su columna `estado` (ver
 * docs/DECISIONES.md): con solo dos opciones excluyentes, un switch
 * comunica el estado más rápido que un desplegable. La columna real en
 * base de datos sigue guardando el string de siempre ('activo'/'inactivo',
 * 'activa'/'inactiva' según el género de cada entidad) — este helper solo
 * traduce entre ese string y el booleano interno del Toggle.
 */
class ToggleEstado
{
    public static function make(string $valorActivo = 'activo', string $valorInactivo = 'inactivo'): Toggle
    {
        return Toggle::make('estado')
            ->label('Activo')
            ->default(true)
            ->afterStateHydrated(
                fn (Toggle $component, $state) => $component->state($state === $valorActivo || $state === true),
            )
            ->dehydrateStateUsing(fn ($state) => $state ? $valorActivo : $valorInactivo)
            ->inline(false);
    }
}
