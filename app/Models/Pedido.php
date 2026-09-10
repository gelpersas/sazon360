<?php

namespace App\Models;

use App\Enums\EstadoComanda;
use App\Enums\EstadoPedido;
use App\Enums\ModoGrupoMesa;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TipoPedido;
use App\Events\ComandaActualizada;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

#[Fillable(['empresa_id', 'sede_id', 'mesa_id', 'cliente_id', 'usuario_id', 'tipo', 'estado', 'notas', 'idempotency_key', 'cerrado_at'])]
class Pedido extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoPedido::class,
            'estado' => EstadoPedido::class,
            'cerrado_at' => 'datetime',
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

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemPedido::class);
    }

    public function comandas(): HasMany
    {
        return $this->hasMany(Comanda::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function subCuentas(): HasMany
    {
        return $this->hasMany(SubCuenta::class);
    }

    /**
     * Idempotente por `idempotencyKey`: un reintento por corte de red con la
     * misma clave devuelve el pedido ya creado en vez de duplicarlo.
     */
    public static function abrir(Sede $sede, User $usuario, TipoPedido $tipo, ?Mesa $mesa, string $idempotencyKey, ?string $notas = null): self
    {
        $existente = static::where('idempotency_key', $idempotencyKey)->first();

        if ($existente) {
            return $existente;
        }

        if ($tipo->requiereMesa() && ! $mesa) {
            throw new InvalidArgumentException('Un pedido de tipo mesa requiere una mesa.');
        }

        // Grupo en modo General (ver docs/DECISIONES.md y App\Enums\ModoGrupoMesa):
        // un solo pedido compartido por todo el grupo, siempre contra la mesa
        // principal — sin importar desde qué mesa del grupo se dispare esta
        // acción. Se reutiliza en vez de crear uno nuevo si ya existe: el
        // `idempotencyKey` por sí solo no lo evita, cada intento trae una
        // clave distinta (a diferencia del reintento por corte de red, que sí
        // repite la misma clave y ya está cubierto arriba).
        if ($mesa?->grupoMesa?->modo === ModoGrupoMesa::General) {
            $mesa = $mesa->grupoMesa->mesaPrincipal;

            $pedidoDelGrupo = static::where('mesa_id', $mesa->id)->where('estado', EstadoPedido::Abierto)->first();

            if ($pedidoDelGrupo) {
                return $pedidoDelGrupo;
            }
        }

        try {
            return static::create([
                'empresa_id' => $sede->empresa_id,
                'sede_id' => $sede->id,
                'mesa_id' => $mesa?->id,
                'usuario_id' => $usuario->id,
                'tipo' => $tipo,
                'estado' => EstadoPedido::Abierto,
                'notas' => $notas,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (QueryException $e) {
            $pedido = static::where('idempotency_key', $idempotencyKey)->first();

            if ($pedido) {
                return $pedido;
            }

            throw $e;
        }
    }

    /**
     * Suma de los ítems no anulados (excluye los de comandas anuladas). Se
     * recalcula siempre desde los ítems, no se guarda un total acumulado.
     */
    public function total(): string
    {
        return $this->items()
            ->whereDoesntHave('comanda', fn ($query) => $query->where('estado', EstadoComanda::Anulada))
            ->get()
            ->reduce(fn (string $acumulado, ItemPedido $item) => bcadd($acumulado, $item->subtotal(), 2), '0.00');
    }

    public function totalPagado(): string
    {
        // sum() no pasa por el cast decimal:2 del modelo (es un agregado de
        // consulta, no un atributo) — normalizar con bcadd() para no
        // devolver "10" en vez de "10.00" según el driver (SQLite en tests
        // no formatea igual que PostgreSQL).
        return bcadd((string) $this->pagos()->sum('monto'), '0', 2);
    }

    public function saldoPendiente(): string
    {
        return bcsub($this->total(), $this->totalPagado(), 2);
    }

    /**
     * Agrupa los ítems todavía sin enviar (comanda_id nulo) por área de
     * preparación y crea una Comanda por cada grupo. Reintentar esta acción
     * (ej. tras perder la respuesta por un corte de red) es seguro: en el
     * segundo intento ya no quedan ítems pendientes, así que no se duplica
     * ninguna comanda — ver docs/DECISIONES.md DEC-010 y el riesgo de
     * idempotencia de docs/ROADMAP.md Fase 4.
     *
     * @return Collection<int, Comanda>
     */
    public function enviarComanda(): Collection
    {
        $comandas = DB::transaction(function () {
            $itemsPendientes = $this->items()->whereNull('comanda_id')->lockForUpdate()->get();

            $comandas = collect();

            foreach ($itemsPendientes->groupBy('area_preparacion_id') as $areaId => $items) {
                $comanda = Comanda::create([
                    'empresa_id' => $this->empresa_id,
                    'pedido_id' => $this->id,
                    'area_preparacion_id' => $areaId,
                    'estado' => EstadoComanda::Pendiente,
                ]);

                ItemPedido::whereIn('id', $items->pluck('id'))->update(['comanda_id' => $comanda->id]);

                $comandas->push($comanda);
            }

            return $comandas;
        });

        // Fuera de la transacción a propósito: notificar al KDS de una
        // comanda que terminó no existiendo (si la transacción hubiera
        // fallado) sería peor que el pequeño retraso de notificar justo
        // después de confirmarla — ver docs/DECISIONES.md (Fase 8).
        // dispatchSeguro() (no dispatch()): un fallo de broadcast (Reverb
        // caído) nunca debe impedir que la comanda ya creada se confirme al
        // mesero — ver el docblock de ComandaActualizada::dispatchSeguro().
        $comandas->each(fn (Comanda $comanda) => ComandaActualizada::dispatchSeguro($comanda));

        return $comandas;
    }

    /**
     * Anula el pedido y, en cascada, cualquier comanda suya que todavía
     * estuviera pendiente/en preparación/lista — si no se hiciera, cocina
     * seguiría viendo y preparando una venta que ya no existe (hallazgo de
     * auditoría corregido, ver docs/DECISIONES.md). No se puede anular si
     * alguna comanda ya llegó a "entregada": la comida ya salió, así que el
     * pedido no puede simplemente dejar de existir (dejaría ítems servidos
     * fuera del total sin que nadie los cobre) — ese caso queda fuera de
     * alcance, pendiente del flujo de devolución post-entrega (DEC-042).
     */
    public function anular(): void
    {
        $comandasAnuladas = DB::transaction(function () {
            $pedido = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($pedido->estado !== EstadoPedido::Abierto) {
                throw new RuntimeException('Solo se puede anular un pedido abierto.');
            }

            $comandas = $pedido->comandas()->lockForUpdate()->get();

            if ($comandas->contains(fn (Comanda $comanda) => $comanda->estado === EstadoComanda::Entregada)) {
                throw new RuntimeException('No se puede anular un pedido con ítems ya entregados al cliente.');
            }

            $pedido->update([
                'estado' => EstadoPedido::Anulado,
                'cerrado_at' => now(),
            ]);

            $comandasAnuladas = $comandas->filter(
                fn (Comanda $comanda) => in_array($comanda->estado, [EstadoComanda::Pendiente, EstadoComanda::EnPreparacion, EstadoComanda::Lista], true)
            );

            $comandasAnuladas->each(fn (Comanda $comanda) => $comanda->anular());

            return $comandasAnuladas;
        });

        // Fuera de la transacción a propósito, mismo criterio que
        // enviarComanda(): notificar al KDS de las comandas anuladas después
        // de confirmar el cambio — dispatchSeguro() evita que un fallo de
        // broadcast (Reverb caído) impida que la anulación ya guardada se
        // confirme.
        $comandasAnuladas->each(fn (Comanda $comanda) => ComandaActualizada::dispatchSeguro($comanda));

        $this->refresh();
    }

    public function estaAbierto(): bool
    {
        return $this->estado === EstadoPedido::Abierto;
    }

    /**
     * Asignación opcional "al cobrar" (ver docs/DECISIONES.md) — sin esto,
     * la factura electrónica se emite al "consumidor final" genérico (ver
     * config/facturacion.php). Pasar null vuelve a dejarlo como consumidor
     * final. Solo mientras el pedido sigue abierto, mismo criterio que
     * actualizar un ítem — una vez cobrado, la factura ya se disparó con
     * el cliente que estuviera asignado en ese momento.
     */
    public function asignarCliente(?Cliente $cliente): void
    {
        if ($this->estado !== EstadoPedido::Abierto) {
            throw new RuntimeException('Solo se puede asignar el cliente de un pedido abierto.');
        }

        $this->update(['cliente_id' => $cliente?->id]);
    }

    /**
     * Descuenta de `Inventario` los insumos de la receta de cada producto
     * vendido (mismo conjunto de ítems que cuenta para total(): excluye los
     * de comandas anuladas). Se llama una sola vez, desde dentro de la
     * transacción de Pago::registrar() al quedar el pedido `cobrado` — ver
     * docs/DECISIONES.md DEC-014. decrement() es una resta atómica a nivel
     * de fila en SQL, segura ante ventas concurrentes sin necesitar
     * lockForUpdate() aquí.
     */
    public function descontarInventario(User $usuario): void
    {
        $items = $this->items()
            ->whereDoesntHave('comanda', fn ($query) => $query->where('estado', EstadoComanda::Anulada))
            ->with('producto.recetaItems')
            ->get();

        foreach ($items as $item) {
            foreach ($item->producto->recetaItems as $recetaItem) {
                $cantidadADescontar = bcmul((string) $recetaItem->cantidad, (string) $item->cantidad, 3);

                // Ver nota de DEC-015/017 sobre el riesgo de condición de
                // carrera en firstOrCreate() si dos ventas del mismo insumo
                // en la misma sede coinciden justo en su primera vez.
                $inventario = Inventario::firstOrCreate(
                    ['sede_id' => $this->sede_id, 'insumo_id' => $recetaItem->insumo_id],
                    ['empresa_id' => $this->empresa_id, 'cantidad_actual' => 0],
                );

                $inventario->decrement('cantidad_actual', (float) $cantidadADescontar);

                MovimientoInventario::create([
                    'empresa_id' => $this->empresa_id,
                    'sede_id' => $this->sede_id,
                    'insumo_id' => $recetaItem->insumo_id,
                    'usuario_id' => $usuario->id,
                    'pedido_id' => $this->id,
                    'tipo' => TipoMovimientoInventario::Salida,
                    'cantidad' => $cantidadADescontar,
                    'motivo' => "Venta pedido #{$this->id}",
                ]);
            }
        }
    }
}
