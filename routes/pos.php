<?php

use App\Http\Controllers\Pos\AuthController;
use App\Http\Controllers\Pos\CajaController;
use App\Http\Controllers\Pos\ClienteController;
use App\Http\Controllers\Pos\ComandaController;
use App\Http\Controllers\Pos\DashboardController;
use App\Http\Controllers\Pos\GrupoMesaController;
use App\Http\Controllers\Pos\PedidoController;
use App\Http\Controllers\Pos\ReferenciaController;
use App\Http\Controllers\Pos\SubCuentaController;
use Illuminate\Support\Facades\Route;

// Rate limiting en login (ver .claude/rules/seguridad.md, "endpoints sensibles").
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::patch('/me/tema', [AuthController::class, 'actualizarTema'])->name('me.tema');

    Route::get('/sedes', [ReferenciaController::class, 'sedes'])->name('sedes.index');
    Route::get('/sedes/{sede}/mesas', [ReferenciaController::class, 'mesas'])->name('sedes.mesas');
    Route::get('/sedes/{sede}/dashboard', [DashboardController::class, 'index'])->name('sedes.dashboard');
    Route::get('/sedes/{sede}/areas', [ReferenciaController::class, 'areas'])->name('sedes.areas');
    Route::get('/sedes/{sede}/catalogo', [ReferenciaController::class, 'catalogo'])->name('sedes.catalogo');

    Route::get('/sedes/{sede}/pedidos', [PedidoController::class, 'index'])->name('pedidos.index');
    Route::post('/sedes/{sede}/pedidos', [PedidoController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('pedidos.store');
    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->name('pedidos.show');
    Route::post('/pedidos/{pedido}/items', [PedidoController::class, 'agregarItem'])->name('pedidos.items.store');
    Route::patch('/pedidos/{pedido}/items/{item}', [PedidoController::class, 'actualizarItem'])->name('pedidos.items.update');
    Route::delete('/pedidos/{pedido}/items/{item}', [PedidoController::class, 'quitarItem'])->name('pedidos.items.destroy');
    Route::post('/pedidos/{pedido}/enviar-comanda', [PedidoController::class, 'enviarComanda'])->name('pedidos.enviar-comanda');
    Route::post('/pedidos/{pedido}/pagos', [PedidoController::class, 'pagar'])
        ->middleware('throttle:30,1')
        ->name('pedidos.pagar');
    Route::post('/pedidos/{pedido}/anular', [PedidoController::class, 'anular'])->name('pedidos.anular');
    Route::patch('/pedidos/{pedido}/cliente', [PedidoController::class, 'asignarCliente'])->name('pedidos.cliente.update');

    Route::get('/sedes/{sede}/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::post('/sedes/{sede}/clientes', [ClienteController::class, 'store'])->name('clientes.store');

    Route::get('/pedidos/{pedido}/sub-cuentas', [SubCuentaController::class, 'index'])->name('sub-cuentas.index');
    Route::post('/pedidos/{pedido}/sub-cuentas', [SubCuentaController::class, 'store'])->name('sub-cuentas.store');
    Route::delete('/sub-cuentas/{subCuenta}', [SubCuentaController::class, 'destroy'])->name('sub-cuentas.destroy');
    Route::post('/sub-cuentas/{subCuenta}/pagos', [SubCuentaController::class, 'pagar'])
        ->middleware('throttle:30,1')
        ->name('sub-cuentas.pagar');

    Route::get('/sedes/{sede}/comandas', [ComandaController::class, 'index'])->name('comandas.index');
    Route::post('/comandas/{comanda}/avanzar', [ComandaController::class, 'avanzar'])->name('comandas.avanzar');

    Route::get('/sedes/{sede}/grupos-mesa', [GrupoMesaController::class, 'index'])->name('grupos-mesa.index');
    Route::post('/sedes/{sede}/grupos-mesa', [GrupoMesaController::class, 'store'])->name('grupos-mesa.store');
    Route::get('/grupos-mesa/{grupoMesa}', [GrupoMesaController::class, 'show'])->name('grupos-mesa.show');
    Route::post('/grupos-mesa/{grupoMesa}/mesas', [GrupoMesaController::class, 'agregarMesa'])->name('grupos-mesa.mesas.store');
    Route::post('/grupos-mesa/{grupoMesa}/disolver', [GrupoMesaController::class, 'disolver'])->name('grupos-mesa.disolver');

    Route::get('/sedes/{sede}/caja', [CajaController::class, 'actual'])->name('caja.actual');
    Route::post('/sedes/{sede}/caja/abrir', [CajaController::class, 'abrir'])->name('caja.abrir');
    Route::post('/cajas/{caja}/cerrar', [CajaController::class, 'cerrar'])->name('caja.cerrar');
    Route::post('/cajas/{caja}/movimientos', [CajaController::class, 'registrarMovimiento'])->name('caja.movimientos.store');
});
