<?php

namespace App\Filament\Resources\Cajas\Pages;

use App\Filament\Resources\Cajas\CajaResource;
use App\Models\Caja;
use App\Models\Sede;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ListCajas extends ListRecords
{
    protected static string $resource = CajaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->abrirCajaAction(),
        ];
    }

    /**
     * "Abrir caja" como modal (ver docs/DECISIONES.md DEC-041) — antes era
     * la página CreateCaja, con este mismo cuerpo en handleRecordCreation().
     * Pasa por Caja::abrir() en vez de un create() genérico: ahí vive el
     * chequeo de "ya hay una abierta en esta sede", no es un simple
     * guardado de formulario.
     */
    protected function abrirCajaAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Abrir caja')
            ->using(function (array $data): Caja {
                try {
                    return Caja::abrir(
                        sede: Sede::findOrFail($data['sede_id']),
                        usuario: Auth::user(),
                        montoInicial: (string) $data['monto_inicial'],
                        nota: $data['nota_apertura'] ?? null,
                    );
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo abrir la caja')
                        ->body($e->getMessage())
                        ->send();

                    throw new Halt;
                }
            });
    }
}
