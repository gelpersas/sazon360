<?php

namespace App\Filament\Resources\Cajas\Pages;

use App\Filament\Resources\Cajas\CajaResource;
use App\Models\Caja;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ViewCaja extends ViewRecord
{
    protected static string $resource = CajaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->cerrarCajaAction(),
        ];
    }

    protected function cerrarCajaAction(): Action
    {
        return Action::make('cerrar')
            ->label('Cerrar caja')
            ->color('danger')
            ->visible(fn (Caja $record): bool => $record->estaAbierta() && Auth::user()->can('cerrar', $record))
            ->form([
                TextInput::make('monto_cierre_real')
                    ->label('Monto real contado')
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0)
                    ->required()
                    ->helperText(fn (Caja $record) => 'Monto esperado según los movimientos: $'.$record->montoEsperado()),
                Textarea::make('nota_cierre')
                    ->label('Nota de cierre')
                    ->maxLength(1000),
            ])
            ->action(function (Caja $record, array $data): void {
                try {
                    $record->cerrar(Auth::user(), (string) $data['monto_cierre_real'], $data['nota_cierre'] ?? null);
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title('No se pudo cerrar la caja')->body($e->getMessage())->send();

                    throw new Halt;
                }

                Notification::make()->success()->title('Caja cerrada')->send();
            });
    }
}
