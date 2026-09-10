# Roadmap — Sazón360

> Estado: propuesta inicial completa (repo vacío, ver DEC-000). Fases pequeñas, verificables y "vendibles" — cada una debe dejar algo demostrable. Prioridad: piloto funcional (Pastelería Dulcita, ver DEC-001) antes que features avanzadas, pero adelantando recetas/inventario básico y facturación electrónica frente a competidores que solo cubren pedidos+caja (ver criterio de priorización del prompt de arranque). Producción: VPS propio (Docker o EasyPanel), no hosting compartido — ver DEC-002 y DEC-003, que ya asumen ese destino.

## Fase 0 — Fundaciones técnicas ✅ completada (2026-09-03)
- **Objetivo**: tener el proyecto Laravel + Filament + Vue PWA corriendo localmente en Laragon con PostgreSQL.
- **Funciones**: scaffolding de Laravel, configuración de PostgreSQL, estructura de carpetas del POS (Vue), Pest instalado, CI local básico (lint + test).
- **Criterio de terminación**: `php artisan serve` y el build del POS corren sin error; una migración de prueba se aplica contra PostgreSQL; un test de ejemplo pasa con Pest. **Cumplido**: `/` (200), `/pos` (200, Vue montado), `/admin` (302 a login de Filament, esperado); migraciones `users`/`cache`/`jobs` aplicadas contra `sazon360` en PostgreSQL 18.2; `composer test` (2/2 Pest) y `composer lint` (Pint) en verde; `npm run build` genera el bundle del POS + manifest PWA.
- **Dependencias**: ninguna.
- **Riesgos**: mantener paridad entre PHP 8.3.30/Node 22 locales (Laragon) y las imágenes del VPS de producción (ver DEC-003) — el Dockerfile/imagen base del VPS **sigue pendiente**, no se definió en esta fase (quedó fuera del alcance mínimo local); definirlo antes del primer despliegue real.
- **Fuera de alcance**: cualquier módulo de negocio (cumplido — solo se creó una vista placeholder del POS, sin lógica); Dockerfile/despliegue al VPS (pendiente, no bloquea Fase 1).

## Fase 1 — Empresas, sedes, usuarios y permisos ✅ completada (2026-09-03)
- **Objetivo**: modelo base multiempresa/multisede funcionando con login y roles.
- **Funciones**: CRUD de Empresa/Sede en Filament, autenticación, roles/permisos por sede, aislamiento de datos verificado con tests.
- **Criterio de terminación**: dos empresas de prueba con datos que no se filtran entre sí, con test de aislamiento pasando. **Cumplido**: empresas `dulcita` y `demo-qa` seeded (más empresas de test en factories); `tests/Feature/AislamientoMultiempresaTest.php` (8 tests) en verde — acceso a panel por rol, aislamiento entre tenants (404 cruzado), listado de Sedes filtrado, permisos de creación/edición/borrado por rol.
- **Dependencias**: Fase 0.
- **Riesgos**: la estrategia de aislamiento se resolvió y quedó registrada como DEC-005 (base compartida + `empresa_id`, tenancy nativo de Filament).
- **Fuera de alcance**: planes de facturación SaaS complejos, tolerancia de pago (solo el modelo base) — cumplido, no se tocó.

## Fase 2 — Catálogo y mesas ✅ completada (2026-09-03)
- **Objetivo**: productos, categorías, precios, pisos/zonas/mesas configurables por sede.
- **Funciones**: CRUD de catálogo en Filament, modelo de mesas.
- **Criterio de terminación**: la sede piloto de Dulcita tiene su catálogo y su plano de mesas cargado y visible. **Cumplido**: seed con 2 categorías (Pasteles, Bebidas), 3 productos y 4 mesas en "Sede Principal" (Dulcita); `tests/Feature/CatalogoYMesasTest.php` (6 tests) en verde — aislamiento de catálogo entre tenants, permisos por rol (solo central crea/edita/borra catálogo, sede administra solo sus propias mesas), rechazo explícito al intentar crear una mesa en una sede ajena.
- **Dependencias**: Fase 1.
- **Riesgos**: variantes/modificadores de producto (tamaño, sabor) se descartaron deliberadamente de esta fase para no sobre-diseñar sin necesidad confirmada — ver DEC-006/DEC-007 en `DECISIONES.md`. Si Dulcita necesita variantes reales de producto, es un descope explícito a revisar, no algo que se asumió incluido.
- **Fuera de alcance**: recetas/inventario todavía (van en Fase 5); variantes/modificadores de producto (ver riesgo arriba); precio/disponibilidad distintos por sede (DEC-006: catálogo es por empresa, no por sede); Piso/Zona como entidades normalizadas (DEC-007: son campos de texto en Mesa).

## Fase 3 — Caja y turnos básicos ✅ completada (2026-09-03)
- **Objetivo**: apertura/cierre de caja con historial de movimientos.
- **Funciones**: apertura, cierre, movimientos manuales de caja, auditoría básica.
- **Criterio de terminación**: un turno completo de caja (apertura → movimientos → cierre) queda registrado y auditado. **Cumplido**: `Caja::abrir()`/`cerrar()` + `MovimientoCaja`, con `CajaResource` en Filament (abrir vía formulario, registrar movimientos vía `RelationManager`, cerrar vía acción dedicada con el monto esperado recalculado); seed con un turno completo ya cerrado para Dulcita; `tests/Feature/CajaTest.php` (9 tests) en verde.
- **Dependencias**: Fase 1.
- **Riesgos**: concurrencia en cierre — **cubierto**: `lockForUpdate()` + revalidación de estado dentro de una transacción (`Caja::cerrar()`), con test que verifica que un segundo cierre sobre la misma caja es rechazado. Concurrencia en apertura — **cubierto también** (no estaba explícitamente pedido, pero es el mismo riesgo): índice único parcial en PostgreSQL (`cajas_una_abierta_por_sede`), no solo un chequeo de aplicación — ver DEC-008.
- **Fuera de alcance**: control de asistencia/turnos de empleados más allá de caja (cumplido, no se tocó); reapertura de una caja cerrada; concepto de "punto de cobro" separado de "caja" (ver DEC-008); tolerancia de pago de suscripción SaaS (es de un módulo de facturación que no existe todavía, ver `docs/REGLAS-NEGOCIO.md`).

## Fase 4 — Pedidos, comandas y cobro (POS táctil mínimo) ✅ completada (2026-09-03)
- **Objetivo**: flujo completo de venta en el POS táctil: pedido → comanda por área → cobro.
- **Funciones**: creación de pedido en mesa/mostrador, envío de comanda, cobro con medios de pago, KDS con polling (DEC-002, confirmado) o impresión térmica por área.
- **Criterio de terminación**: Dulcita puede operar un turno real de ventas de principio a fin usando solo el sistema. **Cumplido de extremo a extremo**: `Pedido::abrir()` → `ItemPedido` → `enviarComanda()` → `Comanda::avanzar()` (KDS) → `Pago::registrar()` → pedido `cobrado`, verificado con 9 tests (`tests/Feature/PosApiTest.php`), con datos reales en PostgreSQL, y con verificación visual en un navegador real (Playwright headless, ver DEC-013) — login, pedido, comanda, cobro y KDS probados con capturas de pantalla, sin errores de consola. Esa verificación encontró un bug real de UX (formato de monto con coma en locale español) ya corregido.
- **Dependencias**: Fases 2 y 3.
- **Riesgos**: idempotencia de envío de comanda/cobro ante cortes de red — **cubierto**: `idempotency_key` único en `Pedido`/`Pago` (reintento devuelve el registro existente, no duplica) y `enviarComanda()` es idempotente por diseño (solo toma ítems con `comanda_id` nulo). Confirmado con el usuario: "pedidos por encargo" queda **pospuesto** (ver DEC-009).
- **Fuera de alcance**: WebSockets en tiempo real (confirmado que queda para Fase 8, ver DEC-002); domicilios avanzados (logística/despacho — "domicilio" es solo un tipo de pedido, sin más); impresión térmica por área (el roadmap la mencionaba como alternativa al KDS — se implementó KDS con polling, no impresión); división/unión de cuentas; anulación de una comanda individual (sí existe anular el pedido completo); descuentos; auditoría detallada de anulaciones (solo queda el cambio de estado, no un registro aparte de quién/cuándo/por qué más allá de los timestamps).

## Fase 5 — Recetas e inventario básico (adelantado como diferenciador) ✅ completada (2026-09-03)
- **Objetivo**: descontar inventario por receta al vender, con alertas simples de stock bajo.
- **Funciones**: insumos, recetas por producto, descuento automático de inventario al cobrar.
- **Criterio de terminación**: vender un producto con receta descuenta correctamente sus insumos y el histórico de movimientos de inventario queda registrado. **Cumplido y verificado con datos reales en PostgreSQL**: el pedido de ejemplo sembrado (2 tortas + 2 cafés) descontó automáticamente 0.300kg de harina y 36g de café en grano al cobrarse, con sus dos `MovimientoInventario` (`tipo=salida`) correctamente registrados y enlazados al pedido. Cubierto además por 6 tests (`tests/Feature/InventarioTest.php`).
- **Dependencias**: Fase 4.
- **Riesgos**: complejidad de recetas con variantes/modificadores — **mitigado**: receta simple por producto base únicamente, sin variantes (ver `RecetaItem` en `docs/MODELO-DATOS.md`).
- **Fuera de alcance**: compras a proveedores, mermas formales, traslados entre sedes (fase 7); bloqueo de venta por falta de stock (solo alerta visual, ver DEC-016); reversión de inventario si se anula un pedido ya cobrado (no aplica — un pedido cobrado no se anula en el MVP).

## Fase 6 — Facturación electrónica básica (adelantado como diferenciador) 🔶 en progreso (2026-09-03)
- **Objetivo**: emitir un comprobante fiscal válido por venta, según el país de Dulcita.
- **Funciones**: integración con proveedor/servicio de facturación electrónica intercambiable (ver DEC-019), generación de comprobante al cobrar.
- **Criterio de terminación**: una venta real de Dulcita genera un comprobante fiscal válido. **Verificado que el mecanismo funciona contra la API real (DEC-022), pero no cumplido todavía para una venta real de Dulcita** — falta el impuesto DIAN de sus productos reales (ver bloqueante abajo).
- **Dependencias**: Fase 4.
- **Riesgos**: requisitos fiscales varían por país — **resuelto**: Dulcita opera en Colombia, régimen DIAN (DEC-018). El usuario pidió explícitamente que el proveedor tecnológico sea intercambiable, mencionando Factus como candidato inicial (DEC-019).
- **Estado real**: la **arquitectura intercambiable** está hecha y probada de extremo a extremo — `App\Contracts\ProveedorFacturacionElectronica`, `FacturaElectronica` (modelo + migración), `EmitirFacturaElectronicaJob` (encolado desde `Pago::registrar()` sin bloquear el cobro), `FacturaElectronicaResource` en Filament (solo lectura + acción "Reintentar"). `FactusProveedor::emitir()` está implementado contra la documentación real de Factus (autenticación OAuth2 + `POST /v2/bills/validate`) y **verificado contra un sandbox real** con credenciales de prueba del usuario: autenticación exitosa y una factura de prueba emitida con `is_validated: true`, CUFE y número oficial reales (ver DEC-022). Esa misma verificación encontró y corrigió un bug real: Factus espera el precio SIN IVA en `items[].price`, mientras que `Producto.precio` en Sazón360 siempre fue el precio final con IVA incluido — corregido calculando la base gravable hacia atrás (`FactusProveedor::precioSinImpuesto()`). 69/69 tests en verde (11 de esta fase, incluyendo el caso de cálculo de IVA verificado contra el sandbox).
- **Bloqueante para terminar la fase de verdad** (emitir un comprobante DIAN real de una venta real de Dulcita, no de prueba): configurar `codigo_impuesto_dian`/`tasa_iva` en cada producto real del catálogo de Dulcita (dato fiscal real que debe confirmar su contador, no adivinable). Menor/no probado: `numbering_range_id` si Dulcita necesita más de un rango de numeración; confirmar el código DIAN de pago con tarjeta (`"48"`, sin confirmar contra el catálogo completo de Factus).
- **Fuera de alcance**: multi-país simultáneo.

## Fase 7 — Compras, mermas y traslados entre sedes ✅ completada (2026-09-03)
- **Objetivo**: cerrar el ciclo de inventario a nivel multisede.
- **Funciones**: proveedores, órdenes de compra, registro de mermas, traslados entre sedes.
- **Criterio de terminación**: un traslado de insumos entre dos sedes queda reflejado correctamente en el inventario de ambas. **Cumplido y verificado con datos reales en PostgreSQL**: `TrasladoInventario::realizar()` descuenta origen/aumenta destino en una transacción con `lockForUpdate()` en orden determinístico, probado con 12 tests (`tests/Feature/ComprasYTrasladosTest.php`); una compra real a un proveedor aumentó correctamente el stock de harina de Dulcita.
- **Dependencias**: Fase 5.
- **Riesgos**: consistencia de inventario durante traslados concurrentes — **cubierto**: ambas filas de `Inventario` (origen y destino) se bloquean con `lockForUpdate()` en orden determinístico por id antes de mutar, evitando deadlock entre traslados en sentido contrario (ver DEC-021).
- **Decisiones de alcance** (ver DEC-021): compra se registra en un solo paso (sin "orden pendiente → recibida"); merma es un tipo de `MovimientoInventario` (`Merma`), no una tabla nueva — reutiliza el formulario "Registrar movimiento" de Fase 5; traslado es un solo paso (sin estado "en tránsito"); autorizar un traslado exige acceso a ambas sedes (origen y destino), no solo a una.
- **Fuera de alcance**: producción/manufactura compleja; compras/traslados en dos pasos con confirmación (si se necesita en el futuro, es una ampliación real del modelo).

## Fase 8 — Tiempo real (KDS con WebSockets) y reportes centrales avanzados ✅ completada (2026-09-03)
- **Objetivo**: reemplazar el polling del KDS por WebSockets reales; reportes consolidados multisede.
- **Funciones**: Redis + WebSockets (Reverb u otro), dashboards centrales.
- **Criterio de terminación**: el KDS refleja cambios de comanda en tiempo real sin polling; un reporte consolidado cruza datos de 2+ sedes. **Cumplido y verificado con un servidor Reverb real**: dos pestañas de navegador (mesero y cocina) confirmaron que una comanda creada en una aparece en la otra sin recargar, y los 3 avances de estado se reflejan en tiempo real (Playwright, instalado temporalmente y desinstalado al terminar — ver DEC-023). El reporte consolidado (`App\Filament\Pages\ReporteConsolidado`) cruza ventas de 2+ sedes de una empresa, probado con datos reales de 2 sedes.
- **Dependencias**: Fase 4 (DEC-002 ya confirma este orden).
- **Riesgos**: bajo — el VPS de producción (Docker/EasyPanel) ya se asumió como capaz de correr Reverb al aprobar DEC-002; validar igualmente el contenedor/proceso exacto al llegar al despliegue real. **Riesgo real encontrado durante la instalación**: `laravel/reverb` (todas sus versiones) fija `guzzlehttp/psr7: ^2.6`, incompatible con `guzzle` 8.1.0 ya instalado — se resolvió permitiendo que Composer degradara `guzzle` a 7.15.5 (estable, mantenido, transparente para el código vía el facade `Http::`) — ver DEC-023.
- **De paso**: se encontró y corrigió un bug real de Fase 4 — el filtro por defecto del listado de comandas no incluía el estado `lista`, haciendo inalcanzable el botón "Entregar" del KDS en la práctica (ver DEC-023).
- **Fuera de alcance**: IA, marketplace, contabilidad completa, nómina (fuera del MVP y de este roadmap por ahora). Reportes más específicos (por producto, por empleado, exportables, programados) — el reporte consolidado cubre solo ventas por sede en un rango de fechas (ver DEC-024).

## Fase 9 — Unión de mesas ✅ completada (2026-09-04)
- **Objetivo**: permitir atender varias mesas físicas como un grupo (caso estándar de restaurante: mesas contiguas para un mismo grupo de comensales).
- **Origen**: propuesta externa del usuario (documento "Prompt Maestro" con unión/división de cuentas completas); auditada primero contra el proyecto real, y reducida deliberadamente a un diseño mucho más simple — ver DEC-025 para el razonamiento completo.
- **Funciones**: `GrupoMesa` (unir/disolver mesas de una misma sede), total combinado de referencia para dividir en partes iguales/por persona, API REST bajo `/api/pos/*`, UI en el POS táctil (Vue): botón "Unir mesas" con selección múltiple, banner de grupos activos, vista de grupo con divisor y acceso directo a cobrar cada pedido. **Sin fusión de pedidos/comandas, sin facturación por sub-cuenta, sin división por productos con reparto parcial de ítems** (diferida a una fase futura si se confirma que hace falta).
- **Criterio de terminación**: backend + UI completos y probados — **cumplido**: `GrupoMesa::unir()`/`disolver()`, 4 endpoints de API, 12 tests de backend, verificado con datos reales de Dulcita en PostgreSQL, y flujo completo verificado visualmente en navegador real con Playwright (instalado y desinstalado, mismo criterio que Fases 4/8): unir mesas, ver total combinado, dividir en partes iguales, banner persistente tras recargar, disolver — cero errores de consola.
- **Dependencias**: Fase 4 (Mesa, Pedido, Pago ya existentes).
- **Riesgos**: ninguno abierto — cubierto por tests y verificación visual real.
- **Fuera de alcance**: división "por productos" con reparto parcial de ítems entre sub-cuentas; propina; nuevos estados operativos de mesa (libre/ocupada/reservada) más allá de "unida"; bloqueo optimista (se usa el mismo patrón pesimista ya validado en el resto del proyecto).

## Fase 10 — División de cuenta "por productos"/"por personas" ✅ completada (2026-09-04)
- **Objetivo**: permitir que cada persona en una mesa pague exactamente lo que consumió (no partes iguales) — caso real reportado por el usuario, distinto de "por mesa original" (ya resuelto en Fase 9).
- **Origen**: segunda sesión del mismo documento externo del usuario, pidiendo auditar Fase 9 antes de ampliar. La auditoría confirmó que "por mesa original" ya funcionaba (pagar cada pedido del grupo por separado) y que lo único genuinamente pendiente era el reparto a nivel de ítem — ver DEC-026.
- **Funciones**: `SubCuenta`/`SubCuentaItem` (reparte ítems de un pedido, completos o por porcentaje, entre sub-cuentas con nombre libre — mismo mecanismo para "por productos" y "por personas"), `Pago.sub_cuenta_id` (etiqueta un pago existente, sin crear una unidad de cobro/factura nueva), 4 endpoints de API, UI en el POS táctil (Vue): botón "Dividir", modal con sub-cuentas existentes (cobrar/eliminar) y formulario para crear una nueva (ítems con checkbox + porcentaje disponible calculado en el cliente).
- **Criterio de terminación**: backend + UI completos y probados — **cumplido**: 9 tests de backend, verificado con datos reales de Dulcita en PostgreSQL (ítem repartido 50/50 entre dos sub-cuentas, cada una pagada con un medio distinto, pedido cerrado correctamente), y flujo completo verificado visualmente en navegador real con Playwright (instalado y desinstalado, mismo criterio que Fases 4/8/9): crear sub-cuenta, cobrarla, confirmar que queda "Pagada" y el pedido pasa a `cobrado` — cero errores de consola.
- **Dependencias**: Fase 9.
- **Riesgos**: ninguno abierto. **Bug real encontrado y corregido durante la verificación**: la comunicación padre-hijo del modal usaba `await emit(...)`, que en Vue 3 no espera al handler real del padre (`emit()` siempre resuelve de inmediato) — el formulario se limpiaba antes de confirmar éxito y los errores del backend nunca llegaban al modal. Corregido pasando los handlers como props (funciones), no como eventos — ver DEC-026.
- **Fuera de alcance**: facturación electrónica DIAN por sub-cuenta (cada pedido sigue siendo 1 factura); propina prorrateada; forzar que el 100% de los ítems quede asignado antes de poder cobrar.

## Nota de priorización

Las Fases 5 y 6 (recetas/inventario básico y facturación electrónica) se adelantaron intencionalmente frente al orden "natural" porque son el diferenciador real frente a competidores que solo cubren captura de pedidos y caja — sin romper la prioridad de tener antes a Dulcita operando como piloto funcional (Fases 0-4).
