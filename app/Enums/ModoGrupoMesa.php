<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Cómo se comparte el pedido dentro de un GrupoMesa (ver docs/DECISIONES.md):
 * - Independiente (default, comportamiento original de DEC-025): cada mesa
 *   del grupo mantiene su propio Pedido/comanda/factura, sin cambios.
 * - General: el grupo comparte UN solo Pedido, abierto siempre contra la
 *   mesa principal (`GrupoMesa::mesaPrincipal`) — las demás mesas del grupo
 *   no llegan a tener pedido propio mientras el grupo siga en este modo.
 */
enum ModoGrupoMesa: string implements HasLabel
{
    case Independiente = 'independiente';
    case General = 'general';

    public function getLabel(): string
    {
        return match ($this) {
            self::Independiente => 'Independiente (cada mesa su cuenta)',
            self::General => 'General (una sola cuenta para el grupo)',
        };
    }
}
