<?php

namespace App\Filament\Support;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Envuelve el borrado de registros con FKs `restrictOnDelete()` (ver
 * .claude/rules/base-datos.md) para mostrar un aviso legible en vez de
 * dejar pasar la QueryException cruda hasta una pantalla de error.
 */
class BorradoSeguro
{
    /**
     * @param  Collection<int, Model>  $records
     */
    public static function variosRegistros(Collection $records, string $entidadPlural): void
    {
        $fallidos = 0;

        foreach ($records as $record) {
            try {
                $record->delete();
            } catch (QueryException $e) {
                if (! static::esViolacionDeLlaveForanea($e)) {
                    throw $e;
                }

                $fallidos++;
            }
        }

        if ($fallidos > 0) {
            Notification::make()
                ->danger()
                ->title("No se pudieron eliminar {$fallidos} {$entidadPlural}")
                ->body('Tienen registros asociados (por ejemplo, productos o mesas). Elimina o mueve primero esos registros.')
                ->send();
        }
    }

    /**
     * Solo se usa alrededor de delete(), que no puede fallar por otra causa
     * de la clase SQLSTATE 23xxx (integrity constraint violation) más que
     * por una FK — por eso basta con el prefijo, sin fijarse en el driver.
     * Postgres usa 23503 específico; SQLite (tests) solo reporta 23000.
     */
    private static function esViolacionDeLlaveForanea(QueryException $e): bool
    {
        return str_starts_with((string) $e->getCode(), '23');
    }
}
