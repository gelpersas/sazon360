<?php

namespace App\Http\Controllers\Pos;

use App\Enums\EstadoCaja;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\AbrirCajaRequest;
use App\Http\Requests\Pos\CerrarCajaRequest;
use App\Http\Requests\Pos\StoreMovimientoCajaRequest;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Sede;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class CajaController extends Controller
{
    /**
     * La caja abierta actual de la sede (o null si no hay ninguna). Solo
     * Caja/administración pueden manejar la caja del POS — mesero y área de
     * preparación no ven este dominio (ver App\Enums\Rol::accedeACaja()).
     */
    public function actual(Request $request, Sede $sede): array
    {
        abort_unless($request->user()->accedeACajaEnSede($sede), 403);

        $caja = Caja::where('sede_id', $sede->id)->where('estado', EstadoCaja::Abierta)->latest('abierta_at')->first();

        return ['data' => $caja ? $this->serializar($caja) : null];
    }

    public function abrir(AbrirCajaRequest $request, Sede $sede): array
    {
        abort_unless($request->user()->accedeACajaEnSede($sede), 403);

        try {
            $caja = Caja::abrir(
                $sede,
                $request->user(),
                (string) $request->validated('monto_inicial'),
                $request->validated('nota'),
            );
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($caja)];
    }

    public function cerrar(CerrarCajaRequest $request, Caja $caja): array
    {
        abort_unless($request->user()->accedeACajaEnSede($caja->sede), 403);

        try {
            $caja->cerrar($request->user(), (string) $request->validated('monto_real'), $request->validated('nota'));
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($caja->fresh())];
    }

    public function registrarMovimiento(StoreMovimientoCajaRequest $request, Caja $caja): array
    {
        abort_unless($request->user()->accedeACajaEnSede($caja->sede), 403);

        try {
            MovimientoCaja::create([
                'caja_id' => $caja->id,
                'empresa_id' => $caja->empresa_id,
                'usuario_id' => $request->user()->id,
                'tipo' => $request->validated('tipo'),
                'monto' => $request->validated('monto'),
                'descripcion' => $request->validated('descripcion'),
            ]);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($caja->fresh())];
    }

    private function serializar(Caja $caja): array
    {
        $caja->loadMissing('movimientos');

        return [
            'id' => $caja->id,
            'sede_id' => $caja->sede_id,
            'estado' => $caja->estado->value,
            'monto_inicial' => $caja->monto_inicial,
            'monto_esperado' => $caja->estaAbierta() ? $caja->montoEsperado() : $caja->monto_cierre_esperado,
            'monto_cierre_real' => $caja->monto_cierre_real,
            'diferencia' => $caja->diferencia,
            'nota_apertura' => $caja->nota_apertura,
            'nota_cierre' => $caja->nota_cierre,
            'abierta_at' => $caja->abierta_at?->toIso8601String(),
            'cerrada_at' => $caja->cerrada_at?->toIso8601String(),
            'movimientos' => $caja->movimientos->sortByDesc('id')->values()->map(fn (MovimientoCaja $m) => [
                'id' => $m->id,
                'tipo' => $m->tipo->value,
                'monto' => (string) $m->monto,
                'descripcion' => $m->descripcion,
            ]),
        ];
    }
}
