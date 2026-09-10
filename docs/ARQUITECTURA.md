# Arquitectura — Sazón360

> Estado: base técnica (Fase 0 de `docs/ROADMAP.md`) ya scaffoldeada — Laravel 13 + Filament 4 + POS Vue + PostgreSQL corriendo local en Laragon (ver tabla "Qué ya existe vs. qué es propuesta" al final). Los módulos de negocio (empresas, catálogo, pedidos, etc.) siguen siendo propuesta, sin implementar.

## Enfoque general

**Monolito modular** (Laravel) con un panel administrativo (Filament) y un POS táctil separado como PWA (Vue.js) que consume una API del mismo backend. No se plantean microservicios para el MVP — se evalúa solo si un componente concreto (p. ej. impresión) demuestra necesitar aislarse.

```
┌─────────────────────────────┐      ┌──────────────────────────┐
│   Panel admin (Filament)    │      │   POS táctil (Vue PWA)   │
│   gestión central/sede      │      │   ventas, mesas, cocina  │
└──────────────┬───────────────┘      └─────────────┬─────────────┘
               │                                     │
               └───────────────┬─────────────────────┘
                                │  API (Laravel, misma app)
                    ┌───────────▼────────────┐
                    │  Laravel (monolito)     │
                    │  dominio + aplicación   │
                    └──────┬───────────┬──────┘
                            │           │
                  ┌─────────▼──┐   ┌────▼────────┐
                  │ PostgreSQL │   │ Redis        │
                  │ (datos)    │   │ (colas, RT)  │
                  └────────────┘   └──────┬───────┘
                                            │
                                  ┌─────────▼─────────┐
                                  │ WebSockets (KDS,  │
                                  │ notificaciones)   │
                                  └────────────────────┘

  Agente local de impresión (Windows) ── red ── impresoras térmicas
```

## Multiempresa / multisede

- Cada `empresa` (tenant) agrupa una o más `sedes`.
- **Implementado en Fase 1** (ver [DECISIONES.md](DECISIONES.md) DEC-005): base de datos compartida, columna `empresa_id` (y `sede_id` donde aplica). El panel Filament usa su tenancy nativo (`->tenant(Empresa::class)`, URL `/admin/{empresa:slug}`), que aplica un global scope automático a cualquier modelo con relación `empresa()`. El acceso de cada usuario a empresas/sedes se modela con la tabla `accesos` (`user_id`, `empresa_id`, `sede_id` nullable, `rol` — ver `App\Enums\Rol`); `sede_id` nulo + rol `administracion_central` = acceso a toda la empresa.
- El aislamiento **no depende únicamente** del scoping automático de Filament: `App\Models\User` expone `sedesAccesibles()`, `esAdminCentralDe()`, `rolEnSede()` para cualquier código (jobs, API, POS) que necesite verificar acceso fuera del contexto de una request de Filament, donde el global scope no está activo. Ver [.claude/rules/seguridad.md](../.claude/rules/seguridad.md) y [.claude/rules/base-datos.md](../.claude/rules/base-datos.md).
- Probado con `tests/Feature/AislamientoMultiempresaTest.php`: acceso al panel sin rol admin (403), acceso a tenant ajeno (404), listado de Sedes filtrado por tenant, permisos de creación/edición/borrado de Sede por rol.

## Componentes

- **API backend (Laravel)**: **implementada desde Fase 4** bajo `/api/pos/*` (`routes/pos.php`), autenticada con Laravel Sanctum en modo SPA (cookie de sesión, ver DEC-011) — separada del panel Filament, que usa su propia sesión. Dominio de negocio en los modelos (`Pedido`, `Caja`, etc.), autorización vía Policies + helpers de `User`, controladores en `app/Http/Controllers/Pos/`.
- **Panel administrativo (Filament)**: solo para administración (empresas, sedes, catálogo, usuarios, reportes, áreas de preparación) — no para el flujo de venta táctil. Ver [.claude/rules/filament.md](../.claude/rules/filament.md).
- **POS táctil (Vue PWA)**: **implementado desde Fase 4** en `resources/js/pos/` — login propio (Sanctum, no Filament), selector de sede, toma de pedidos (catálogo → carrito → enviar a cocina → cobrar), vista de KDS **en tiempo real vía WebSockets desde Fase 8** (`resources/js/pos/echo.js`, sin polling). Usa Pinia (estado) y vue-router (navegación cliente; `routes/web.php` sirve la misma vista Blade para todas las rutas `/pos/*`). Reintento automático simple ante corte de red en creación de pedido/pago/envío de comanda (ver `resources/js/pos/stores/pedido.js`, método `conReintento`) apoyado en que esas operaciones son idempotentes (DEC-009). Ver [.claude/rules/frontend-pos.md](../.claude/rules/frontend-pos.md).
- **WebSockets**: **en uso desde Fase 8** — Laravel Reverb (confirmado en DEC-002, instalado en DEC-023). `App\Events\ComandaActualizada` (`ShouldBroadcastNow`) notifica cambios de comanda por un canal privado por sede (`routes/channels.php`, `sede.{id}.comandas`), autorizado con el mismo `User::rolEnSede()` que el resto del POS. Requiere `php artisan reverb:start` corriendo (proceso propio, puerto 8080 en local) además del servidor de Laravel.
- **Redis**: colas, cache, y backend de broadcasting cuando se active a escala — Reverb corre standalone en local sin Redis todavía (`REVERB_SCALING_ENABLED=false`); activar Redis para escalar Reverb horizontalmente es una decisión de producción, no tomada todavía.
- **KDS con estaciones configurables**: implementado — `AreaPreparacion` se define por sede desde Filament, nunca hardcodeada en código; una sede puede tener una sola estación o varias.
- **Agente local de impresión**: proceso en Windows que recibe trabajos de impresión y los envía a impresoras térmicas en red del local. Diseño concreto (servicio, protocolo de comunicación con el backend) pendiente de definir cuando se aborde el módulo de impresión.
- **Colas y trabajos en segundo plano**: Laravel queues — **en uso desde Fase 6** (`EmitirFacturaElectronicaJob`, driver `database` en local; cambiar a Redis en producción es solo configuración, no requiere tocar el Job) para impresión, notificaciones, reportes pesados y cualquier proceso que no deba bloquear la respuesta al usuario.
- **Auditoría**: registro de acciones sensibles (anulaciones, descuentos, reaperturas, cambios de precio, movimientos de caja/inventario) — mecanismo concreto (tabla propia vs. paquete de audit log) pendiente de elegir al construir el primer módulo que lo requiera.
- **Copias de seguridad**: estrategia de backup de PostgreSQL en producción — pendiente de definir junto con la infraestructura de VPS/Docker/EasyPanel.

## Manejo de pérdida temporal de internet

El POS táctil debe seguir permitiendo operar localmente durante cortes breves (ver reglas de idempotencia en `.claude/rules/frontend-pos.md`), reintentando el envío de operaciones (pedidos, comandas, cobros) sin duplicarlas al reconectar. El diseño concreto de la cola local (IndexedDB, service worker, etc.) se define al construir el módulo POS, no antes.

## Separación de capas

- **Dominio**: reglas de negocio puras (cálculo de totales, validaciones de estado de pedido/comanda/caja) — sin dependencias de framework donde sea razonable.
- **Aplicación**: casos de uso / servicios que orquestan dominio + persistencia + eventos.
- **Infraestructura**: controladores, Filament Resources, jobs, impresión, WebSockets, PostgreSQL.

No se busca Clean Architecture estricta ni hexagonal completa — solo evitar que la lógica de negocio crítica (precios, caja, inventario) viva dentro de controladores o Resources de Filament.

## Qué ya existe vs. qué es propuesta

| Elemento | Estado |
|---|---|
| Laragon con PHP 8.1–8.4, PostgreSQL 18.2, Redis 5.0.14.1, Node instalado | Verificado en el entorno local (ver DEC-003) |
| Proyecto Laravel 13 (PHP 8.3.30) en `c:\laragon\www\Sazon360`, migraciones base (`users`, `cache`, `jobs`) aplicadas contra PostgreSQL (`sazon360`) | Hecho — Fase 0 (`docs/ROADMAP.md`) |
| Panel Filament 4 instalado (`/admin`, `AdminPanelProvider`) | Hecho — Fase 0, sin Resources de negocio todavía |
| POS táctil (Vue 3 + `vite-plugin-pwa`) en `resources/js/pos/`, montado en `GET /pos` | Fase 0: placeholder (DEC-004). Fase 4: app real (login, toma de pedidos, KDS) — ver fila de Fase 4 abajo |
| Pest instalado y en verde (2 tests de ejemplo), Pint como linter (`composer lint`/`composer format`) | Hecho — Fase 0 |
| Empresas, Sedes, Usuarios/roles/permisos (`Empresa`, `Sede`, `Acceso`, `Rol`), tenancy de Filament (registro/perfil de empresa como tenant, `SedeResource` con CRUD completo) | Hecho — Fase 1 |
| Catálogo (`Categoria`, `Producto` — por empresa, sin variantes) y mesas (`Mesa` — por sede, piso/zona como texto libre), con `CategoriaResource`/`ProductoResource`/`MesaResource` en Filament | Hecho — Fase 2 (ver DEC-006, DEC-007) |
| Caja (`Caja`, `MovimientoCaja` — apertura/movimientos/cierre, índice único parcial en Postgres contra doble apertura, `lockForUpdate()` contra doble cierre concurrente), `CajaResource` con acción "Cerrar caja" y `RelationManager` de movimientos | Hecho — Fase 3 (ver DEC-008) |
| Pedidos, ítems, comandas, pagos (`Pedido`, `ItemPedido`, `Comanda`, `AreaPreparacion`, `Pago`), API REST bajo `/api/pos/*` con Laravel Sanctum (SPA), POS Vue funcional (login, toma de pedidos, KDS) | Hecho — Fase 4 (ver DEC-009 a DEC-012). Verificado visualmente en navegador real con Playwright (DEC-013). KDS pasó de polling a WebSockets en Fase 8 (DEC-023) |
| Recetas e inventario básico (`Insumo`, `RecetaItem`, `Inventario`, `MovimientoInventario` — descuento automático al cobrar, alertas de stock bajo), `InsumoResource`/`InventarioResource` en Filament | Hecho — Fase 5 (ver DEC-014 a DEC-017) |
| Facturación electrónica (`FacturaElectronica`, contrato `ProveedorFacturacionElectronica`, `EmitirFacturaElectronicaJob` en cola, `FacturaElectronicaResource` en Filament con acción "Reintentar") | Arquitectura intercambiable hecha y probada de extremo a extremo — Fase 6 (DEC-018/DEC-019). `FactusProveedor` **verificado contra un sandbox real de Factus** (autenticación + emisión con CUFE/número oficial reales, DEC-022) — falta el impuesto DIAN de los productos reales de Dulcita para facturar sus ventas reales (no las de prueba) |
| Compras, mermas y traslados entre sedes (`Proveedor`, `Compra`, `CompraItem`, `TrasladoInventario`), `ProveedorResource`/`CompraResource`/`TrasladoInventarioResource` en Filament | Hecho — Fase 7 (ver DEC-021) |
| WebSockets (Reverb) para KDS en tiempo real, reporte consolidado multisede (`App\Filament\Pages\ReporteConsolidado`) | Hecho — Fase 8 (ver DEC-023, DEC-024). Verificado con un servidor Reverb real y Playwright |
| Colas en producción con Redis, agente de impresión, auditoría, backups | Propuesta — sin implementar |
