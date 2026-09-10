<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Sede;
use Illuminate\Http\Request;

class ReferenciaController extends Controller
{
    public function sedes(Request $request): array
    {
        return [
            'data' => $request->user()->sedesOperativas()->map(fn (Sede $sede) => [
                'id' => $sede->id,
                'nombre' => $sede->nombre,
                'empresa_id' => $sede->empresa_id,
            ])->values(),
        ];
    }

    public function mesas(Request $request, Sede $sede): array
    {
        $this->autorizarSede($request, $sede);

        return [
            'data' => $sede->mesas()
                ->where('estado', 'activa')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'piso', 'zona', 'capacidad', 'grupo_mesa_id']),
        ];
    }

    public function areas(Request $request, Sede $sede): array
    {
        $this->autorizarSede($request, $sede);

        return [
            'data' => $sede->areasPreparacion()
                ->where('estado', 'activa')
                ->orderBy('orden')
                ->get(['id', 'nombre']),
        ];
    }

    public function catalogo(Request $request, Sede $sede): array
    {
        $this->autorizarSede($request, $sede);

        $categorias = $sede->empresa->categorias()
            ->where('estado', 'activa')
            ->orderBy('orden')
            ->with(['productos' => fn ($query) => $query->where('estado', 'activo')->orderBy('nombre')])
            ->get(['id', 'nombre', 'orden']);

        return ['data' => $categorias];
    }

    private function autorizarSede(Request $request, Sede $sede): void
    {
        abort_unless($request->user()->rolEnSede($sede) !== null, 403);
    }
}
