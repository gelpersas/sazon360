<?php

namespace App\Models;

use App\Enums\TipoMovimientoCaja;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

#[Fillable(['caja_id', 'empresa_id', 'usuario_id', 'tipo', 'monto', 'descripcion'])]
class MovimientoCaja extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoCaja::class,
            'monto' => 'decimal:2',
        ];
    }

    /**
     * Defensa adicional además de la Policy/UI (ver .claude/rules/seguridad.md,
     * "no confiar únicamente en el frontend"): no se permiten movimientos en
     * una caja ya cerrada, con montos no positivos, ni en una sede a la que
     * el usuario autenticado no tenga acceso — `MovimientoCajaPolicy::create()`
     * no puede validar esto último porque Filament no le pasa la caja destino.
     */
    protected static function booted(): void
    {
        static::creating(function (MovimientoCaja $movimiento): void {
            $caja = $movimiento->caja ?? Caja::find($movimiento->caja_id);

            if ($caja && ! $caja->estaAbierta()) {
                throw new InvalidArgumentException('No se pueden registrar movimientos en una caja cerrada.');
            }

            if ($caja && Auth::check() && Auth::user()->rolEnSede($caja->sede) === null) {
                throw new AuthorizationException('No tienes acceso a la sede de esta caja.');
            }

            if ($movimiento->monto !== null && bccomp((string) $movimiento->monto, '0.00', 2) <= 0) {
                throw new InvalidArgumentException('El monto del movimiento debe ser mayor que cero.');
            }
        });
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
