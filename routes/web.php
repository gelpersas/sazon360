<?php

use App\Http\Controllers\TemaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Sincroniza el selector de tema nativo de Filament con `users.tema` — ver
// TemaController y resources/views/filament/tema-sync.blade.php.
Route::patch('/tema', [TemaController::class, 'actualizar'])
    ->middleware('auth')
    ->name('tema.update');

// Una sola vista para todo el SPA del POS — el enrutamiento real de
// /pos/login, /pos/kds, etc. lo hace vue-router en el cliente. Sin esto, un
// refresh de página en /pos/kds daría 404 en vez de recargar el SPA ahí.
Route::get('/pos/{cualquiera?}', function () {
    return view('pos');
})->where('cualquiera', '.*');
