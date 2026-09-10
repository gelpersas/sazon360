# Sazón360 — CLAUDE.md

Índice operativo corto. No repite la documentación de negocio — para eso está `docs/`. Auto Memory de Claude Code está activo y complementa este archivo con notas propias (correcciones, preferencias); no dupliques aquí lo que Auto Memory ya captura solo.

## Objetivo del producto

POS SaaS táctil multiempresa/multisede para cafeterías y restaurantes (empresas → sedes → cajas, mesas, comandas por área, KDS, inventario, facturación electrónica). Ver `docs/PRODUCTO.md`. Proyecto distinto e independiente de "Dulcita POS" (ver `docs/DECISIONES.md` DEC-000). Cliente piloto: **Pastelería Dulcita** (DEC-001). Producción: VPS propio con Docker/EasyPanel (ver DEC-002, DEC-003).

## Estado actual

**Fases 0-10 completadas** (base técnica, Empresas/Sedes/Usuarios, Catálogo/Mesas, Caja, Pedidos/comandas/cobro del POS, Recetas/inventario, Compras/mermas/traslados, KDS en tiempo real + reporte consolidado, unión de mesas `GrupoMesa`, división de cuenta `SubCuenta`) — detalle completo de cada una en `docs/DECISIONES.md` (DEC-005 a DEC-026). **Fase 6 (facturación electrónica) en progreso**: arquitectura intercambiable probada de extremo a extremo, `FactusProveedor` **verificado contra un sandbox real de Factus** (DEC-022). Dulcita opera en Colombia/DIAN (DEC-018). **Bloqueante para facturar ventas reales**: configurar el impuesto DIAN de cada producto real del catálogo (dato fiscal, no adivinable — DEC-019/020/022); `.env` tiene `FACTURACION_PROVEEDOR=factus` activo, así que hasta configurar ese impuesto cualquier venta real generará una factura `rechazada` (no bloquea el cobro). **Fases 11/12/13 completadas** (UX del POS + Caja en el POS, DEC-027 a DEC-034): sidebar de íconos, carrito fijo + catálogo con tabs, calculadora de vuelto, +/- de cantidad y nota por ítem, y `Caja`/`MovimientoCaja` (antes solo en Filament) ya accesible desde el POS táctil — nota: los pagos en efectivo no generan `MovimientoCaja` automáticamente, sigue siendo anotación manual. **Perfil de Empresa completado** (DEC-035): `EditEmpresaProfile` (tenancy nativo de Filament) ampliado con NIT/DV (inmutables una vez fijados)/régimen/CIIU/contacto/logo — sin conectar todavía con Factus (no lo necesita: el emisor ya está registrado en la cuenta de Factus del `.env`). **Auditoría general completada** (DEC-036 a DEC-041): cobro directo desde "Mesas unidas" + botón "Volver" agrandado; `AccesoResource` nuevo para crear usuarios y asignar sede/rol desde el panel (sin un `UserResource` propio porque `User` no tiene `empresa_id` y no hereda el scoping de tenancy); navegación de Filament agrupada en 5 categorías con íconos distintos por Resource; `AUDITORIA.md` generado (auditoría completa de solo lectura) con sus hallazgos accionables corregidos (DEC-039 — ver la tabla de estado al inicio del archivo); "Sedes" oculto del menú solo para administración de sede (DEC-040); **crear/editar en los 12 Resources del panel ahora son modal, no páginas aparte, con refresco automático de la tabla** (DEC-041 — mecanismo nativo de Filament, sin tocar formularios/tablas/Policies; Cajas/Compras preservan su lógica de negocio personalizada vía `CreateAction::using()`). Pendientes de fondo bloqueados esperando al usuario: Fase 6 (datos fiscales DIAN reales) e infraestructura de despliegue (conversación de cPanel en curso — pendiente de confirmar si el plan ofrece PostgreSQL/SSH). Ver `docs/ROADMAP.md` y `docs/MODULO-ACTUAL.md`.

## Tecnologías (verificadas — ya instaladas y en uso)

- Backend: Laravel 13.30.1, PHP 8.3.30 (`composer.json` fija `^8.3` — ver DEC-003).
- Panel admin: Filament 4.12.8, panel en `/admin` (`app/Providers/Filament/AdminPanelProvider.php`).
- POS táctil: Vue 3 + `vite-plugin-pwa`, en `resources/js/pos/` (segundo entry point de Vite, no un paquete separado — ver DEC-004), servido en `GET /pos/{cualquiera?}` (vue-router maneja las subrutas del lado del cliente). Login propio vía Laravel Sanctum SPA (`/api/pos/*`, DEC-011) — separado de Filament. Usa Pinia + vue-router + axios (agregados en Fase 4).
- Base de datos: PostgreSQL 18.2 en Laragon, base `sazon360` (puerto 5432, usuario `postgres`, auth `trust` local). Migraciones base (`users`, `cache`, `jobs`) aplicadas.
- Tiempo real: Laravel Reverb (WebSockets) en uso desde Fase 8 — KDS sin polling (ver DEC-002, DEC-023). Requiere `php artisan reverb:start` corriendo como proceso persistente (puerto 8080 local, no arranca solo). `guzzlehttp/guzzle` quedó en 7.15.5 (bajado de 8.1.0) por incompatibilidad de dependencias con Reverb — transparente para el código. Redis 5.0.14.1 instalado, sin usarse todavía (Reverb corre standalone sin escalado).
- Colas: Laravel queues, driver `database` en local (`config/queue.php`) — en uso desde Fase 6 (`EmitirFacturaElectronicaJob`, requiere `php artisan queue:work` para procesarse). El tiempo real del KDS NO usa colas (`ShouldBroadcastNow`, sincrónico).
- Pruebas: Pest 4.7.8, 98/98 tests en verde (aislamiento multiempresa/multisede, permisos por rol, concurrencia de caja, idempotencia de pedidos/pagos del POS, descuento de inventario por receta, emisión de factura electrónica incluyendo el cálculo de IVA verificado contra el sandbox real de Factus, compras/mermas/traslados entre sedes, WebSockets/reporte consolidado, unión de mesas, división de cuenta por sub-cuenta).
- Multiempresa/multisede: `Empresa`/`Sede`/`Acceso`/`Rol` (Fase 1, DEC-005) + `Categoria`/`Producto`/`Mesa` (Fase 2, DEC-006/DEC-007) + `Caja`/`MovimientoCaja` (Fase 3, DEC-008) + `AreaPreparacion`/`Pedido`/`ItemPedido`/`Comanda`/`Pago` (Fase 4, DEC-009 a DEC-012) + `Insumo`/`RecetaItem`/`Inventario`/`MovimientoInventario` (Fase 5, DEC-014 a DEC-017) + `FacturaElectronica` (Fase 6, DEC-018/DEC-019) + `Proveedor`/`Compra`/`CompraItem`/`TrasladoInventario` (Fase 7, DEC-021) + `GrupoMesa` (Fase 9, DEC-025) + `SubCuenta`/`SubCuentaItem` (Fase 10, DEC-026). Seed local: empresas `dulcita` (piloto, con catálogo, 4 mesas, un turno de caja cerrado, insumos/receta e inventario inicial, y un pedido completo cobrado que ya descontó inventario) y `demo-qa` (solo test de aislamiento) — usuarios admin `admin@dulcita.test`/`sede@dulcita.test`, y operativos del POS `caja@dulcita.test`/`mesero@dulcita.test`/`cocina@dulcita.test` (password `password` en todos, dev local únicamente).
- Lint/formato: Laravel Pint (`composer lint` / `composer format`).
- Control de versiones: Git 2.51.0 disponible; repositorio **aún sin `git init`** (pendiente, no bloquea el trabajo).
- Claude Code: extensión 2.1.259 (soporta skills como slash-commands, requiere ≥2.1.1).

## Comandos confirmados

Ejecutar siempre con PHP/Node de Laragon en PATH (`C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64`, `C:\laragon\bin\nodejs\node-v22`) o desde una terminal de Laragon que ya los tenga activos:

- Instalar deps PHP: `composer install`.
- Instalar deps JS: `npm install`.
- Levantar el servidor: `php artisan serve`.
- Tiempo real (KDS): `php artisan reverb:start` (proceso aparte, puerto 8080 — sin esto el KDS no recibe actualizaciones en vivo, ver Fase 8).
- Compilar frontend (dev): `npm run dev`. Producción: `npm run build` (necesario tras cambiar variables `VITE_*` en `.env`, Vite las lee solo en build).
- Correr tests: `composer test` (equivalente a `php artisan test`, Pest).
- Lint: `composer lint`. Autoformatear: `composer format` (Pint).
- Migrar: `php artisan migrate` (nunca `migrate:fresh`/`migrate:reset`/`db:wipe` — bloqueados en `.claude/settings.json`).

## Organización del proyecto (propuesta)

Monolito modular Laravel con Filament (admin) y una app Vue PWA separada (POS táctil) consumiendo la misma API. Ver `docs/ARQUITECTURA.md` para el diagrama y el detalle de componentes.

## Principios de arquitectura

- Monolito modular, sin microservicios salvo necesidad demostrada.
- Separar dominio (reglas puras) / aplicación (casos de uso) / infraestructura (HTTP, Filament, jobs, impresión).
- Filament solo para administración, nunca como sustituto del POS táctil.
- KDS con estaciones configurables por sede, nunca hardcodeadas.

## Reglas multiempresa / multisede

- Toda entidad operativa debe estar asociada a una empresa cuando corresponda.
- Ventas, cajas, mesas, inventarios y empleados deben estar asociados a una sede.
- Nunca confiar únicamente en filtros enviados desde el frontend para aislar empresas o sedes — el scoping real va en el backend.
- Los permisos deben validarse en el backend.

## Reglas de negocio clave (ver `docs/REGLAS-NEGOCIO.md` para el detalle completo)

- Los precios históricos de una venta no cambian si cambia el precio del producto.
- Una comanda enviada no se elimina físicamente; se anula con trazabilidad.
- Descuentos, anulaciones y reaperturas requieren permisos y auditoría.
- Los movimientos de caja e inventario mantienen historial.
- Los procesos críticos usan transacciones de base de datos.
- Las operaciones repetidas por pérdida de conexión deben ser idempotentes.

## Flujo obligatorio antes de modificar código

1. Lee este archivo y `docs/MODULO-ACTUAL.md`.
2. Usa `/crear-modulo` para trabajar un módulo funcional, `/investigar-error` para un bug, `/revisar-modulo` para auditar sin modificar, `/migracion-segura` antes de aplicar una migración sobre datos existentes.
3. Trabaja sobre **una sola función/módulo a la vez** — no mezcles cambios de módulos distintos en la misma tarea.
4. No modifiques archivos que no estén relacionados con la tarea actual.
5. Al terminar, usa `/cerrar-tarea`.

## Estándares mínimos de seguridad y pruebas

Ver `.claude/rules/seguridad.md` (siempre cargada) y `.claude/rules/testing.md`. Mínimo no negociable: autorización en backend, aislamiento multiempresa/sede probado, transacciones en procesos críticos, sin secretos en código ni logs.

## Documentación por tarea

| Si vas a... | Consulta |
|---|---|
| Entender el producto/MVP | `docs/PRODUCTO.md` |
| Diseñar o ubicar un componente | `docs/ARQUITECTURA.md` |
| Confirmar una regla de negocio | `docs/REGLAS-NEGOCIO.md` |
| Diseñar una entidad/migración | `docs/MODELO-DATOS.md` |
| Ver decisiones tomadas o pendientes | `docs/DECISIONES.md` |
| Ver prioridad/fase | `docs/ROADMAP.md` |
| Retomar el trabajo en curso | `docs/MODULO-ACTUAL.md` |
| Aclarar un término | `docs/GLOSARIO.md` |

## Acciones prohibidas

Las prohibiciones críticas (`git push`, editar/leer `.env`, comandos destructivos de BD) están bloqueadas en `.claude/settings.json` (`permissions.deny`) — esto es el control real. Lo de aquí es referencia legible, no el único mecanismo:

- No ejecutar `git push`.
- No editar `.env` ni exponer secretos.
- No ejecutar comandos destructivos (`rm -rf`, `migrate:fresh`, `migrate:reset`, `db:wipe`) sin autorización explícita.
- No borrar migraciones que pudieran haberse ejecutado.
- No crear funciones futuras fuera del alcance del módulo actual.
- No agregar dependencias sin explicar su necesidad.
- No afirmar que una tarea funciona sin haber ejecutado las pruebas relacionadas.
- No hacer refactorización general no solicitada.

## Mantener `docs/MODULO-ACTUAL.md` actualizado

Actualízalo al terminar cada sesión o tarea importante (lo hace `/cerrar-tarea`): módulo actual, alcance, trabajo terminado/pendiente, pruebas ejecutadas, errores conocidos, decisiones pendientes, próxima acción exacta.

## Eficiencia

- Empieza por este archivo y `docs/MODULO-ACTUAL.md`, no releas todo el repo.
- Usa búsquedas específicas antes de abrir archivos completos; no leas `vendor/`, `node_modules/`, cachés ni builds.
- Ejecuta pruebas relacionadas antes que toda la suite.
- Resume logs extensos, no los pegues completos.
- Usa subagentes solo para investigaciones grandes o revisión aislada, nunca para tareas pequeñas ni duplicando el mismo análisis.
- Recomienda `/compact` si el contexto crece mucho, y sugiere sesión nueva al cambiar completamente de módulo.
