<?php

namespace App\Filament\Resources\Inventarios\Pages;

use App\Enums\TipoMovimientoInventario;
use App\Filament\Resources\Inventarios\InventarioResource;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\Sede;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListInventarios extends ListRecords
{
    protected static string $resource = InventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->registrarMovimientoAction(),
        ];
    }

    protected function registrarMovimientoAction(): Action
    {
        return Action::make('registrarMovimiento')
            ->label('Registrar movimiento')
            ->visible(fn () => Auth::user()->can('create', Inventario::class))
            ->form([
                Select::make('sede_id')
                    ->label('Sede')
                    ->options(function () {
                        $empresa = Filament::getTenant();

                        return $empresa ? Auth::user()->sedesAccesibles($empresa)->pluck('nombre', 'id') : [];
                    })
                    ->required()
                    ->native(false),
                Select::make('insumo_id')
                    ->label('Insumo')
                    ->relationship('insumo', 'nombre')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('tipo')
                    ->options(TipoMovimientoInventario::class)
                    ->required()
                    ->helperText('Entrada suma, salida resta, ajuste fija el stock al valor indicado (para corregir tras un conteo físico).'),
                TextInput::make('cantidad')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Textarea::make('motivo')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                // $data['tipo'] ya llega como instancia del enum, no como
                // string: options(EnumClass::class) hace que Filament
                // devuelva el caso del enum directamente, a diferencia de
                // un array de opciones armado a mano — ::from() no aplica.
                Inventario::registrarMovimiento(
                    sede: Sede::findOrFail($data['sede_id']),
                    insumo: Insumo::findOrFail($data['insumo_id']),
                    usuario: Auth::user(),
                    tipo: $data['tipo'],
                    cantidad: (string) $data['cantidad'],
                    motivo: $data['motivo'],
                );

                Notification::make()->success()->title('Movimiento registrado')->send();
            });
    }
}
