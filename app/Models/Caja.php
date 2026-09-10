<?php

namespace App\Models;

use App\Enums\EstadoCaja;
use App\Enums\TipoMovimientoCaja;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Fillable(['empresa_id', 'sede_id', 'usuario_apertura_id', 'usuario_cierre_id', 'monto_inicial', 'monto_cierre_esperado', 'monto_cierre_real', 'diferencia', 'estado', 'nota_apertura', 'nota_cierre', 'abierta_at', 'cerrada_at'])]
class Caja extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estado' => EstadoCaja::class,
            'monto_inicial' => 'decimal:2',
            'monto_cierre_esperado' => 'decimal:2',
            'monto_cierre_real' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'abierta_at' => 'datetime',
            'cerrada_at' => 'datetime',
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

    public function usuarioApertura(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_apertura_id');
    }

    public function usuarioCierre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cierre_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    /**
     * Abre una caja para la sede indicada. Rechaza la operación (mensaje
     * legible, no una QueryException cruda) si ya hay una abierta — el
     * índice único parcial `cajas_una_abierta_por_sede` es la garantía real
     * contra la condición de carrera; este chequeo previo solo mejora el
     * mensaje de error en el caso normal (no concurrente).
     */
    public static function abrir(Sede $sede, User $usuario, string $montoInicial, ?string $nota = null): self
    {
        try {
            return DB::transaction(function () use ($sede, $usuario, $montoInicial, $nota) {
                $yaAbierta = static::query()
                    ->where('sede_id', $sede->id)
                    ->where('estado', EstadoCaja::Abierta)
                    ->lockForUpdate()
                    ->exists();

                if ($yaAbierta) {
                    throw new RuntimeException('Ya hay una caja abierta en esta sede.');
                }

                return static::create([
                    'empresa_id' => $sede->empresa_id,
                    'sede_id' => $sede->id,
                    'usuario_apertura_id' => $usuario->id,
                    'monto_inicial' => $montoInicial,
                    'estado' => EstadoCaja::Abierta,
                    'nota_apertura' => $nota,
                    'abierta_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            throw new RuntimeException('Ya hay una caja abierta en esta sede.', previous: $e);
        }
    }

    /**
     * Cierra la caja: recalcula el monto esperado a partir de los
     * movimientos reales (no de un contador acumulado) y registra la
     * diferencia con lo contado físicamente. Bloquea la fila (`lockForUpdate`)
     * dentro de una transacción para que un doble cierre concurrente no
     * pueda procesarse dos veces (ver docs/ROADMAP.md, riesgo de Fase 3).
     */
    public function cerrar(User $usuario, string $montoReal, ?string $nota = null): void
    {
        DB::transaction(function () use ($usuario, $montoReal, $nota) {
            $caja = static::query()->lockForUpdate()->findOrFail($this->id);

            if ($caja->estado !== EstadoCaja::Abierta) {
                throw new RuntimeException('Esta caja ya está cerrada.');
            }

            $esperado = $caja->montoEsperado();

            $caja->update([
                'usuario_cierre_id' => $usuario->id,
                'monto_cierre_esperado' => $esperado,
                'monto_cierre_real' => $montoReal,
                'diferencia' => bcsub($montoReal, $esperado, 2),
                'estado' => EstadoCaja::Cerrada,
                'nota_cierre' => $nota,
                'cerrada_at' => now(),
            ]);
        });

        $this->refresh();
    }

    public function totalMovimientos(TipoMovimientoCaja $tipo): string
    {
        // sum() no pasa por el cast decimal:2 (es un agregado de consulta,
        // no un atributo) — normalizar con bcadd() para no devolver "30" en
        // vez de "30.00" según el driver.
        return bcadd((string) $this->movimientos()->where('tipo', $tipo)->sum('monto'), '0', 2);
    }

    /**
     * Monto esperado si se cerrara ahora mismo (inicial + ingresos - egresos).
     * Es el mismo cálculo que hace cerrar(), expuesto para mostrarlo en el
     * formulario de cierre antes de confirmar.
     */
    public function montoEsperado(): string
    {
        return bcadd(
            bcadd($this->monto_inicial, $this->totalMovimientos(TipoMovimientoCaja::Ingreso), 2),
            bcmul($this->totalMovimientos(TipoMovimientoCaja::Egreso), '-1', 2),
            2
        );
    }

    public function estaAbierta(): bool
    {
        return $this->estado === EstadoCaja::Abierta;
    }
}
