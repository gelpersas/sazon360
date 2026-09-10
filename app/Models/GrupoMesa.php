<?php

namespace App\Models;

use App\Enums\EstadoGrupoMesa;
use App\Enums\EstadoPedido;
use App\Enums\ModoGrupoMesa;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Agrupa mesas físicas que un mesero decide atender juntas (mismo grupo de
 * comensales en mesas contiguas). Dos modos (`modo`, ver
 * App\Enums\ModoGrupoMesa y docs/DECISIONES.md):
 *
 * - Independiente (default, diseño original): NO fusiona pedidos ni
 *   comandas — cada mesa conserva su propio `Pedido` intacto (cocina,
 *   inventario, factura sin cambios). El grupo es solo una capa operativa de
 *   agrupación/visualización por encima.
 * - General: el grupo comparte UN solo `Pedido`, siempre abierto contra
 *   `mesaPrincipal` — ver `Pedido::abrir()`, que redirige ahí cualquier
 *   intento de abrir pedido en una mesa no-principal de un grupo general.
 *   Una sola comanda por área, una sola factura para todo el grupo.
 *
 * El modo se fija al unir las mesas y no cambia después — mezclar pedidos ya
 * en curso (comandas enviadas, pagos parciales) sería un problema de
 * integridad de datos que queda fuera de alcance a propósito.
 *
 * "Dividir la cuenta por mesa original" en modo Independiente queda resuelto
 * por diseño: como cada mesa mantiene su pedido separado, pagar por mesa
 * original es simplemente cobrar cada pedido del grupo por separado (ya
 * soportado por `Pago::registrar()`, sin código nuevo). En modo General, la
 * división por persona/producto dentro del pedido compartido la cubre
 * `SubCuenta` (Fase 10) igual que en cualquier pedido normal — de hecho con
 * más alcance, ya que puede repartir ítems sin que importe de qué mesa
 * física "vinieron". "Partes iguales"/"por persona" del total combinado se
 * siguen calculando como referencia (`totalCombinado()` dividido entre N),
 * no como un monto que se cobre como tal.
 */
#[Fillable(['empresa_id', 'sede_id', 'mesa_principal_id', 'estado', 'modo', 'creado_por_id', 'disuelto_por_id', 'disuelto_en'])]
class GrupoMesa extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estado' => EstadoGrupoMesa::class,
            'modo' => ModoGrupoMesa::class,
            'disuelto_en' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function mesaPrincipal(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'mesa_principal_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function disueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disuelto_por_id');
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class);
    }

    /**
     * @param  Collection<int, Mesa>  $mesas
     * @param  Mesa|null  $mesaPrincipal  Cuál de $mesas fija como principal
     *                                    — importa sobre todo en modo General, donde TODO pedido del grupo
     *                                    se abre contra ella (ver Pedido::abrir()). Sin indicarla, se usa la
     *                                    primera del resultado de la consulta (orden no garantizado por
     *                                    `id` de entrada — mismo comportamiento que antes de esta opción).
     *                                    Típicamente se pasa cuando la unión arranca desde una mesa que YA
     *                                    tenía un pedido en curso: esa debe seguir siendo la que lo reciba,
     *                                    no una elegida al azar entre las demás.
     */
    public static function unir(Sede $sede, Collection $mesas, User $usuario, ModoGrupoMesa $modo = ModoGrupoMesa::Independiente, ?Mesa $mesaPrincipal = null): self
    {
        if ($mesas->count() < 2) {
            throw new InvalidArgumentException('Unir mesas requiere al menos dos mesas.');
        }

        if ($mesas->contains(fn (Mesa $mesa) => $mesa->sede_id !== $sede->id)) {
            throw new InvalidArgumentException('Todas las mesas deben pertenecer a la sede indicada.');
        }

        return DB::transaction(function () use ($sede, $mesas, $usuario, $modo, $mesaPrincipal) {
            $mesasBloqueadas = Mesa::query()
                ->whereIn('id', $mesas->pluck('id'))
                ->lockForUpdate()
                ->get();

            $yaEnGrupo = $mesasBloqueadas->whereNotNull('grupo_mesa_id');

            if ($yaEnGrupo->isNotEmpty()) {
                throw new RuntimeException('Ya están unidas a otro grupo: '.$yaEnGrupo->pluck('nombre')->implode(', '));
            }

            $principal = $mesaPrincipal ? $mesasBloqueadas->firstWhere('id', $mesaPrincipal->id) : $mesasBloqueadas->first();

            if ($mesaPrincipal && ! $principal) {
                throw new InvalidArgumentException('La mesa principal debe estar entre las mesas que se van a unir.');
            }

            $grupo = static::create([
                'empresa_id' => $sede->empresa_id,
                'sede_id' => $sede->id,
                'mesa_principal_id' => $principal->id,
                'estado' => EstadoGrupoMesa::Activo,
                'modo' => $modo,
                'creado_por_id' => $usuario->id,
            ]);

            Mesa::whereIn('id', $mesasBloqueadas->pluck('id'))->update(['grupo_mesa_id' => $grupo->id]);

            return $grupo;
        });
    }

    /**
     * Suma una mesa más a un grupo ya activo — antes solo se podía unir
     * mesas de a dos o más al crear el grupo; si el grupo de comensales
     * crecía después (una mesa vecina se suma a media atención), no había
     * forma de agregarla sin disolver y volver a crear el grupo entero
     * (hallazgo del usuario, ver docs/DECISIONES.md).
     */
    public function agregarMesa(Mesa $mesa, User $usuario): void
    {
        DB::transaction(function () use ($mesa) {
            $grupo = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($grupo->estado !== EstadoGrupoMesa::Activo) {
                throw new RuntimeException('Este grupo ya está disuelto.');
            }

            $mesaBloqueada = Mesa::query()->lockForUpdate()->findOrFail($mesa->id);

            if ($mesaBloqueada->sede_id !== $grupo->sede_id) {
                throw new InvalidArgumentException('Esa mesa no pertenece a la misma sede del grupo.');
            }

            if ($mesaBloqueada->grupo_mesa_id !== null) {
                throw new RuntimeException("\"{$mesaBloqueada->nombre}\" ya está unida a otro grupo.");
            }

            $mesaBloqueada->update(['grupo_mesa_id' => $grupo->id]);
        });

        $this->refresh();
    }

    /**
     * Desune las mesas — no toca los pedidos existentes de ninguna de
     * ellas, cada una sigue su curso normal de forma independiente.
     */
    public function disolver(User $usuario): void
    {
        DB::transaction(function () use ($usuario) {
            $grupo = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($grupo->estado !== EstadoGrupoMesa::Activo) {
                throw new RuntimeException('Este grupo ya está disuelto.');
            }

            Mesa::where('grupo_mesa_id', $grupo->id)->update(['grupo_mesa_id' => null]);

            $grupo->update([
                'estado' => EstadoGrupoMesa::Disuelto,
                'disuelto_por_id' => $usuario->id,
                'disuelto_en' => now(),
            ]);
        });

        $this->refresh();
    }

    /**
     * @return Collection<int, Pedido>
     */
    public function pedidosAbiertos(): Collection
    {
        return Pedido::whereIn('mesa_id', $this->mesas()->pluck('id'))
            ->where('estado', EstadoPedido::Abierto)
            ->get();
    }

    /**
     * Suma de los pedidos abiertos de todas las mesas del grupo — de
     * referencia (para mostrar "cuánto le toca a cada quien" en partes
     * iguales/por persona), no un monto que se cobre como tal: el cobro
     * real sigue registrándose pedido por pedido.
     */
    public function totalCombinado(): string
    {
        return $this->pedidosAbiertos()->reduce(
            fn (string $acumulado, Pedido $pedido) => bcadd($acumulado, $pedido->total(), 2),
            '0.00',
        );
    }
}
