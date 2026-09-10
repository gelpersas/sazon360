<?php

namespace App\Enums;

enum Rol: string
{
    case AdministracionCentral = 'administracion_central';
    case AdministracionSede = 'administracion_sede';
    case Caja = 'caja';
    case Mesero = 'mesero';
    case AreaPreparacion = 'area_preparacion';

    public function label(): string
    {
        return match ($this) {
            self::AdministracionCentral => 'Administración central',
            self::AdministracionSede => 'Administración de sede',
            self::Caja => 'Caja',
            self::Mesero => 'Mesero',
            self::AreaPreparacion => 'Área de preparación',
        };
    }

    /**
     * Roles que requieren una sede asignada (todos salvo administración central).
     */
    public function requiereSede(): bool
    {
        return $this !== self::AdministracionCentral;
    }

    /**
     * Grupos de acceso dentro del POS táctil — cada operativo ve y puede
     * usar solo su propia área, salvo administración (que siempre tiene
     * acceso a todo). Confirmado con el usuario: Mesero no cobra ni toca
     * caja, Área de preparación solo ve Cocina/KDS, Mesero no ve Cocina/KDS
     * — ver docs/DECISIONES.md.
     */
    public function accedeAMostrador(): bool
    {
        return in_array($this, [self::Mesero, self::Caja, self::AdministracionSede, self::AdministracionCentral], true);
    }

    public function accedeACocina(): bool
    {
        return in_array($this, [self::AreaPreparacion, self::AdministracionSede, self::AdministracionCentral], true);
    }

    public function accedeACaja(): bool
    {
        return in_array($this, [self::Caja, self::AdministracionSede, self::AdministracionCentral], true);
    }
}
