<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Reemplaza el Dashboard base de Filament (ver docs/DECISIONES.md DEC-065)
 * para agregar el filtro de rango/sede — el resto de la página (grilla de
 * widgets) es 100% comportamiento nativo heredado, ver
 * vendor/filament/filament/src/Pages/Dashboard.php::content(), que ya
 * embebe `filtersForm` automáticamente cuando la clase usa `HasFiltersForm`.
 */
class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    /**
     * Selector de "Sede" en null = vista consolidada de todas las sedes
     * accesibles (ver App\Filament\Widgets\ComparativoSedesWidget). Con una
     * sola sede accesible (típico de administración de sede) el selector
     * queda oculto: no hay nada que "consolidar" con una sola sede.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('sede_id')
                ->label('Sede')
                ->options(function () {
                    $empresa = Filament::getTenant();

                    if (! $empresa) {
                        return [];
                    }

                    return Auth::user()->sedesAccesibles($empresa)->pluck('nombre', 'id');
                })
                ->visible(function () {
                    $empresa = Filament::getTenant();

                    return $empresa && Auth::user()->sedesAccesibles($empresa)->count() > 1;
                })
                ->placeholder('Todas (consolidado)')
                ->native(false),
            Select::make('rango')
                ->label('Rango')
                ->options([
                    'hoy' => 'Hoy',
                    'semana' => 'Semana',
                    'mes' => 'Mes',
                ])
                ->default('hoy')
                ->selectablePlaceholder(false)
                ->native(false),
        ]);
    }
}
