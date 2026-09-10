<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Mapea 1:1 a `legal_organization_code` de Factus (ver
 * FactusProveedor::construirCliente()): "1" = persona jurídica, "2" =
 * persona natural — confirmado contra el SDK oficial de Factus
 * (sbetav/factus-js, packages/factus-js/src/constants.ts,
 * OrganizationTypeCode), no adivinado.
 */
enum TipoPersona: string implements HasLabel
{
    case Natural = 'natural';
    case Juridica = 'juridica';

    public function getLabel(): string
    {
        return match ($this) {
            self::Natural => 'Persona natural',
            self::Juridica => 'Persona jurídica',
        };
    }

    public function codigoFactus(): string
    {
        return match ($this) {
            self::Natural => '2',
            self::Juridica => '1',
        };
    }
}
