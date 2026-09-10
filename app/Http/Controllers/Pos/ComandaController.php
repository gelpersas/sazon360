<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Comanda;
use App\Models\ItemPedido;
use App\Models\Sede;
use Illuminate\Http\Request;
use RuntimeException;

class ComandaController extends Controller
{
    /**
     * Pensado para que el KDS haga polling (ver docs/DECISIONES.md DEC-002 —
     * el MVP no usa WebSockets todavía).
     */
    public function index(Request $request, Sede $sede): array
    {
        abort_unless($request->user()->accedeACocinaEnSede($sede), 403);

        // Incluye 'lista' por defecto: el KDS necesita seguir mostrando una
        // comanda lista para poder entregarla (ver KdsView.vue) — quedaba
        // fuera del filtro por defecto desde la Fase 4, lo que hacía
        // inalcanzable el botón "Entregar" en la práctica.
        $estados = array_filter(explode(',', (string) $request->query('estado', 'pendiente,en_preparacion,lista')));
        $areaId = $request->query('area_preparacion_id');

        $comandas = Comanda::query()
            ->whereHas('pedido', fn ($query) => $query->where('sede_id', $sede->id))
            ->when($areaId, fn ($query) => $query->where('area_preparacion_id', $areaId))
            ->whereIn('estado', $estados)
            ->with(['areaPreparacion', 'pedido.mesa', 'items'])
            ->oldest()
            ->get();

        return [
            'data' => $comandas->map(fn (Comanda $comanda) => [
                'id' => $comanda->id,
                'estado' => $comanda->estado->value,
                'area' => $comanda->areaPreparacion->nombre,
                'pedido_id' => $comanda->pedido_id,
                'mesa' => $comanda->pedido->mesa?->nombre,
                'tipo_pedido' => $comanda->pedido->tipo->value,
                'created_at' => $comanda->created_at->toIso8601String(),
                'items' => $comanda->items->map(fn (ItemPedido $item) => [
                    'nombre_producto' => $item->nombre_producto,
                    'cantidad' => $item->cantidad,
                    'notas' => $item->notas,
                ])->values(),
            ])->values(),
        ];
    }

    public function avanzar(Request $request, Comanda $comanda): array
    {
        abort_unless($request->user()->accedeACocinaEnSede($comanda->pedido->sede), 403);

        try {
            $comanda->avanzar();
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return [
            'data' => [
                'id' => $comanda->id,
                'estado' => $comanda->fresh()->estado->value,
            ],
        ];
    }
}
