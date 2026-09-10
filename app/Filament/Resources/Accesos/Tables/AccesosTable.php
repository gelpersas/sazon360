<?php

namespace App\Filament\Resources\Accesos\Tables;

use App\Enums\Rol;
use App\Filament\Support\BorradoSeguro;
use App\Models\Acceso;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class AccesosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('rol')
                    ->formatStateUsing(fn (Rol $state) => $state->label())
                    ->badge()
                    // Cada fila sigue siendo un solo rol en base de datos
                    // (ver DEC-048), pero si esta persona tiene más de uno en
                    // la misma sede, se lista aquí para que no parezca que
                    // "solo tiene este" al mirar la tabla.
                    ->description(function (Acceso $record): ?string {
                        $otros = Acceso::where('user_id', $record->user_id)
                            ->where('sede_id', $record->sede_id)
                            ->whereKeyNot($record->getKey())
                            ->get()
                            ->map(fn (Acceso $acceso) => $acceso->rol->label());

                        return $otros->isEmpty() ? null : 'también: '.$otros->implode(', ');
                    }),
                TextColumn::make('sede.nombre')
                    ->label('Sede')
                    ->placeholder('Todas (administración central)'),
            ])
            ->filters([
                SelectFilter::make('rol')
                    ->options(collect(Rol::cases())->mapWithKeys(fn (Rol $rol) => [$rol->value => $rol->label()])),
            ])
            ->recordActions([
                self::editarAccesoAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(fn ($records) => BorradoSeguro::variosRegistros($records, 'accesos')),
                ]),
            ]);
    }

    /**
     * Edita TODOS los roles de este usuario en esta sede en un solo modal,
     * no solo el rol de la fila en la que se hizo clic — ver el docblock de
     * AccesoForm y docs/DECISIONES.md DEC-048.
     */
    private static function editarAccesoAction(): EditAction
    {
        return EditAction::make()
            ->mutateRecordDataUsing(function (Acceso $record, array $data): array {
                $data['roles'] = Acceso::where('user_id', $record->user_id)
                    ->where('empresa_id', $record->empresa_id)
                    ->where('sede_id', $record->sede_id)
                    ->pluck('rol')
                    ->map(fn (Rol $rol) => $rol->value)
                    ->all();

                $data['user_name'] = $record->user->name;
                $data['user_email'] = $record->user->email;
                $data['user_telefono'] = $record->user->telefono;
                $data['user_documento_identidad'] = $record->user->documento_identidad;

                return $data;
            })
            ->using(function (Acceso $record, array $data): Acceso {
                $sedeId = $data['sede_id'] ?? null;
                $nuevo = null;

                DB::transaction(function () use ($record, $data, $sedeId, &$nuevo) {
                    // Perfil de quien ya tenía este acceso — independiente
                    // de si el campo "Usuario" de arriba lo reasigna a otra
                    // persona (ver docblock de AccesoForm).
                    $record->user->update([
                        'name' => $data['user_name'],
                        'email' => $data['user_email'],
                        'telefono' => $data['user_telefono'] ?? null,
                        'documento_identidad' => $data['user_documento_identidad'] ?? null,
                    ]);

                    // Se reemplaza el grupo completo (todas las filas de
                    // este usuario en esta sede) por los roles marcados en
                    // el formulario — más simple y seguro que intentar
                    // adivinar cuál fila reutilizar para cuál rol.
                    Acceso::where('user_id', $record->user_id)
                        ->where('empresa_id', $record->empresa_id)
                        ->where('sede_id', $record->sede_id)
                        ->delete();

                    foreach ($data['roles'] as $rolValor) {
                        $acceso = Acceso::create([
                            'user_id' => $data['user_id'] ?? $record->user_id,
                            'empresa_id' => $record->empresa_id,
                            'sede_id' => $sedeId,
                            'rol' => $rolValor,
                        ]);

                        $nuevo ??= $acceso;
                    }
                });

                return $nuevo;
            });
    }
}
