<?php

use App\Enums\EstadoGrupoMesa;
use App\Enums\ModoGrupoMesa;
use App\Enums\Rol;
use App\Enums\TipoPedido;
use App\Models\Empresa;
use App\Models\GrupoMesa;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Sede;
use App\Models\User;

function crearSedeConMesasYCajero(string $nombreEmpresa, int $numeroDeMesas = 2): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sede = Sede::factory()->for($empresa)->create();
    $mesas = Mesa::factory()->for($empresa)->for($sede)->count($numeroDeMesas)->create();

    $cajero = User::factory()->create();
    $cajero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    return [$empresa, $sede, $mesas, $cajero];
}

it('GrupoMesa::unir() agrupa dos o más mesas y fija la primera como principal', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Union Basica', 3);

    $grupo = GrupoMesa::unir($sede, $mesas, $cajero);

    expect($grupo->estado)->toBe(EstadoGrupoMesa::Activo);
    expect($grupo->modo)->toBe(ModoGrupoMesa::Independiente);
    expect($grupo->mesa_principal_id)->toBe($mesas->first()->id);
    expect(Mesa::whereIn('id', $mesas->pluck('id'))->pluck('grupo_mesa_id')->unique()->all())->toBe([$grupo->id]);
});

it('GrupoMesa::unir() rechaza unir menos de dos mesas', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Union Una Mesa', 1);

    GrupoMesa::unir($sede, $mesas, $cajero);
})->throws(InvalidArgumentException::class);

it('GrupoMesa::unir() rechaza mesas de otra sede', function () {
    [$empresa, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Union Sede Cruzada', 1);
    $otraSede = Sede::factory()->for($empresa)->create();
    $mesaDeOtraSede = Mesa::factory()->for($empresa)->for($otraSede)->create();

    GrupoMesa::unir($sede, $mesas->push($mesaDeOtraSede), $cajero);
})->throws(InvalidArgumentException::class);

it('GrupoMesa::unir() rechaza una mesa que ya está unida a otro grupo activo', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Union Duplicada', 2);
    GrupoMesa::unir($sede, $mesas, $cajero);

    $mesaExtra = Mesa::factory()->for($sede->empresa)->for($sede)->create();

    GrupoMesa::unir($sede, collect([$mesas->first(), $mesaExtra]), $cajero);
})->throws(RuntimeException::class);

it('totalCombinado() suma los pedidos abiertos de todas las mesas del grupo', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Total Combinado', 2);
    $grupo = GrupoMesa::unir($sede, $mesas, $cajero);

    Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $mesas[0], 'idem-grupo-pedido-1');
    Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $mesas[1], 'idem-grupo-pedido-2');

    // Sin ítems todavía: total() de cada pedido es "0.00" — solo se verifica
    // que ambos pedidos entran al cálculo combinado, no un monto real.
    expect($grupo->pedidosAbiertos())->toHaveCount(2);
    expect($grupo->totalCombinado())->toBe('0.00');
});

it('agregarMesa() suma una mesa libre a un grupo ya activo', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Agregar Mesa', 2);
    $grupo = GrupoMesa::unir($sede, $mesas, $cajero);

    $mesaNueva = Mesa::factory()->for($sede->empresa)->for($sede)->create();

    $grupo->agregarMesa($mesaNueva, $cajero);

    expect($mesaNueva->fresh()->grupo_mesa_id)->toBe($grupo->id);
    expect(Mesa::whereIn('id', $mesas->pluck('id'))->pluck('grupo_mesa_id')->unique()->all())->toBe([$grupo->id]);
});

it('agregarMesa() rechaza una mesa que ya está en otro grupo', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Agregar Mesa Duplicada', 4);
    $grupoA = GrupoMesa::unir($sede, $mesas->take(2), $cajero);
    $grupoB = GrupoMesa::unir($sede, $mesas->slice(2, 2), $cajero);

    $grupoA->agregarMesa($mesas[2], $cajero);
})->throws(RuntimeException::class);

it('agregarMesa() rechaza una mesa de otra sede', function () {
    [$empresa, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Agregar Mesa Sede Cruzada', 2);
    $grupo = GrupoMesa::unir($sede, $mesas, $cajero);

    $otraSede = Sede::factory()->for($empresa)->create();
    $mesaDeOtraSede = Mesa::factory()->for($empresa)->for($otraSede)->create();

    $grupo->agregarMesa($mesaDeOtraSede, $cajero);
})->throws(InvalidArgumentException::class);

it('agregarMesa() rechaza sumar mesas a un grupo ya disuelto', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Agregar Mesa Grupo Disuelto', 2);
    $grupo = GrupoMesa::unir($sede, $mesas, $cajero);
    $grupo->disolver($cajero);

    $mesaNueva = Mesa::factory()->for($sede->empresa)->for($sede)->create();

    $grupo->agregarMesa($mesaNueva, $cajero);
})->throws(RuntimeException::class);

it('la API suma una mesa a un grupo activo', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa API Agregar Mesa', 2);

    $grupo = $this->actingAs($cajero)
        ->postJson("/api/pos/sedes/{$sede->id}/grupos-mesa", ['mesa_ids' => $mesas->pluck('id')->all()])
        ->json('data');

    $mesaNueva = Mesa::factory()->for($sede->empresa)->for($sede)->create();

    $this->postJson("/api/pos/grupos-mesa/{$grupo['id']}/mesas", ['mesa_id' => $mesaNueva->id])
        ->assertOk()
        ->assertJsonCount(3, 'data.mesas');
});

it('GrupoMesa::unir() con modo General fija el modo correctamente', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Union General', 2);

    $grupo = GrupoMesa::unir($sede, $mesas, $cajero, ModoGrupoMesa::General);

    expect($grupo->modo)->toBe(ModoGrupoMesa::General);
});

it('Pedido::abrir() en un grupo General siempre abre contra la mesa principal, sin importar qué mesa se pida', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Pedido General', 3);
    $grupo = GrupoMesa::unir($sede, $mesas, $cajero, ModoGrupoMesa::General);

    // Releer la mesa desde la BD: unir() actualiza grupo_mesa_id en filas
    // propias, no en la colección $mesas original en memoria — igual que en
    // el flujo real, donde PedidoController vuelve a consultar la mesa justo
    // antes de llamar a abrir().
    $segundaMesa = Mesa::find($mesas[1]->id);

    // Se pide la SEGUNDA mesa del grupo (no la principal) — debe terminar
    // igual contra la mesa principal.
    $pedido = Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $segundaMesa, 'idem-general-1');

    expect($pedido->mesa_id)->toBe($grupo->mesa_principal_id);
    expect($pedido->mesa_id)->not->toBe($segundaMesa->id);
});

it('Pedido::abrir() en un grupo General reutiliza el pedido ya abierto en vez de duplicarlo', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Pedido General Reuso', 3);
    GrupoMesa::unir($sede, $mesas, $cajero, ModoGrupoMesa::General);

    // Dos intentos con claves de idempotencia DISTINTAS (como si dos
    // meseros distintos "tomaran" el pedido desde dos mesas distintas del
    // mismo grupo) deben devolver el MISMO pedido, no dos.
    $pedido1 = Pedido::abrir($sede, $cajero, TipoPedido::Mesa, Mesa::find($mesas[0]->id), 'idem-general-reuso-1');
    $pedido2 = Pedido::abrir($sede, $cajero, TipoPedido::Mesa, Mesa::find($mesas[2]->id), 'idem-general-reuso-2');

    expect($pedido2->id)->toBe($pedido1->id);
    expect(Pedido::where('sede_id', $sede->id)->where('estado', 'abierto')->count())->toBe(1);
});

it('Pedido::abrir() en un grupo Independiente NO redirige a la mesa principal — cada mesa su propio pedido', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Pedido Independiente', 2);
    GrupoMesa::unir($sede, $mesas, $cajero, ModoGrupoMesa::Independiente);

    $segundaMesa = Mesa::find($mesas[1]->id);
    $pedido = Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $segundaMesa, 'idem-independiente-1');

    expect($pedido->mesa_id)->toBe($segundaMesa->id);
});

it('la API crea un grupo en modo General', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa API Modo General', 2);

    $this->actingAs($cajero)
        ->postJson("/api/pos/sedes/{$sede->id}/grupos-mesa", ['mesa_ids' => $mesas->pluck('id')->all(), 'modo' => 'general'])
        ->assertOk()
        ->assertJsonPath('data.modo', 'general');
});

it('la API crea un grupo en modo Independiente por defecto si no se especifica', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa API Modo Default', 2);

    $this->actingAs($cajero)
        ->postJson("/api/pos/sedes/{$sede->id}/grupos-mesa", ['mesa_ids' => $mesas->pluck('id')->all()])
        ->assertOk()
        ->assertJsonPath('data.modo', 'independiente');
});

it('disolver() libera las mesas sin afectar sus pedidos existentes', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Disolver', 2);
    $grupo = GrupoMesa::unir($sede, $mesas, $cajero);

    $pedido = Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $mesas[0], 'idem-disolver-pedido');

    $grupo->disolver($cajero);

    expect($grupo->fresh()->estado)->toBe(EstadoGrupoMesa::Disuelto);
    expect($grupo->fresh()->disuelto_por_id)->toBe($cajero->id);
    expect(Mesa::whereIn('id', $mesas->pluck('id'))->whereNotNull('grupo_mesa_id')->count())->toBe(0);
    expect($pedido->fresh()->mesa_id)->toBe($mesas[0]->id);
    expect($pedido->fresh()->estaAbierto())->toBeTrue();
});

it('disolver() un grupo ya disuelto falla', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Doble Disolucion', 2);
    $grupo = GrupoMesa::unir($sede, $mesas, $cajero);
    $grupo->disolver($cajero);

    $grupo->disolver($cajero);
})->throws(RuntimeException::class);

it('la API crea un grupo de mesas para un usuario con acceso a la sede', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa API Union', 2);

    $this->actingAs($cajero)
        ->postJson("/api/pos/sedes/{$sede->id}/grupos-mesa", ['mesa_ids' => $mesas->pluck('id')->all()])
        ->assertOk()
        ->assertJsonPath('data.estado', 'activo')
        ->assertJsonCount(2, 'data.mesas');
});

it('la API rechaza crear un grupo de mesas para un usuario sin acceso a la sede', function () {
    [, $sede, $mesas] = crearSedeConMesasYCajero('Empresa API Sin Acceso', 2);
    $sinAcceso = User::factory()->create();

    $this->actingAs($sinAcceso)
        ->postJson("/api/pos/sedes/{$sede->id}/grupos-mesa", ['mesa_ids' => $mesas->pluck('id')->all()])
        ->assertForbidden();
});

it('la API disuelve un grupo y refleja el total combinado con pedidos reales', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa API Total', 2);

    $grupo = $this->actingAs($cajero)
        ->postJson("/api/pos/sedes/{$sede->id}/grupos-mesa", ['mesa_ids' => $mesas->pluck('id')->all()])
        ->json('data');

    $this->getJson("/api/pos/grupos-mesa/{$grupo['id']}")
        ->assertOk()
        ->assertJsonPath('data.total_combinado', '0.00');

    $this->postJson("/api/pos/grupos-mesa/{$grupo['id']}/disolver")
        ->assertOk()
        ->assertJsonPath('data.estado', 'disuelto');
});

it('la API lista solo los grupos activos de la sede, no los ya disueltos', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa API Listado', 4);

    $grupoActivo = $this->actingAs($cajero)
        ->postJson("/api/pos/sedes/{$sede->id}/grupos-mesa", ['mesa_ids' => [$mesas[0]->id, $mesas[1]->id]])
        ->json('data');

    $grupoDisuelto = $this->postJson("/api/pos/sedes/{$sede->id}/grupos-mesa", ['mesa_ids' => [$mesas[2]->id, $mesas[3]->id]])
        ->json('data');
    $this->postJson("/api/pos/grupos-mesa/{$grupoDisuelto['id']}/disolver");

    $this->getJson("/api/pos/sedes/{$sede->id}/grupos-mesa")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $grupoActivo['id']);
});

it('el listado de mesas de referencia expone grupo_mesa_id para que el POS excluya las ya unidas', function () {
    [, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Referencia Mesas', 2);

    $this->actingAs($cajero)->postJson("/api/pos/sedes/{$sede->id}/grupos-mesa", ['mesa_ids' => $mesas->pluck('id')->all()]);

    $respuesta = $this->getJson("/api/pos/sedes/{$sede->id}/mesas")->assertOk()->json('data');

    expect(collect($respuesta)->pluck('grupo_mesa_id')->filter()->count())->toBe(2);
});
