# Modelo de datos — Sazón360

> Estado: Empresa/Sede/Acceso **implementadas** (Fase 1, ver `docs/ROADMAP.md`). El resto sigue siendo propuesta. No crear migraciones a partir de las secciones "propuesta" sin pasar por `/crear-modulo` y aprobación explícita del alcance.

## Convenciones (confirmadas en Fase 1, aplican hacia adelante)

- Llave primaria `id` (bigint autoincremental) — decidido, no UUID (ver DEC-005: Filament tenancy usa `slugAttribute` para URLs amigables sin necesitar UUID como PK).
- Toda tabla operativa con `empresa_id` (FK) y, cuando corresponda, `sede_id` (FK) — ver `.claude/rules/base-datos.md`. Confirmado con `sedes.empresa_id`, `accesos.empresa_id`/`sede_id`.
- Timestamps estándar (`created_at`, `updated_at`); `deleted_at` solo donde el soft delete tenga sentido de negocio — todavía sin usarse (Empresa/Sede no tienen soft delete: borrar una sede con `restrictOnDelete()` en la FK de sus dependientes en vez de soft delete, para MVP).
- Montos monetarios: **pendiente**, decisión al construir Fase 2 (Productos).

## Entidades implementadas (Fase 1)

### Empresa
- **Propósito**: tenant SaaS raíz.
- **Empresa/sede**: es la raíz — no depende de otra empresa.
- **Relaciones**: `hasMany(Sede)`, `hasMany(Acceso)`. Implementa `Filament\Models\Contracts\HasName` (`getFilamentName()` → `nombre`).
- **Estados**: `Enum EstadoEmpresa` (`activa`, `suspendida`, `cancelada`, `HasColor`/`HasLabel` — ver DEC-035, resuelve el enum PHP que quedaba pendiente aquí desde Fase 1).
- **Perfil (Fase "Perfil de Empresa", DEC-035)**: `nombre_comercial` (para recibos, distinto de `nombre` que sigue siendo el identificador interno/slug/tenancy), `nit`/`dv` (inmutables una vez fijados — ver `Empresa::booted()`), `regimen_tributario`, `actividad_economica_ciiu`, `direccion`, `telefono`, `whatsapp`, `email`, `logo_path` (disco `public`, directorio `logos-empresas`), `notas_internas` — todos nullable. Editable desde el panel vía `EditEmpresaProfile` (tenancy nativo de Filament), no un Resource aparte.
- **Restricciones**: `slug` único (usado como identificador de tenant en las URLs `/admin/{slug}`); `nit`/`dv` no se pueden modificar una vez tienen un valor (guardián en el modelo, no solo en el formulario).
- **Índices**: PK, único en `slug`.
- **Auditoría**: no implementada todavía (pendiente para cambios de plan/estado, no crítico en Fase 1).
- **Riesgos**: ninguno abierto — cubierto por tests de aislamiento y por `tests/Feature/PerfilEmpresaTest.php`.

### Sede
- **Propósito**: local físico de una empresa.
- **Empresa/sede**: `belongsTo(Empresa)`, FK `empresa_id` con `restrictOnDelete()` (no se puede borrar una empresa con sedes).
- **Relaciones**: `hasMany(Acceso)`. Resource Filament `SedeResource` (CRUD completo, tenant-scoped).
- **Estados**: `activa`, `inactiva`.
- **Restricciones**: único (`empresa_id`, `nombre`); validado en formulario con `scopedUnique()` (no `unique()` — ver nota de seguridad en DEC-005 sobre bypass de global scope).
- **Índices**: PK, único compuesto (`empresa_id`, `nombre`).
- **Auditoría**: no implementada todavía.
- **Riesgos**: ninguno abierto.

### Acceso (pivote Usuario–Empresa–Sede–Rol)
- **Propósito**: modela qué rol tiene un usuario en una empresa (y opcionalmente una sede concreta).
- **Empresa/sede**: `empresa_id` obligatorio; `sede_id` nullable — nulo únicamente tiene sentido con `rol = administracion_central` (acceso a toda la empresa); con cualquier otro rol, `sede_id` debe estar presente (validado a nivel de aplicación, no de constraint — ver comentario en la migración).
- **Relaciones**: `belongsTo(User)`, `belongsTo(Empresa)`, `belongsTo(Sede)`.
- **Rol** (`App\Enums\Rol`, enum PHP nativo): `administracion_central`, `administracion_sede`, `caja`, `mesero`, `area_preparacion` — mismos 5 roles de `docs/PRODUCTO.md`. Solo `administracion_central`/`administracion_sede` pueden entrar al panel Filament (`User::canAccessPanel()`); los otros tres operarán el POS táctil (Fase 4), todavía sin auth propia.
- **Estados**: N/A.
- **Restricciones**: sin `unique()` de BD por las razones explicadas en la migración (NULLs en Postgres); duplicados de `administracion_central` se evitan solo en la aplicación por ahora — riesgo de baja severidad, revisar si se vuelve un problema real.
- **Índices**: índice compuesto (`empresa_id`, `sede_id`).
- **Auditoría**: no implementada (dar de alta/baja accesos no queda auditado todavía — pendiente para cuando exista gestión de usuarios en el panel).
- **Riesgos**: el aislamiento depende de que todo código futuro use `User::sedesAccesibles()`/`esAdminCentralDe()`/`rolEnSede()` o el tenancy de Filament — un query directo sin pasar por ninguno de los dos rompería el aislamiento (ver nota de seguridad en DEC-005).

## Entidades implementadas (Fase 2)

### Categoria
- **Propósito**: agrupa productos del catálogo.
- **Empresa/sede**: pertenece a `Empresa` (catálogo compartido entre todas las sedes de la empresa — ver DEC-006, no es por sede).
- **Relaciones**: `belongsTo(Empresa)`, `hasMany(Producto)`.
- **Estados**: `activa`, `inactiva`.
- **Restricciones**: único (`empresa_id`, `nombre`), validado con `scopedUnique()` en el formulario.
- **Índices**: PK, único compuesto (`empresa_id`, `nombre`).
- **Riesgos**: ninguno abierto.

### Producto
- **Propósito**: catálogo vendible.
- **Empresa/sede**: pertenece a `Empresa` (no a `Sede` — ver DEC-006). **Sin variantes ni modificadores todavía** (ver DEC-007/riesgo de Fase 2 en `ROADMAP.md`): un producto tiene un único precio, no variantes de tamaño/sabor.
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Categoria)`.
- **Estados**: `activo`, `inactivo`, `agotado`.
- **Restricciones**: único (`empresa_id`, `nombre`); `precio` es `decimal(10,2)` (no centavos enteros — decisión tomada en Fase 2, simple y suficiente para MVP), sin validación de moneda/locale específica todavía (la tabla de Filament usa `money('usd')` como placeholder, pendiente de la ubicación real de Dulcita — ver `docs/MODULO-ACTUAL.md`).
- **Índices**: PK, único compuesto (`empresa_id`, `nombre`), índice en `categoria_id`.
- **Auditoría**: cambios de precio — **no implementada todavía**; el snapshot de precio en la venta (para que cambios de precio no afecten ventas históricas) se implementa en Fase 4 (Pedidos), cuando exista `ItemPedido`.
- **Riesgos**: ninguno abierto para el alcance actual (sin variantes).

### Mesa
- **Propósito**: unidad de atención en sala.
- **Empresa/sede**: pertenece a `Empresa` **y** `Sede` directamente (`empresa_id` + `sede_id`, ambos como columnas propias — no solo heredado vía `Sede`). `piso` y `zona` son **campos de texto libre**, no relaciones normalizadas — ver DEC-007 (reemplaza el modelo original de 3 tablas `Piso`/`Zona`/`Mesa`).
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Sede)`.
- **Estados**: `activa`/`inactiva` — es disponibilidad física, **no** el estado operativo de ocupación (libre/ocupada/reservada/en cuenta dividida), que se modelará en Fase 4 cuando exista `Pedido` y pueda depender de si hay un pedido abierto en esa mesa.
- **Restricciones**: único (`sede_id`, `nombre`) a nivel de base de datos (sin validación de formulario `scopedUnique` todavía — deliberado, ver nota en el código de `MesaForm`, para no sobre-diseñar la validación de un campo compuesto sede+nombre en esta fase).
- **Índices**: PK, único compuesto (`sede_id`, `nombre`), índice (`empresa_id`, `sede_id`).
- **Auditoría**: no implementada.
- **Riesgos**: condiciones de carrera al *ocupar* una mesa (dos meseros tomándola a la vez) — no aplica todavía porque el concepto de "ocupar" no existe hasta Fase 4 (Pedidos); revisar entonces. Aislamiento por sede dentro de la misma empresa verificado con tests (`tests/Feature/CatalogoYMesasTest.php`): el listado de Filament y la creación vía modelo (`Mesa::booted()`) rechazan sedes que el usuario autenticado no administra.

## Entidades implementadas (Fase 3)

### Caja
- **Propósito**: una sesión completa de apertura→movimientos→cierre — no un "punto de cobro" físico persistente (ver DEC-008, reemplaza el modelo `Caja` 1-N `Turno` implícito en la versión anterior de este documento).
- **Empresa/sede**: `empresa_id` **y** `sede_id` directos (mismo patrón que `Mesa`: `empresa_id` para el tenancy de Filament, `sede_id` para el filtro real).
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Sede)`, `belongsTo(User, 'usuario_apertura_id')`, `belongsTo(User, 'usuario_cierre_id')` (nullable), `hasMany(MovimientoCaja)`.
- **Estados** (`App\Enums\EstadoCaja`): `abierta`, `cerrada`. Sin "reapertura" — fuera de alcance de Fase 3.
- **Restricciones**: como mucho una `Caja` con `estado = abierta` por `sede_id` — **índice único parcial en PostgreSQL** (`cajas_una_abierta_por_sede`, `WHERE estado = 'abierta'`), no solo un chequeo de aplicación (ver DEC-008 y el riesgo de concurrencia de abajo).
- **Índices**: PK, compuesto (`sede_id`, `estado`), único parcial (`sede_id`) `WHERE estado = 'abierta'`.
- **Auditoría**: `usuario_apertura_id`/`abierta_at` y `usuario_cierre_id`/`cerrada_at` — inherentes al registro, sin tabla de auditoría separada.
- **Riesgos**: concurrencia en apertura y cierre — **resuelto**: apertura protegida por el índice único parcial (además de un `lockForUpdate()` de aplicación para dar un mensaje legible en el caso no-concurrente); cierre protegido con `lockForUpdate()` dentro de una transacción que revalida `estado` antes de aplicar el cambio (`Caja::cerrar()`), evitando que un doble clic/doble request procese el cierre dos veces. Cubierto por tests (`tests/Feature/CajaTest.php`).

### MovimientoCaja
- **Propósito**: cada ingreso/egreso de una caja durante su sesión — es el historial de auditoría, no se edita ni se borra (sin `EditAction`/`DeleteAction` en el `RelationManager`).
- **Empresa/sede**: `empresa_id` directo (heredado de la `Caja`); sede se resuelve vía `caja.sede`.
- **Relaciones**: `belongsTo(Caja)`, `belongsTo(Empresa)`, `belongsTo(User, 'usuario_id')`.
- **Tipo** (`App\Enums\TipoMovimientoCaja`): `ingreso`, `egreso`. El monto siempre es positivo; el signo lo da el tipo.
- **Restricciones**: monto > 0 (validado en formulario y, como defensa adicional, en `MovimientoCaja::booted()`); solo se puede crear si la caja sigue `abierta` (mismo doble chequeo); solo si el usuario autenticado tiene acceso a la sede de esa caja — `MovimientoCajaPolicy::create()` no puede validar esto porque Filament no le pasa la caja destino al chequeo genérico, así que vive en el modelo.
- **Índices**: PK, índice en `caja_id`.
- **Auditoría**: es en sí mismo el registro de auditoría (`usuario_id` + `created_at` por fila).
- **Riesgos**: ninguno abierto — cubierto por tests.

## Entidades implementadas (Fase 4)

### AreaPreparacion
- **Propósito**: destino configurable de comandas (cocina, barra, panadería, etc.) — gestionada en Filament (`AreaPreparacionResource`, mismo patrón que `Mesa`), no vía POS.
- **Empresa/sede**: `empresa_id` **y** `sede_id` directos (igual que `Mesa` — ver DEC-010).
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Sede)`, `hasMany(Comanda)`.
- **Estados**: `activa`, `inactiva`.
- **Restricciones**: único (`sede_id`, `nombre`).
- **Índices**: PK, único compuesto (`sede_id`, `nombre`).
- **Riesgos**: ninguno abierto.

### Pedido
- **Propósito**: registro de lo pedido — el "carrito" de una venta en curso, luego histórico.
- **Empresa/sede**: `empresa_id` y `sede_id` directos; `mesa_id` nullable (solo si `tipo = mesa`).
- **Relaciones**: `hasMany(ItemPedido)`, `hasMany(Comanda)`, `hasMany(Pago)`, `belongsTo(Mesa)` nullable, `belongsTo(User, 'usuario_id')`.
- **Tipo** (`App\Enums\TipoPedido`): `mesa`, `mostrador`, `para_llevar`, `domicilio`. **Sin pedidos por encargo** (fecha futura) — pospuesto, ver DEC-009.
- **Estados** (`App\Enums\EstadoPedido`): `abierto`, `cobrado`, `anulado` — **simplificado respecto a la propuesta original** (sin "enviado a cocina"/"servido" a este nivel, eso vive en `Comanda` — ver DEC-012).
- **Restricciones**: `idempotency_key` único, no nulo — toda creación desde el POS la exige (ver riesgo de idempotencia en `docs/ROADMAP.md` Fase 4).
- **Índices**: PK, único en `idempotency_key`, compuesto (`sede_id`, `estado`).
- **Auditoría**: `usuario_id` (quién lo abrió) inherente; `cerrado_at` marca cobro o anulación.
- **Riesgos**: doble envío de comanda por reintentos de red — **resuelto**: `enviarComanda()` solo procesa ítems con `comanda_id` nulo, así que un reintento no duplica nada (ver `ItemPedido` abajo). Doble creación de pedido por reintento — **resuelto**: `Pedido::abrir()` es idempotente por `idempotency_key`. Cubierto por tests (`tests/Feature/PosApiTest.php`).

### ItemPedido
- **Propósito**: cada línea de un pedido — producto, cantidad, precio congelado.
- **Empresa/sede**: se resuelve vía `pedido.sede`; no tiene `empresa_id`/`sede_id` propios (a diferencia de otras entidades operativas — ver nota de riesgo abajo).
- **Relaciones**: `belongsTo(Pedido)`, `belongsTo(Producto)`, `belongsTo(AreaPreparacion)`, `belongsTo(Comanda)` nullable.
- **Restricciones**: `precio_unitario` y `nombre_producto` son una **copia congelada** al momento de agregarlo — un cambio posterior de precio en `Producto` no afecta pedidos ya creados (regla confirmada en `docs/REGLAS-NEGOCIO.md`). `cantidad` ≥ 1. Solo se puede crear si el pedido sigue `abierto`; solo se puede eliminar si `comanda_id` es nulo (si ya se envió a cocina, no se quita — hay que anular la comanda, no implementado en Fase 4).
- **Índices**: PK, índice en `pedido_id`, índice en `comanda_id`.
- **Riesgo abierto**: no tiene `empresa_id` propio (inconsistente con el patrón de "toda tabla operativa" de `.claude/rules/base-datos.md`) — se aceptó así porque siempre se consulta a través de `Pedido`, nunca de forma aislada; revisar si en el futuro se necesita consultarlo directamente sin cargar el pedido.

### Comanda
- **Propósito**: instrucción de preparación enviada a un área — agrupa los `ItemPedido` pendientes de un pedido en el momento de "enviar a cocina".
- **Empresa/sede**: `empresa_id` directo; sede se resuelve vía `pedido.sede`.
- **Relaciones**: `belongsTo(Pedido)`, `belongsTo(AreaPreparacion)`, `hasMany(ItemPedido)`.
- **Estados** (`App\Enums\EstadoComanda`): `pendiente` → `en_preparacion` → `lista` → `entregada` (avance secuencial vía `Comanda::avanzar()`); `anulada` (no implementado en Fase 4 — no hay acción de UI para anular una comanda todavía).
- **Restricciones**: no se borra físicamente — sin `DeleteAction` en ningún lugar.
- **Índices**: PK, compuesto (`area_preparacion_id`, `estado`).
- **Auditoría**: no implementada explícitamente (no registra quién avanzó cada estado, solo el `estado` actual).
- **Riesgos**: pérdida de sincronía si el polling del KDS falla — **mitigado** parcialmente (el KDS reintenta cada 4s automáticamente, ver `resources/js/pos/views/KdsView.vue`), pero no hay alerta si el polling lleva mucho tiempo fallando. WebSockets quedan para Fase 8 (DEC-002).

### Pago
- **Propósito**: cada pago aplicado a un pedido — soporta pago mixto (varios `Pago` por `Pedido`, distintos medios).
- **Empresa/sede**: `empresa_id` directo; sede vía `pedido.sede`.
- **Relaciones**: `belongsTo(Pedido)`, `belongsTo(User, 'usuario_id')`.
- **Medio** (`App\Enums\MedioPago`): `efectivo`, `tarjeta`, `transferencia` — sin integración real de pasarela de pago (solo registro, ver "Fuera de alcance" en `docs/ROADMAP.md` Fase 4).
- **Restricciones**: `idempotency_key` único — un reintento con la misma clave devuelve el pago existente, no cobra dos veces (`Pago::registrar()`). El pedido pasa a `cobrado` automáticamente cuando la suma de pagos alcanza el total, dentro de la misma transacción que registra el pago.
- **Índices**: PK, único en `idempotency_key`, índice en `pedido_id`.
- **Auditoría**: `usuario_id` + `created_at` por fila.
- **Riesgos**: redondeo en pagos mixtos — **mitigado**: todos los cálculos de dinero usan `bcmath` (no floats), consistente con `Caja`/`MovimientoCaja` de Fase 3.

## Entidades implementadas (Fase 5)

### Insumo
- **Propósito**: definición de un ingrediente/materia prima (harina, café en grano, etc.) — la receta y el stock lo referencian.
- **Empresa/sede**: `empresa_id` únicamente — **por empresa**, compartido entre sedes, igual que `Categoria`/`Producto` (ver DEC-015).
- **Relaciones**: `belongsTo(Empresa)`, `hasMany(RecetaItem)`, `hasMany(Inventario)`, `hasMany(MovimientoInventario)`.
- **Estados**: `activo`, `inactivo`.
- **Restricciones**: único (`empresa_id`, `nombre`). `stock_minimo` es el umbral que activa la alerta visual (DEC-016), no bloquea nada por sí solo.
- **Índices**: PK, único compuesto (`empresa_id`, `nombre`).
- **Riesgos**: ninguno abierto.

### RecetaItem
- **Propósito**: cuánto de un insumo consume una unidad vendida de un producto — la "receta" en sí.
- **Empresa/sede**: no tiene, se resuelve vía `producto.empresa`.
- **Relaciones**: `belongsTo(Producto)`, `belongsTo(Insumo)`.
- **Restricciones**: único (`producto_id`, `insumo_id`) — un insumo no se repite en la misma receta. `producto_id` con `cascadeOnDelete()` (a diferencia del resto de FKs del proyecto, que usan `restrictOnDelete()`) — si se borra un producto, sus líneas de receta ya no tienen sentido.
- **Índices**: PK, único compuesto (`producto_id`, `insumo_id`).
- **Riesgos**: ninguno — **sin variantes/modificadores** (ver riesgo de Fase 5 en `docs/ROADMAP.md`), receta simple por producto base únicamente.

### Inventario
- **Propósito**: la existencia actual de un insumo en una sede — una fila por combinación sede+insumo, no un histórico (eso es `MovimientoInventario`).
- **Empresa/sede**: `empresa_id` **y** `sede_id` directos — **por sede** (ver DEC-015), a diferencia de `Insumo`.
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Sede)`, `belongsTo(Insumo)`.
- **Restricciones**: único (`sede_id`, `insumo_id`). Sin páginas de crear/editar en Filament — solo cambia vía `Inventario::registrarMovimiento()` (manual) o `Pedido::descontarInventario()` (automático al cobrar) — ver DEC-017.
- **Índices**: PK, único compuesto (`sede_id`, `insumo_id`).
- **Auditoría**: no en la fila misma — el histórico completo vive en `MovimientoInventario`.
- **Riesgos**: condición de carrera en `firstOrCreate()` si dos ventas del mismo insumo en la misma sede coinciden justo en su primera vez (antes de que exista la fila) — documentado en el código, no resuelto con un lock adicional (bajo impacto, ver DEC-014/017).

### MovimientoInventario
- **Propósito**: historial de cada cambio de inventario — automático (venta) o manual (entrada/salida/ajuste).
- **Empresa/sede**: `empresa_id` y `sede_id` directos; `pedido_id` nullable (solo se llena en descuentos automáticos por venta).
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Sede)`, `belongsTo(Insumo)`, `belongsTo(User, 'usuario_id')`, `belongsTo(Pedido)` nullable.
- **Tipo** (`App\Enums\TipoMovimientoInventario`): `entrada` (suma), `salida` (resta), `ajuste` (**fija** el valor absoluto — no suma ni resta, ver DEC-017).
- **Restricciones**: no se edita ni se borra — es el registro de auditoría en sí mismo (`docs/REGLAS-NEGOCIO.md`, "Inventario").
- **Índices**: PK, índice compuesto (`sede_id`, `insumo_id`).
- **Auditoría**: es en sí mismo el registro (`usuario_id` + `created_at` + `motivo` por fila).
- **Riesgos**: ninguno abierto — cubierto por tests (`tests/Feature/InventarioTest.php`), incluyendo un caso real de venta con receta descontando correctamente y quedando el movimiento registrado (criterio de terminación de Fase 5).

## Entidades implementadas (Fase 6)

### FacturaElectronica

Ver `docs/DECISIONES.md` DEC-018/DEC-019/DEC-020 y `docs/MODULO-ACTUAL.md` para el detalle completo (contrato intercambiable, proveedores, estados, disparo desde `Pago::registrar()`).

## Entidades implementadas (Fase 7)

### Proveedor
- **Propósito**: origen de las compras de insumos.
- **Empresa/sede**: `empresa_id` únicamente — por empresa, igual que `Insumo`/`Producto` (no hay proveedores distintos por sede).
- **Relaciones**: `belongsTo(Empresa)`, `hasMany(Compra)`.
- **Estados**: `activo`, `inactivo`.
- **Restricciones**: único (`empresa_id`, `nombre`). Tabla `proveedores` — nombre explícito en el modelo (`$table`), el default de Eloquent pluralizaría "Proveedor" al inglés (`proveedors`).
- **Índices**: PK, único compuesto (`empresa_id`, `nombre`).
- **Riesgos**: ninguno abierto.

### Compra
- **Propósito**: encabezado de una compra a un proveedor — se registra de una sola vez (encabezado + ítems), no en dos pasos "orden → recepción" (ver DEC-021).
- **Empresa/sede**: `empresa_id` y `sede_id` directos (la sede que recibe físicamente los insumos).
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Sede)`, `belongsTo(Proveedor)`, `belongsTo(User, 'usuario_id')`, `hasMany(CompraItem)`.
- **Restricciones**: al menos un ítem (`Compra::registrar()` rechaza un array vacío). Sin `EditAction`/`DeleteAction` — ya movió inventario real al registrarse.
- **Índices**: PK, índice compuesto (`sede_id`, `created_at`).
- **Auditoría**: `usuario_id` + `created_at`; cada ítem genera un `MovimientoInventario` de tipo `entrada` enlazado vía `compra_id`.
- **Riesgos**: ninguno abierto — cubierto por tests (`tests/Feature/ComprasYTrasladosTest.php`) y verificado con datos reales en PostgreSQL.

### CompraItem
- **Propósito**: cada línea de una compra — insumo, cantidad, costo unitario.
- **Empresa/sede**: no tiene, se resuelve vía `compra.sede`.
- **Relaciones**: `belongsTo(Compra)` (`cascadeOnDelete()` — a diferencia del resto del proyecto, que usa `restrictOnDelete()`, porque un ítem de compra no tiene sentido sin su encabezado), `belongsTo(Insumo)`.
- **Restricciones**: no se edita ni se borra después de creado (igual que `MovimientoInventario`) — el aumento de inventario ya ocurrió.
- **Índices**: PK, índice en `compra_id`.
- **Riesgos**: ninguno abierto.

### TrasladoInventario
- **Propósito**: mueve una cantidad de un insumo de una sede a otra dentro de la misma empresa — un solo paso, sin estado intermedio "en tránsito" (ver DEC-021).
- **Empresa/sede**: `empresa_id` directo; `sede_origen_id`/`sede_destino_id` (ambas `belongsTo(Sede)`, deben pertenecer a la misma empresa que el insumo — validado en `TrasladoInventario::realizar()`, no solo en el formulario).
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Insumo)`, `belongsTo(Sede, 'sede_origen_id')`, `belongsTo(Sede, 'sede_destino_id')`, `belongsTo(User, 'usuario_id')`.
- **Restricciones**: origen ≠ destino (validado en el modelo). Autorización exige acceso a **ambas** sedes, no solo a una (`TrasladoInventarioPolicy`) — distinto del resto de entidades de sede única.
- **Índices**: PK, índice en `sede_origen_id`, índice en `sede_destino_id`.
- **Auditoría**: `usuario_id` + `created_at`; genera dos `MovimientoInventario` (salida en origen, entrada en destino) enlazados vía `traslado_inventario_id`.
- **Riesgos**: concurrencia entre traslados — **cubierto**: ambas filas de `Inventario` involucradas se bloquean con `lockForUpdate()` en orden determinístico (por id ascendente) antes de mutar, evitando deadlock si dos traslados en sentido contrario entre las mismas sedes corren a la vez. Cubierto por tests.

### MovimientoInventario (ampliado en Fase 7)
- Gana `compra_id` y `traslado_inventario_id` (nullable, mismo patrón que `pedido_id` de Fase 5) para trazabilidad real al origen del movimiento, no solo texto libre en `motivo`.
- El tipo `merma` (`App\Enums\TipoMovimientoInventario::Merma`) se agregó como una variante de `salida` (resta stock igual, `TipoMovimientoInventario::resta()`) — no es una tabla nueva, reutiliza `Inventario::registrarMovimiento()` y el formulario "Registrar movimiento" de Fase 5.

## Entidades implementadas (Fase 8)

No se agregaron tablas nuevas. `App\Events\ComandaActualizada` (no persistido, solo transmitido vía WebSocket) y `App\Filament\Pages\ReporteConsolidado` (página, no un modelo — calcula sus filas en el momento desde `Pedido`/`Pago` existentes). Ver `docs/DECISIONES.md` DEC-023/DEC-024.

## Entidades implementadas (Fase 9)

### GrupoMesa
- **Propósito**: agrupa mesas físicas operadas juntas (mismo grupo de comensales) — deliberadamente NO fusiona pedidos ni comandas, cada mesa conserva el suyo intacto (ver DEC-025).
- **Empresa/sede**: `empresa_id` y `sede_id` directos.
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Sede)`, `belongsTo(Mesa, 'mesa_principal_id')`, `belongsTo(User, 'creado_por_id')`, `belongsTo(User, 'disuelto_por_id')` nullable, `hasMany(Mesa)` (vía `mesas.grupo_mesa_id`).
- **Estados**: `activo`, `disuelto`.
- **Restricciones**: `GrupoMesa::unir()` exige ≥2 mesas de la misma sede, ninguna ya unida a otro grupo activo (`lockForUpdate()` sobre las mesas dentro de una transacción). `disolver()` libera las mesas sin tocar sus pedidos.
- **Índices**: PK, compuesto (`sede_id`, `estado`).
- **Auditoría**: `creado_por_id`/`created_at` y `disuelto_por_id`/`disuelto_en` en la propia fila.
- **Riesgos**: ninguno abierto — cubierto por tests y verificado con datos reales en PostgreSQL.

### Mesa (ampliada en Fase 9)
- Gana `grupo_mesa_id` (nullable, `belongsTo(GrupoMesa)`) — null mientras opera sola.

## Entidades implementadas (Fase 10)

### SubCuenta
- **Propósito**: reparte ítems de un pedido entre varias sub-cuentas con nombre libre ("por productos"/"por personas" — mismo mecanismo) — no es una unidad de cobro ni de factura nueva, ver DEC-026.
- **Empresa/sede**: `empresa_id` directo; sede se resuelve vía `pedido.sede`.
- **Relaciones**: `belongsTo(Empresa)`, `belongsTo(Pedido)`, `belongsTo(User, 'creado_por_id')`, `hasMany(SubCuentaItem)`, `hasMany(Pago)`.
- **Restricciones**: `crear()` exige ≥1 ítem asignado, dentro de una transacción con `lockForUpdate()` sobre el pedido. Solo se puede eliminar (`eliminar()`) si no tiene pagos.
- **Índices**: PK, índice en `pedido_id`.
- **Auditoría**: `creado_por_id`/`created_at`.
- **Riesgos**: ninguno abierto — cubierto por tests y datos reales.

### SubCuentaItem
- **Propósito**: cuánto porcentaje de un `ItemPedido` pertenece a una sub-cuenta — permite repartir un mismo ítem entre varias (ej. un postre a la mitad).
- **Relaciones**: `belongsTo(SubCuenta)` (`cascadeOnDelete()`), `belongsTo(ItemPedido)`.
- **Restricciones**: único (`sub_cuenta_id`, `item_pedido_id`). La suma de `porcentaje` de un mismo `item_pedido_id` entre todas sus sub-cuentas no puede pasar de 100 — validado en `SubCuenta::crear()`, no en la base de datos.
- **Índices**: PK, único compuesto (`sub_cuenta_id`, `item_pedido_id`).
- **Riesgos**: ninguno abierto.

### Pago (ampliado en Fase 10)
- Gana `sub_cuenta_id` (nullable, `belongsTo(SubCuenta)`, `restrictOnDelete()`) — solo una etiqueta: no cambia en nada la lógica de `Pago::registrar()` (idempotencia, cierre del pedido, descuento de inventario, disparo de factura).

## Entidades post-MVP (solo mencionadas, sin diseño aún)

Cliente, Promoción — se documentarán con el mismo nivel de detalle cuando se aborden en el roadmap.
