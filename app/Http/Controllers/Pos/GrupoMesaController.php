<?php

namespace App\Http\Controllers\Pos;

use App\Enums\EstadoGrupoMesa;
use App\Enums\ModoGrupoMesa;
use App\Http\Controllers\Controller;
use App\Models\GrupoMesa;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

/**
 * Sin Policy propia — mismo criterio que Pedido/Comanda/Pago (ver el
 * docblock de PedidoPolicy): la autorización real es `rolEnSede($sede)`,
 * repetida inline en cada método en vez de una clase de Policy casi
 * idéntica, para no multiplicar clases. Revisado explícitamente en la
 * auditoría de 2026-09-04/07 (ver AUDITORIA.md) — no es un descuido nuevo,
 * es el mismo patrón ya usado en toda la capa de controladores del POS.
 */
class GrupoMesaController extends Controller
{
    /**
     * Grupos activos de la sede — para que el POS sepa, al entrar o
     * recargar, si ya hay mesas unidas sin tener que recordarlo en el
     * cliente.
     */
    public function index(Request $request, Sede $sede): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($sede), 403);

        $grupos = GrupoMesa::where('sede_id', $sede->id)
            ->where('estado', EstadoGrupoMesa::Activo)
            ->get();

        return ['data' => $grupos->map(fn (GrupoMesa $grupo) => $this->serializar($grupo))->values()];
    }

    public function store(Request $request, Sede $sede): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($sede), 403);

        $data = $request->validate([
            'mesa_ids' => ['required', 'array', 'min:2'],
            'mesa_ids.*' => ['integer', 'distinct'],
            'modo' => ['sometimes', Rule::enum(ModoGrupoMesa::class)],
            // Mesa que debe quedar como principal — importa sobre todo en
            // modo General (ver GrupoMesa::unir()): si la unión arranca
            // desde una mesa con un pedido ya en curso, esa debe ser la
            // principal, no una elegida al azar entre las demás.
            'mesa_principal_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $mesas = Mesa::where('sede_id', $sede->id)->whereIn('id', $data['mesa_ids'])->get();

        if ($mesas->count() !== count($data['mesa_ids'])) {
            abort(422, 'Alguna mesa no pertenece a esta sede.');
        }

        $modo = isset($data['modo']) ? ModoGrupoMesa::from($data['modo']) : ModoGrupoMesa::Independiente;
        $mesaPrincipal = isset($data['mesa_principal_id']) ? $mesas->firstWhere('id', $data['mesa_principal_id']) : null;

        try {
            $grupo = GrupoMesa::unir($sede, $mesas, $request->user(), $modo, $mesaPrincipal);
        } catch (InvalidArgumentException|RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($grupo)];
    }

    public function show(Request $request, GrupoMesa $grupoMesa): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($grupoMesa->sede), 403);

        return ['data' => $this->serializar($grupoMesa)];
    }

    public function disolver(Request $request, GrupoMesa $grupoMesa): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($grupoMesa->sede), 403);

        try {
            $grupoMesa->disolver($request->user());
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($grupoMesa)];
    }

    public function agregarMesa(Request $request, GrupoMesa $grupoMesa): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($grupoMesa->sede), 403);

        $data = $request->validate([
            'mesa_id' => ['required', 'integer'],
        ]);

        $mesa = Mesa::where('sede_id', $grupoMesa->sede_id)->findOrFail($data['mesa_id']);

        try {
            $grupoMesa->agregarMesa($mesa, $request->user());
        } catch (InvalidArgumentException|RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($grupoMesa)];
    }

    private function serializar(GrupoMesa $grupo): array
    {
        $grupo->loadMissing('mesas', 'mesaPrincipal');

        return [
            'id' => $grupo->id,
            'estado' => $grupo->estado->value,
            'modo' => $grupo->modo->value,
            'mesa_principal' => $grupo->mesaPrincipal->only(['id', 'nombre']),
            'mesas' => $grupo->mesas->map(fn (Mesa $mesa) => ['id' => $mesa->id, 'nombre' => $mesa->nombre])->values(),
            'total_combinado' => $grupo->totalCombinado(),
            'pedidos' => $grupo->pedidosAbiertos()->map(fn (Pedido $pedido) => [
                'id' => $pedido->id,
                'mesa_id' => $pedido->mesa_id,
                'total' => $pedido->total(),
                'total_pagado' => $pedido->totalPagado(),
                'saldo_pendiente' => $pedido->saldoPendiente(),
            ])->values(),
        ];
    }
}
