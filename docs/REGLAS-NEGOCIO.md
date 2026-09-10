# Reglas de negocio — Sazón360

> Estado: la mayoría son **propuestas** derivadas del prompt de arranque del proyecto (repo vacío, ver DEC-000). Se marcan explícitamente como `[Confirmada]`, `[Propuesta]` o `[Pendiente]`. Ninguna regla aquí tiene todavía código que la implemente.

## Empresas y sedes

- `[Confirmada]` Toda entidad operativa (venta, caja, mesa, inventario, empleado) debe estar asociada a una empresa y, cuando corresponda, a una sede.
- `[Confirmada]` Una empresa puede tener una o varias sedes; una sede pertenece a una sola empresa.
- `[Confirmada]` Estrategia de aislamiento: base de datos compartida + columna `empresa_id`/`sede_id` (no schema-per-tenant) — implementada en Fase 1, ver DEC-005 en `DECISIONES.md`.

## Usuarios y permisos

- `[Confirmada]` Los permisos deben validarse siempre en el backend, nunca confiar únicamente en lo que oculta/muestra el frontend. Implementado con Policies (`EmpresaPolicy`, `SedePolicy`) + helpers en `User` (`esAdminCentralDe`, `rolEnSede`, `sedesAccesibles`).
- `[Confirmada]` Un usuario puede tener acceso a una o varias empresas/sedes, con rol potencialmente distinto por sede (tabla `accesos`, `sede_id` nulo = acceso a toda la empresa, solo válido con rol `administracion_central`).
- `[Confirmada]` Los 5 roles de `docs/PRODUCTO.md` (`administracion_central`, `administracion_sede`, `caja`, `mesero`, `area_preparacion`) están modelados en `App\Enums\Rol`. Solo los dos roles de administración acceden al panel Filament; los operativos usarán el POS táctil (Fase 4).

## Turnos

- `[Pendiente]` Modelo de turnos y asistencia — alcance exacto (fichaje, horarios, control de tardanzas) no definido; es funcionalidad post-MVP según `PRODUCTO.md`.

## Caja

- `[Confirmada]` Apertura y cierre de caja quedan asociados a sede y usuario responsable (`usuario_apertura_id`/`usuario_cierre_id`). "Turno" y "Caja" son el mismo concepto en el MVP — una sesión de apertura→movimientos→cierre, sin un "punto de cobro" físico separado (ver DEC-008). Como mucho una caja abierta por sede a la vez.
- `[Confirmada]` Los movimientos de caja deben mantener historial (no se sobrescriben, se acumulan) — no hay acción de editar ni borrar un `MovimientoCaja` en el panel; si se registró mal, se compensa con otro movimiento.
- `[Confirmada]` El monto esperado al cerrar se recalcula siempre desde los movimientos reales (`monto_inicial` + ingresos − egresos), no desde un contador acumulado — evita drift. La diferencia con el monto contado físicamente queda registrada (`diferencia`), no se oculta ni se ajusta automáticamente.
- `[Confirmada]` Cerrar una caja ya cerrada se rechaza explícitamente (protección contra doble cierre concurrente, con bloqueo de fila dentro de una transacción). Reapertura de una caja cerrada — **fuera de alcance de Fase 3**, no implementada.
- `[Propuesta, a confirmar]` Tolerancia de pago de suscripción: si el pago de la empresa (SaaS) se retrasa, no se debe interrumpir un turno o caja que ya esté abierto. El bloqueo de nuevas operaciones aplica solo después de una ventana de tolerancia (ej. varios días, monto exacto pendiente de definir), conservando siempre acceso de consulta y exportación de datos. (Sin relación con la Fase 3 construida — esto es sobre el módulo de facturación SaaS, que no existe todavía.)

## Productos

- `[Propuesta]` Los precios históricos de una venta no deben cambiar si cambia el precio del producto (la venta guarda el precio al momento de la transacción) — **implementación pendiente de Fase 4** (`ItemPedido` todavía no existe); la regla se mantiene como propuesta hasta que haya código que la aplique.
- `[Confirmada]` El catálogo (`Categoria`/`Producto`) es por empresa, compartido entre todas sus sedes — no hay precio ni disponibilidad distintos por sede (ver DEC-006). Solo `administracion_central` puede crear/editar/eliminar catálogo.
- `[Pendiente]` Modelo exacto de variantes y modificadores — deliberadamente fuera de la Fase 2 (ver DEC-006/riesgo de sobre-ingeniería en `docs/ROADMAP.md`); se define si Dulcita confirma una necesidad real.

## Mesas

- `[Confirmada]` Una mesa pertenece a una sede; `piso`/`zona` son texto libre, no entidades normalizadas (ver DEC-007).
- `[Confirmada]` `administracion_sede` puede gestionar (crear/editar/eliminar) solo las mesas de su propia sede; `administracion_central` gestiona las de cualquier sede de su empresa. Verificado tanto a nivel de Policy como con una validación adicional en el modelo (`Mesa::booted()`), no solo en el formulario de Filament.
- `[Confirmada]` Una mesa puede unirse a otras en un `GrupoMesa` (`grupo_mesa_id`) — deliberadamente no es un estado operativo nuevo, se deriva de si esa columna es nula o no (ver DEC-025).
- `[Pendiente]` Estados operativos de ocupación más allá de la unión (libre/ocupada/reservada) — no implementado; el estado actual de `Mesa` (`activa`/`inactiva`) sigue siendo solo disponibilidad física.

## Pedidos

- `[Confirmada]` Un pedido puede originarse en mesa, mostrador, para llevar o domicilio (`App\Enums\TipoPedido`). "Domicilio" solo captura el tipo de venta — no hay logística de despacho/repartidor (fuera del MVP, ver `docs/PRODUCTO.md`).
- `[Confirmada]` Los precios históricos de una venta no cambian si cambia el precio del producto — `ItemPedido` congela `nombre_producto`/`precio_unitario` al agregarlo.
- `[Confirmada]` Las operaciones repetidas por pérdida de conexión deben ser idempotentes — implementado con `idempotency_key` en la creación de pedido y de pago, y con el propio diseño de "enviar a cocina" (solo toma ítems pendientes, un reintento no duplica). Ver `docs/DECISIONES.md` DEC-009 y `docs/ROADMAP.md` Fase 4.
- `[Pospuesta]` Pedidos por encargo (fecha futura de entrega) — fuera de alcance de Fase 4, ver DEC-009.
- `[Confirmada]` Unir mesas (`GrupoMesa`, Fase 9) **no fusiona pedidos ni comandas** — cada mesa conserva el suyo, sin cambios en cocina, inventario ni factura. Ver DEC-025.

## Comandas

- `[Confirmada]` Una comanda enviada a un área de preparación no se elimina físicamente — no hay `DeleteAction` en ningún lugar del sistema para `Comanda`.
- `[Confirmada]` Las comandas se separan por área de preparación configurada en la sede (`AreaPreparacion`, gestionada en Filament). El enrutamiento de cada ítem a un área se elige manualmente al agregarlo al pedido, no automáticamente desde el producto — ver DEC-010.
- `[Pendiente]` Anulación de una comanda con trazabilidad (quién, cuándo, por qué) — el enum `EstadoComanda` contempla el estado `anulada`, pero no hay ninguna acción de UI/API que lo dispare todavía en Fase 4.

## Cocina y KDS

- `[Confirmada]` El KDS se actualiza en tiempo real vía WebSockets (Laravel Reverb) — sin polling desde Fase 8 (ver DEC-002, DEC-023). El tablero muestra comandas en estado `pendiente`, `en_preparacion` y `lista` (una comanda `lista` debe seguir visible para poder entregarla); desaparece al quedar `entregada`.
- `[Confirmada]` Las estaciones del KDS (`AreaPreparacion`) son configurables por sede, no fijas en código.

## División y unión de cuentas

- `[Confirmada]` Unión de mesas: agrupa mesas (`GrupoMesa`) sin fusionar sus pedidos — cada mesa mantiene el suyo, incluidas comandas y descuento de inventario. Auditado (`creado_por_id`/`disuelto_por_id`/timestamps). Ver DEC-025.
- `[Confirmada]` División de cuenta "por mesa original" se resuelve cobrando cada pedido del grupo por separado — no requiere ninguna operación especial. "Partes iguales"/"por persona" son cálculo de referencia (`GrupoMesa::totalCombinado()` entre N); el cobro real se sigue registrando pedido por pedido, sin facturación por sub-cuenta.
- `[Confirmada]` División "por productos"/"por personas" (`SubCuenta`, Fase 10): reparte ítems de un pedido (completos o por porcentaje) entre sub-cuentas con nombre libre — mismo mecanismo para ambas modalidades. El pago sigue siendo un `Pago` normal contra el pedido, solo etiquetado (`sub_cuenta_id`) — sin facturación por sub-cuenta, sin cambios a cuándo el pedido queda cobrado. Ver DEC-026.
- `[Confirmada]` No se exige que el 100% de los ítems de un pedido quede asignado a alguna sub-cuenta antes de poder cobrar — una sub-cuenta y un pago "normal" (sin etiquetar) pueden coexistir en el mismo pedido (mismo criterio de "no bloquear" que el inventario, DEC-016).
- `[Confirmada]` Una sub-cuenta sin pagos se puede eliminar (deshacer); una vez tiene algún pago, queda fija — mismo criterio que `MovimientoCaja`/`Compra`.
- `[Pendiente]` Propina — no existe el concepto en el dominio todavía; si se necesita, se cobra como monto libre dentro de un `Pago` existente.

## Pagos

- `[Confirmada]` Deben soportarse múltiples medios de pago por venta (pago mixto) — un `Pedido` puede tener varios `Pago` con distinto `medio`; queda `cobrado` cuando la suma alcanza el total.
- `[Confirmada]` Los pagos no se registran dos veces por reintento — `Pago::registrar()` es idempotente por `idempotency_key`.
- `[Pendiente]` Descuentos y anulaciones (de pedido completo sí existe — `Pedido::anular()` —, de un pago individual no) requieren permiso explícito y quedan auditados — la anulación de pedido no tiene un registro de auditoría separado todavía (solo el cambio de `estado` y `cerrado_at`).

## Inventario

- `[Confirmada]` El inventario (`Insumo` por empresa, `Inventario` por sede) se descuenta automáticamente al **cobrar** un pedido, no al enviarlo a cocina ni al crearlo — ver DEC-014.
- `[Confirmada]` Los movimientos de inventario mantienen historial — `MovimientoInventario` no se edita ni se borra; toda entrada/salida/ajuste queda como una fila nueva.
- `[Confirmada]` No hay bloqueo de venta por falta de stock — cobrar siempre descuenta, incluso a negativo. Solo hay alerta visual (stock por debajo del mínimo) — ver DEC-016.
- `[Pendiente]` Compras a proveedores, mermas formales y traslados entre sedes — explícitamente fuera de Fase 5 (Fase 7 del roadmap).

## Recetas

- `[Confirmada]` Una receta (`RecetaItem`) es simple: un producto consume una cantidad fija de uno o más insumos por unidad vendida. Sin variantes ni modificadores (mismo criterio YAGNI que el catálogo — ver riesgo de Fase 5 en `docs/ROADMAP.md`).
- `[Confirmada]` Un producto sin receta configurada no genera ningún movimiento de inventario al venderse — no es un error, simplemente no tiene insumos asociados.

## Compras

- `[Confirmada]` Una compra se registra en un solo paso (encabezado + ítems juntos) y aumenta el inventario de la sede receptora inmediatamente — sin un estado intermedio "orden pendiente de recibir" (ver DEC-021). Requiere al menos un ítem.
- `[Confirmada]` Una compra ya registrada no se edita ni se borra — si se registró mal, se corrige con un ajuste manual de inventario (mismo criterio que `MovimientoCaja`/`MovimientoInventario`).
- `[Confirmada]` Puede registrar una compra cualquiera con acceso a la sede que recibe, no solo administración central (mismo criterio que `Caja`) — pero solo administración central gestiona el catálogo de `Proveedor`.

## Mermas

- `[Confirmada]` Una merma es un `MovimientoInventario` de tipo `merma` — resta stock igual que una salida manual, con un motivo específico de pérdida/daño. No es una entidad ni un flujo separado (ver DEC-021).

## Traslados

- `[Confirmada]` Un traslado mueve una cantidad de un insumo de una sede a otra de la misma empresa en un solo paso (sin estado "en tránsito"), descontando origen y aumentando destino en la misma transacción — ver DEC-021.
- `[Confirmada]` Autorizar un traslado exige acceso a **ambas** sedes (origen y destino), no solo a una — a diferencia de Caja/Compra, que solo requieren acceso a la sede involucrada.
- `[Confirmada]` Sin bloqueo por falta de stock en origen (mismo criterio que las ventas, ver DEC-016) — el stock de origen puede quedar negativo.

## Reportes

- `[Confirmada]` El reporte consolidado de ventas por sede solo lo ve administración central — cruzar ventas de todas las sedes es información a nivel de empresa (ver DEC-024).
- `[Confirmada]` Las ventas se cuentan por el momento del cobro (`Pedido.cerrado_at`/`Pago.created_at`), no por el momento de apertura del pedido.

## Auditoría

- `[Propuesta]` Toda acción sensible (descuentos, anulaciones, reaperturas de caja/pedido, cambios de precio, movimientos de caja/inventario) debe quedar registrada con usuario, fecha/hora y detalle del cambio.
- `[Propuesta]` Los procesos críticos (cierre de caja, cobro, anulación) deben ejecutarse dentro de transacciones de base de datos.
- `[Propuesta]` Las operaciones que puedan repetirse por pérdida de conexión (envío de comanda, registro de pago) deben ser idempotentes.
