<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StoreClienteRequest;
use App\Models\Cliente;
use App\Models\Sede;
use Illuminate\Http\Request;

/**
 * Buscar/crear el cliente al que se le factura una venta — solo Caja/
 * administración (ver App\Enums\Rol::accedeACaja()), porque es parte del
 * mismo paso que cobrar, no de tomar el pedido (ver docs/DECISIONES.md).
 * `Cliente` es por empresa, no por sede (mismo criterio que `Proveedor`).
 */
class ClienteController extends Controller
{
    public function index(Request $request, Sede $sede): array
    {
        abort_unless($request->user()->accedeACajaEnSede($sede), 403);

        $termino = mb_strtolower(trim((string) $request->query('buscar', '')));

        $clientes = Cliente::query()
            ->where('empresa_id', $sede->empresa_id)
            ->where('estado', 'activo')
            ->when(
                $termino !== '',
                // "nombres || ' ' || apellidos" además de las columnas
                // sueltas: alguien que busca "Juan Pérez" (nombre completo)
                // no debe depender de que el término quepa entero en una
                // sola columna — bug real encontrado: con columnas
                // separadas, buscar "Cliente Del" (que abarca nombres="
                // Cliente" + apellidos="Del Cajero") no encontraba nada.
                // `||` es portable entre PostgreSQL y SQLite (el driver de
                // los tests) — CONCAT() no lo es.
                fn ($query) => $query->where(
                    fn ($q) => $q
                        ->whereRaw("LOWER(COALESCE(nombres, '') || ' ' || COALESCE(apellidos, '')) LIKE ?", ["%{$termino}%"])
                        ->orWhereRaw('LOWER(razon_social) LIKE ?', ["%{$termino}%"])
                        ->orWhereRaw('LOWER(nombre_comercial) LIKE ?', ["%{$termino}%"])
                        ->orWhereRaw('LOWER(numero_documento) LIKE ?', ["%{$termino}%"]),
                ),
            )
            ->orderBy('razon_social')
            ->orderBy('nombres')
            ->limit(20)
            ->get();

        return ['data' => $clientes->map(fn (Cliente $cliente) => $this->serializar($cliente))->values()];
    }

    public function store(StoreClienteRequest $request, Sede $sede): array
    {
        abort_unless($request->user()->accedeACajaEnSede($sede), 403);

        $cliente = Cliente::create([
            ...$request->validated(),
            'empresa_id' => $sede->empresa_id,
        ]);

        return ['data' => $this->serializar($cliente)];
    }

    private function serializar(Cliente $cliente): array
    {
        return [
            'id' => $cliente->id,
            'nombre' => $cliente->nombre_completo,
            'nombre_comercial' => $cliente->nombre_comercial,
            'tipo_documento' => $cliente->tipo_documento->value,
            'numero_documento' => $cliente->numero_documento,
        ];
    }
}
