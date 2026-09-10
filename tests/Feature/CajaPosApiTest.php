<?php

use App\Enums\Rol;
use App\Models\Caja;
use App\Models\Empresa;
use App\Models\Sede;
use App\Models\User;

function crearSedeYCajero(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sede = Sede::factory()->for($empresa)->create();

    $cajero = User::factory()->create();
    $cajero->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::Caja,
    ]);

    return [$empresa, $sede, $cajero];
}

it('no hay caja abierta al inicio: el POS recibe null', function () {
    [, $sede, $cajero] = crearSedeYCajero('Empresa Caja Vacia');

    $this->actingAs($cajero);

    $this->getJson("/api/pos/sedes/{$sede->id}/caja")
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('flujo completo desde el POS: abrir caja, registrar movimientos y cerrar', function () {
    [, $sede, $cajero] = crearSedeYCajero('Empresa Turno POS');

    $this->actingAs($cajero);

    $caja = $this->postJson("/api/pos/sedes/{$sede->id}/caja/abrir", [
        'monto_inicial' => '50.00',
        'nota' => 'Fondo inicial del día',
    ])->assertOk()->assertJsonPath('data.estado', 'abierta')->json('data');

    $this->getJson("/api/pos/sedes/{$sede->id}/caja")
        ->assertOk()
        ->assertJsonPath('data.id', $caja['id']);

    $this->postJson("/api/pos/cajas/{$caja['id']}/movimientos", [
        'tipo' => 'ingreso',
        'monto' => '30.00',
        'descripcion' => 'Venta mostrador en efectivo',
    ])->assertOk()->assertJsonPath('data.monto_esperado', '80.00');

    $this->postJson("/api/pos/cajas/{$caja['id']}/movimientos", [
        'tipo' => 'egreso',
        'monto' => '5.00',
        'descripcion' => 'Compra de servilletas',
    ])->assertOk()->assertJsonPath('data.monto_esperado', '75.00');

    $cierre = $this->postJson("/api/pos/cajas/{$caja['id']}/cerrar", [
        'monto_real' => '75.00',
        'nota' => 'Cuadró exacto',
    ])->assertOk();

    expect($cierre->json('data.estado'))->toBe('cerrada');
    expect($cierre->json('data.diferencia'))->toBe('0.00');

    $this->getJson("/api/pos/sedes/{$sede->id}/caja")
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('rechaza abrir una segunda caja en la misma sede desde el POS', function () {
    [, $sede, $cajero] = crearSedeYCajero('Empresa Doble Apertura POS');

    $this->actingAs($cajero);

    $this->postJson("/api/pos/sedes/{$sede->id}/caja/abrir", ['monto_inicial' => '20.00'])->assertOk();

    $this->postJson("/api/pos/sedes/{$sede->id}/caja/abrir", ['monto_inicial' => '20.00'])
        ->assertUnprocessable();
});

it('un usuario sin acceso a la sede no puede ver ni abrir su caja', function () {
    [, $sedeA] = crearSedeYCajero('Empresa Caja Aislamiento A');
    [, , $cajeroB] = crearSedeYCajero('Empresa Caja Aislamiento B');

    $this->actingAs($cajeroB);

    $this->getJson("/api/pos/sedes/{$sedeA->id}/caja")->assertForbidden();
    $this->postJson("/api/pos/sedes/{$sedeA->id}/caja/abrir", ['monto_inicial' => '20.00'])->assertForbidden();
});

it('rechaza un movimiento con monto no positivo desde el POS', function () {
    [, $sede, $cajero] = crearSedeYCajero('Empresa Movimiento Invalido POS');

    $this->actingAs($cajero);

    $caja = Caja::abrir($sede, $cajero, '20.00');

    $this->postJson("/api/pos/cajas/{$caja->id}/movimientos", [
        'tipo' => 'ingreso',
        'monto' => '0',
        'descripcion' => 'Intento inválido',
    ])->assertUnprocessable();
});
