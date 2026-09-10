<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Código DIAN de tipo de documento — subconjunto común, no el catálogo
 * completo (mismo criterio que `Producto.codigo_impuesto_dian`: confirmar
 * con el contador de Dulcita si un cliente real no encaja aquí).
 *
 * Enum de verdad (no un array plano de opciones) a propósito: los values
 * '13'/'31'/etc. son cadenas numéricas "canónicas" (sin cero a la
 * izquierda) — un array PHP literal con esas claves las convierte
 * automáticamente a enteros (`['31' => 'NIT']` termina con la clave `int
 * 31`, no `string '31'`), lo que rompía comparaciones `===` contra '31' en
 * el formulario (bug real encontrado y corregido — ver
 * docs/DECISIONES.md). Un enum respaldado por string no tiene ese problema:
 * el valor siempre es el string declarado.
 */
enum TipoDocumentoCliente: string implements HasLabel
{
    case CedulaCiudadania = '13';
    case NIT = '31';
    case CedulaExtranjeria = '22';
    case Pasaporte = '41';
    case NUIP = '91';

    public function getLabel(): string
    {
        return match ($this) {
            self::CedulaCiudadania => 'Cédula de ciudadanía',
            self::NIT => 'NIT',
            self::CedulaExtranjeria => 'Cédula de extranjería',
            self::Pasaporte => 'Pasaporte',
            self::NUIP => 'NUIP',
        };
    }
}
