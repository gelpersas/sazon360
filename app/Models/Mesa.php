<?php

namespace App\Models;

use App\Enums\EstadoPedido;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

#[Fillable(['empresa_id', 'sede_id', 'nombre', 'piso', 'zona', 'capacidad', 'estado', 'grupo_mesa_id'])]
class Mesa extends Model
{
    use HasFactory;

    /**
     * Defensa en profundidad: aunque el formulario de Filament ya limita las
     * sedes seleccionables, no confiamos solo en eso (ver .claude/rules/seguridad.md)
     * — se re-valida aquí que el usuario autenticado tenga rol en la sede elegida.
     */
    protected static function booted(): void
    {
        static::saving(function (Mesa $mesa): void {
            if (! Auth::check()) {
                return;
            }

            $sede = $mesa->sede ?? Sede::find($mesa->sede_id);

            if ($sede && Auth::user()->rolEnSede($sede) === null) {
                throw new AuthorizationException('No tienes acceso a esa sede.');
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function grupoMesa(): BelongsTo
    {
        return $this->belongsTo(GrupoMesa::class);
    }

    /**
     * Cuenta mesas ocupadas dentro de las sedes indicadas — usado por el
     * dashboard del POS y el de Filament (ver docs/DECISIONES.md DEC-065).
     * Una mesa unida en Modo General (ver DEC-064) solo tiene el `Pedido`
     * directo sobre su mesa principal, así que sin expandir el grupo el
     * resto de sus mesas se verían "libres" aunque están físicamente en uso
     * por el mismo grupo de comensales.
     *
     * @param  Collection<int, int>  $sedeIds
     */
    public static function ocupadasEnSedes(Collection $sedeIds): int
    {
        $mesaIdsDirectas = Pedido::whereIn('sede_id', $sedeIds)
            ->where('estado', EstadoPedido::Abierto)
            ->whereNotNull('mesa_id')
            ->pluck('mesa_id');

        $grupoIds = static::whereIn('id', $mesaIdsDirectas)->whereNotNull('grupo_mesa_id')->pluck('grupo_mesa_id')->unique();

        $mesaIdsEnGrupo = $grupoIds->isNotEmpty()
            ? static::whereIn('grupo_mesa_id', $grupoIds)->pluck('id')
            : collect();

        return $mesaIdsDirectas->merge($mesaIdsEnGrupo)->unique()->count();
    }
}
