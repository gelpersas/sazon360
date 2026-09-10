<?php

namespace Database\Seeders;

use App\Enums\EstadoComanda;
use App\Enums\MedioPago;
use App\Enums\Rol;
use App\Enums\TipoMovimientoCaja;
use App\Enums\TipoPedido;
use App\Models\AreaPreparacion;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\MovimientoCaja;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\RecetaItem;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Datos base para desarrollo local: la empresa piloto (Dulcita, ver
     * docs/DECISIONES.md DEC-001) y una segunda empresa solo para verificar
     * aislamiento multiempresa — no es un cliente real.
     */
    public function run(): void
    {
        $dulcita = Empresa::create([
            'nombre' => 'Pastelería Dulcita',
            'slug' => 'dulcita',
            'estado' => 'activa',
        ]);

        $sedeDulcita = Sede::create([
            'empresa_id' => $dulcita->id,
            'nombre' => 'Sede Principal',
            'estado' => 'activa',
        ]);

        $adminDulcita = User::factory()->create([
            'name' => 'Admin Dulcita',
            'email' => 'admin@dulcita.test',
        ]);

        $adminDulcita->accesos()->create([
            'empresa_id' => $dulcita->id,
            'sede_id' => null,
            'rol' => Rol::AdministracionCentral,
        ]);

        $adminSedeDulcita = User::factory()->create([
            'name' => 'Admin Sede Principal',
            'email' => 'sede@dulcita.test',
        ]);

        $adminSedeDulcita->accesos()->create([
            'empresa_id' => $dulcita->id,
            'sede_id' => $sedeDulcita->id,
            'rol' => Rol::AdministracionSede,
        ]);

        $categoriaPasteles = Categoria::create([
            'empresa_id' => $dulcita->id,
            'nombre' => 'Pasteles',
            'orden' => 1,
            'estado' => 'activa',
        ]);

        $categoriaBebidas = Categoria::create([
            'empresa_id' => $dulcita->id,
            'nombre' => 'Bebidas',
            'orden' => 2,
            'estado' => 'activa',
        ]);

        $tortaChocolate = Producto::create([
            'empresa_id' => $dulcita->id,
            'categoria_id' => $categoriaPasteles->id,
            'nombre' => 'Torta de chocolate (porción)',
            'precio' => 4.50,
            'estado' => 'activo',
        ]);

        Producto::create([
            'empresa_id' => $dulcita->id,
            'categoria_id' => $categoriaPasteles->id,
            'nombre' => 'Cheesecake de fresa (porción)',
            'precio' => 5.00,
            'estado' => 'activo',
        ]);

        $cafeAmericano = Producto::create([
            'empresa_id' => $dulcita->id,
            'categoria_id' => $categoriaBebidas->id,
            'nombre' => 'Café americano',
            'precio' => 2.00,
            'estado' => 'activo',
        ]);

        // Insumos, receta e inventario inicial (Fase 5, ver docs/ROADMAP.md):
        // el pedido de ejemplo de abajo (2 tortas + 2 cafés) descontará estos
        // insumos automáticamente al cobrarse, dejando un movimiento real.
        $harina = Insumo::create([
            'empresa_id' => $dulcita->id,
            'nombre' => 'Harina',
            'unidad_medida' => 'kg',
            'stock_minimo' => 2,
            'estado' => 'activo',
        ]);

        $cafeEnGrano = Insumo::create([
            'empresa_id' => $dulcita->id,
            'nombre' => 'Café en grano',
            'unidad_medida' => 'g',
            'stock_minimo' => 200,
            'estado' => 'activo',
        ]);

        RecetaItem::create(['producto_id' => $tortaChocolate->id, 'insumo_id' => $harina->id, 'cantidad' => 0.15]);
        RecetaItem::create(['producto_id' => $cafeAmericano->id, 'insumo_id' => $cafeEnGrano->id, 'cantidad' => 18]);

        Inventario::create([
            'empresa_id' => $dulcita->id,
            'sede_id' => $sedeDulcita->id,
            'insumo_id' => $harina->id,
            'cantidad_actual' => 10,
        ]);

        Inventario::create([
            'empresa_id' => $dulcita->id,
            'sede_id' => $sedeDulcita->id,
            'insumo_id' => $cafeEnGrano->id,
            'cantidad_actual' => 1000,
        ]);

        $mesaUno = null;

        foreach (['Mesa 1', 'Mesa 2', 'Mesa 3', 'Mesa 4'] as $i => $nombreMesa) {
            $mesa = Mesa::create([
                'empresa_id' => $dulcita->id,
                'sede_id' => $sedeDulcita->id,
                'nombre' => $nombreMesa,
                'piso' => 'Planta baja',
                'zona' => $i < 2 ? 'Ventana' : 'Interior',
                'capacidad' => 4,
                'estado' => 'activa',
            ]);

            $mesaUno ??= $mesa;
        }

        $areaCocina = AreaPreparacion::create([
            'empresa_id' => $dulcita->id,
            'sede_id' => $sedeDulcita->id,
            'nombre' => 'Cocina',
            'orden' => 1,
            'estado' => 'activa',
        ]);

        $cajeroDulcita = User::factory()->create([
            'name' => 'Cajero Sede Principal',
            'email' => 'caja@dulcita.test',
        ]);

        $cajeroDulcita->accesos()->create([
            'empresa_id' => $dulcita->id,
            'sede_id' => $sedeDulcita->id,
            'rol' => Rol::Caja,
        ]);

        $meseroDulcita = User::factory()->create([
            'name' => 'Mesero Sede Principal',
            'email' => 'mesero@dulcita.test',
        ]);

        $meseroDulcita->accesos()->create([
            'empresa_id' => $dulcita->id,
            'sede_id' => $sedeDulcita->id,
            'rol' => Rol::Mesero,
        ]);

        $areaDulcita = User::factory()->create([
            'name' => 'Cocina Sede Principal',
            'email' => 'cocina@dulcita.test',
        ]);

        $areaDulcita->accesos()->create([
            'empresa_id' => $dulcita->id,
            'sede_id' => $sedeDulcita->id,
            'rol' => Rol::AreaPreparacion,
        ]);

        // Un turno de caja completo ya cerrado, como referencia (ver
        // docs/ROADMAP.md Fase 3: "apertura → movimientos → cierre").
        $cajaDeAyer = Caja::abrir($sedeDulcita, $adminSedeDulcita, '50.00', 'Fondo inicial del día');

        MovimientoCaja::create([
            'caja_id' => $cajaDeAyer->id,
            'empresa_id' => $dulcita->id,
            'usuario_id' => $adminSedeDulcita->id,
            'tipo' => TipoMovimientoCaja::Ingreso,
            'monto' => '35.50',
            'descripcion' => 'Venta mostrador — tortas y café',
        ]);

        MovimientoCaja::create([
            'caja_id' => $cajaDeAyer->id,
            'empresa_id' => $dulcita->id,
            'usuario_id' => $adminSedeDulcita->id,
            'tipo' => TipoMovimientoCaja::Egreso,
            'monto' => '8.00',
            'descripcion' => 'Compra de servilletas e insumos de limpieza',
        ]);

        $cajaDeAyer->cerrar($adminSedeDulcita, '77.50', 'Cuadró exacto con lo esperado');

        // Un pedido completo ya cobrado, como referencia (ver docs/ROADMAP.md
        // Fase 4: "Dulcita puede operar un turno real... de principio a fin").
        $pedidoDeAyer = Pedido::abrir(
            sede: $sedeDulcita,
            usuario: $meseroDulcita,
            tipo: TipoPedido::Mesa,
            mesa: $mesaUno,
            idempotencyKey: 'seed-pedido-demo-'.$mesaUno->id,
        );

        ItemPedido::create([
            'pedido_id' => $pedidoDeAyer->id,
            'producto_id' => $tortaChocolate->id,
            'area_preparacion_id' => $areaCocina->id,
            'nombre_producto' => $tortaChocolate->nombre,
            'precio_unitario' => $tortaChocolate->precio,
            'cantidad' => 2,
        ]);

        ItemPedido::create([
            'pedido_id' => $pedidoDeAyer->id,
            'producto_id' => $cafeAmericano->id,
            'area_preparacion_id' => $areaCocina->id,
            'nombre_producto' => $cafeAmericano->nombre,
            'precio_unitario' => $cafeAmericano->precio,
            'cantidad' => 2,
        ]);

        $comandasDeAyer = $pedidoDeAyer->enviarComanda();
        $comandasDeAyer->first()->update(['estado' => EstadoComanda::Entregada]);

        Pago::registrar(
            pedido: $pedidoDeAyer,
            usuario: $cajeroDulcita,
            medio: MedioPago::Efectivo,
            monto: $pedidoDeAyer->total(),
            idempotencyKey: 'seed-pago-demo-'.$pedidoDeAyer->id,
        );

        $empresaQa = Empresa::create([
            'nombre' => 'Empresa Demo QA',
            'slug' => 'demo-qa',
            'estado' => 'activa',
        ]);

        Sede::create([
            'empresa_id' => $empresaQa->id,
            'nombre' => 'Sede QA',
            'estado' => 'activa',
        ]);

        $adminQa = User::factory()->create([
            'name' => 'Admin Demo QA',
            'email' => 'admin@demoqa.test',
        ]);

        $adminQa->accesos()->create([
            'empresa_id' => $empresaQa->id,
            'sede_id' => null,
            'rol' => Rol::AdministracionCentral,
        ]);
    }
}
