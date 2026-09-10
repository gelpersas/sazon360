<?php

namespace App\Filament\Resources\Accesos\Pages;

use App\Filament\Resources\Accesos\AccesoResource;
use App\Models\Acceso;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListAccesos extends ListRecords
{
    protected static string $resource = AccesoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->crearAccesoAction(),
        ];
    }

    /**
     * El formulario junta varios roles en un campo `roles` (ver
     * AccesoForm) — cada uno se guarda como su propia fila `Acceso`, sin
     * cambiar el esquema (ver DEC-048). `using()` en vez del create()
     * default de Filament porque acá se crean N registros, no uno.
     */
    private function crearAccesoAction(): CreateAction
    {
        return CreateAction::make()
            ->using(function (array $data): Acceso {
                $empresaId = Filament::getTenant()->id;
                $sedeId = $data['sede_id'] ?? null;
                $primero = null;

                DB::transaction(function () use ($data, $empresaId, $sedeId, &$primero) {
                    foreach ($data['roles'] as $rolValor) {
                        $acceso = Acceso::create([
                            'user_id' => $data['user_id'],
                            'empresa_id' => $empresaId,
                            'sede_id' => $sedeId,
                            'rol' => $rolValor,
                        ]);

                        $primero ??= $acceso;
                    }
                });

                return $primero;
            });
    }
}
