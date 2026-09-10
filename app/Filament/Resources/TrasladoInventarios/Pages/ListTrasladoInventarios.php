<?php

namespace App\Filament\Resources\TrasladoInventarios\Pages;

use App\Filament\Resources\TrasladoInventarios\TrasladoInventarioResource;
use App\Models\Insumo;
use App\Models\Sede;
use App\Models\TrasladoInventario;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ListTrasladoInventarios extends ListRecords
{
    protected static string $resource = TrasladoInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->nuevoTrasladoAction(),
        ];
    }

    protected function nuevoTrasladoAction(): Action
    {
        return Action::make('nuevoTraslado')
            ->label('Nuevo traslado')
            // Gate general (¿puede intentar un traslado?) vía la Policy —
            // el chequeo específico de las dos sedes elegidas sigue abajo en
            // ->action(), porque la Policy no conoce esos valores concretos.
            ->visible(fn () => Auth::user()->can('create', TrasladoInventario::class))
            ->form([
                Select::make('insumo_id')
                    ->label('Insumo')
                    ->options(fn () => Insumo::query()->where('empresa_id', Filament::getTenant()?->id)->pluck('nombre', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('sede_origen_id')
                    ->label('Sede de origen')
                    ->options(fn () => $this->sedesAccesiblesDelUsuario())
                    ->required()
                    ->native(false),
                Select::make('sede_destino_id')
                    ->label('Sede de destino')
                    ->options(fn () => $this->sedesAccesiblesDelUsuario())
                    ->required()
                    ->native(false),
                TextInput::make('cantidad')
                    ->numeric()
                    ->minValue(0.001)
                    ->step(0.001)
                    ->required(),
                Textarea::make('notas')
                    ->maxLength(1000),
            ])
            ->action(function (array $data): void {
                $usuario = Auth::user();
                $empresa = Filament::getTenant();

                // Doble chequeo más allá de la Policy general (que solo
                // valida "tiene acceso a ≥2 sedes"): acceso real a las dos
                // sedes concretas elegidas — mismo criterio de
                // TrasladoInventarioPolicy::create(), verificado también
                // aquí porque el formulario no vuelve a pasar por la Policy
                // con los valores ya elegidos.
                $sedeOrigen = Sede::findOrFail($data['sede_origen_id']);
                $sedeDestino = Sede::findOrFail($data['sede_destino_id']);

                $tieneAcceso = fn (Sede $sede) => $usuario->esAdminCentralDe($empresa) || $usuario->sedesAccesibles($empresa)->contains('id', $sede->id);

                if (! $tieneAcceso($sedeOrigen) || ! $tieneAcceso($sedeDestino)) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo registrar el traslado')
                        ->body('No tienes acceso a una de las sedes elegidas.')
                        ->send();

                    throw new Halt;
                }

                try {
                    TrasladoInventario::realizar(
                        insumo: Insumo::findOrFail($data['insumo_id']),
                        origen: $sedeOrigen,
                        destino: $sedeDestino,
                        usuario: $usuario,
                        cantidad: (string) $data['cantidad'],
                        notas: $data['notas'] ?? null,
                    );
                } catch (InvalidArgumentException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo registrar el traslado')
                        ->body($e->getMessage())
                        ->send();

                    throw new Halt;
                }

                Notification::make()->success()->title('Traslado registrado')->send();
            });
    }

    private function sedesAccesiblesDelUsuario()
    {
        $empresa = Filament::getTenant();

        return $empresa ? Auth::user()->sedesAccesibles($empresa)->pluck('nombre', 'id') : [];
    }
}
