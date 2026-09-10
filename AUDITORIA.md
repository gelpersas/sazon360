# Auditoría del proyecto Sazón360

> Documento de estado — **no propone cambios**, solo documenta lo que existe hoy en el código, verificado por lectura directa (sin ejecutar la app, sin correr análisis estático como phpstan/psalm, sin `composer audit`/`npm audit`). Donde algo no se pudo verificar desde el código, se dice explícitamente en vez de asumir.
>
> Generado: 2026-09-04.

## Estado de los hallazgos (actualizado 2026-09-07, ver `docs/DECISIONES.md` DEC-039)

El cuerpo del documento abajo queda como el **registro histórico** de la auditoría original — no se reescribió. Esto es lo que pasó con cada hallazgo de la sección 5/6 desde entonces:

| Hallazgo | Resultado |
|---|---|
| ROTO — Mesas "Eliminar seleccionados" sin `BorradoSeguro` | **Corregido** — envuelto igual que el resto de los Resources. |
| ROTO — Productos "Eliminar seleccionados" sin `BorradoSeguro` | **Corregido** — mismo arreglo. |
| A_MEDIAS — Inventario "Registrar movimiento" sin `InventarioPolicy` conectada | **Corregido** — `->visible()` ahora llama a la Policy. |
| DUPLICADO — Traslado "Nuevo traslado" con autorización propia en vez de `TrasladoInventarioPolicy` | **Corregido** — se agregó el gate de la Policy sin quitar la revalidación específica de las 2 sedes elegidas (esa sigue siendo necesaria, no es redundante). |
| Comentario muerto en `User.php` | **Corregido** — eliminado. |
| `accesos` sin unique constraint real | **Corregido** — migración nueva, verificado 0 duplicados antes de aplicar. |
| `GrupoMesa`/`SubCuenta` sin Policy propia | **Revisado y no corregido a propósito** — al investigar más a fondo se confirmó que `PedidoPolicy` (que sí existe) tampoco se invoca nunca desde `PedidoController`, y toda la capa de controladores del POS (Pedido/Comanda/Pago/GrupoMesa/SubCuenta) usa el mismo patrón deliberado de `rolEnSede()` inline. Agregar Policies nuevas para GrupoMesa/SubCuenta las habría dejado igual de "no conectadas" que `PedidoPolicy` — no era un hallazgo distinto, era el mismo patrón ya documentado. Se dejó un comentario explícito en ambos controladores en vez de código nuevo sin usar. |
| `estado` como string libre en 7 modelos | **No abordado** — refactor grande (7 modelos × Enum nuevo × migraciones de cast × formularios de Filament), no es algo "roto" hoy, se dejó fuera de esta ronda de arreglos. |
| `MovimientoCajaPolicy` no referenciada | **No abordado** — confirmado como decisión deliberada (ver DEC-034), no un descuido. |
| `EmpresaPolicy::create()` sin condición | **No abordado** — confirmado como decisión deliberada (autoregistro SaaS). |

---

## 1. Estructura general

### Árbol de carpetas relevante

```
app/
├── Contracts/                         # ProveedorFacturacionElectronica (interfaz)
├── Enums/                             # 10 enums: EstadoCaja, EstadoComanda, EstadoEmpresa,
│                                       #   EstadoFactura, EstadoGrupoMesa, EstadoPedido,
│                                       #   MedioPago, Rol, TipoMovimientoCaja,
│                                       #   TipoMovimientoInventario, TipoPedido
├── Events/                            # ComandaActualizada.php (broadcast KDS)
├── Filament/
│   ├── Pages/
│   │   ├── ReporteConsolidado.php     # página raíz, fuera de cualquier Resource
│   │   └── Tenancy/
│   │       ├── EditEmpresaProfile.php
│   │       └── RegisterEmpresa.php
│   ├── Resources/                     # 12 Resources (ver detalle sección 4)
│   │   ├── Accesos/  AreaPreparacions/  Cajas/  Categorias/  Compras/
│   │   ├── FacturaElectronicas/  Insumos/  Inventarios/  Mesas/
│   │   └── Productos/  Proveedores/  Sedes/  TrasladoInventarios/
│   │       └── (cada uno: *Resource.php, Pages/, Schemas/, Tables/, a veces RelationManagers/)
│   └── Support/BorradoSeguro.php      # helper reutilizado para bulk-delete seguro
├── Http/
│   ├── Controllers/Pos/               # 7 controladores del API del POS táctil
│   │   ├── AuthController, CajaController, ComandaController,
│   │   ├── GrupoMesaController, PedidoController, ReferenciaController,
│   │   └── SubCuentaController
│   └── Requests/Pos/                  # 8 Form Requests
├── Jobs/EmitirFacturaElectronicaJob.php
├── Models/                            # 26 modelos (ver sección 2)
├── Policies/                          # 16 Policies + Concerns/ (trait compartido)
├── Providers/{AppServiceProvider,Filament/AdminPanelProvider}.php
└── Services/Facturacion/              # FactusProveedor, NuloProveedor, ResultadoEmisionFactura

routes/
├── api.php        → incluye routes/pos.php bajo prefijo pos/
├── channels.php    → autorización de canal WebSocket (Reverb)
├── console.php
├── pos.php         → toda la API REST del POS táctil (auth:sanctum)
└── web.php         → "/" (welcome) + catch-all "/pos/{cualquiera?}" (SPA Vue)

database/migrations/   → 34 archivos (detalle sección 2)

resources/js/pos/       # PWA Vue 3 aparte del panel Filament
├── App.vue, main.js, router.js, api.js, echo.js, idempotency.js
├── components/  AppShell.vue, DividirCuentaModal.vue, PagoModal.vue
├── stores/      auth.js, caja.js, grupoMesa.js, pedido.js, subCuenta.js
└── views/       CajaView, GrupoMesaView, KdsView, LoginView, PosView, SedeSelectView (.vue)
```

**Nota de alcance**: no existe `app/Livewire/` — este proyecto no usa componentes Livewire independientes. Toda acción interactiva del panel administrativo vive dentro de `app/Filament/**` usando el propio sistema de Actions de Filament 4. El POS táctil (mesero/cajero) es una **PWA Vue 3 separada**, no Filament/Livewire — se audita su estructura aquí pero sus botones no entran en la tabla de la sección 5 (que el usuario pidió explícitamente en términos de Resources/Livewire).

### Stack detectado (versiones reales, de `composer.lock`/`package.json`, no supuestas)

| Paquete | Versión declarada | Versión instalada real |
|---|---|---|
| PHP | `^8.3` | — |
| `laravel/framework` | `^13.17` | **v13.30.1** |
| `filament/filament` | `^4.0` | **v4.12.8** |
| `livewire/livewire` | (dependencia de Filament) | **v3.8.7** |
| `laravel/reverb` | `^1.11` | — |
| `laravel/sanctum` | `^4.3` | — |
| `guzzlehttp/guzzle` | (dependencia indirecta) | **7.15.5** (bajado deliberadamente de 8.x por conflicto con Reverb — documentado en `docs/DECISIONES.md`) |
| `pestphp/pest` | `^4.7` | — |
| `laravel/pint` | `^1.27` | — |
| Node | — | v20.18.0 (entorno de esta auditoría) |
| `vue` | `^3.5.42` | — |
| `vue-router` | `^5.3.1` | — (versión de vue-router compatible con Vue 3, no es la v5 "vieja" de Vue 2) |
| `pinia` | `^4.0.3` | — |
| `tailwindcss` | `^4.0.0` | — |
| `vite` | `^8.0.0` | — |
| `laravel-echo` / `pusher-js` | `^2.4.0` / `^8.6.0` | — (usados para consumir Reverb) |

No se detectó `predis/predis` en `composer.lock` — Redis (mencionado como parte del stack en el prompt de esta auditoría) **no es una dependencia real del proyecto hoy**; según la documentación interna (`docs/`) Redis está instalado a nivel de sistema operativo pero Reverb corre standalone sin usarlo. Esto se reporta porque el prompt original asumía "Redis + WebSockets" como stack de tiempo real — la realidad verificada es "Reverb standalone, sin Redis".

No se encontró ninguna dependencia con una versión mayor claramente obsoleta (ej. no hay Vue 2, no hay Laravel &lt;10, etc.). No se ejecutó `composer audit`/`npm audit`, por lo que no hay información sobre CVEs conocidos — solo se verificó obsolescencia de versión mayor por lectura de los archivos de lock.

---

## 2. Modelos y base de datos

### Todos los modelos Eloquent y sus relaciones

26 archivos en `app/Models/*.php`. Todos usan el atributo `#[Fillable(...)]` (no `protected $fillable` clásico); ninguno usa `$guarded`.

| Modelo | Relaciones | Cast de `estado`/enums | `booted()` |
|---|---|---|---|
| `Acceso` | `user()` BT, `empresa()` BT, `sede()` BT | `rol → Rol` (enum) | `saving`: bloquea asignar `AdministracionCentral` salvo que quien guarda ya sea admin central de esa empresa, **excepto** el primer acceso de una empresa recién creada (autoregistro) |
| `AreaPreparacion` | `empresa()`, `sede()`, `comandas()` HM | `estado` string plano (sin Enum) | — |
| `Caja` | `empresa()`, `sede()`, `usuarioApertura()`/`usuarioCierre()` BT User, `movimientos()` HM | `estado → EstadoCaja` (enum) | — (lógica en métodos estáticos, ver sección 3) |
| `Categoria` | `empresa()`, `productos()` HM | `estado` string plano | — |
| `Comanda` | `empresa()`, `pedido()` BT, `areaPreparacion()` BT, `items()` HM | `estado → EstadoComanda` (enum) | — |
| `Compra` | `empresa()`, `sede()`, `proveedor()` BT, `usuario()` BT, `items()` HM | — | — |
| `CompraItem` | `compra()` BT, `insumo()` BT | — | — |
| `Empresa` | `sedes()`, `accesos()`, `categorias()`, `productos()`, `mesas()`, `cajas()`, `areasPreparacion()`, `pedidos()` (todas HM) | `estado → EstadoEmpresa` (enum) | `updating`: NIT/DV inmutables una vez fijados |
| `FacturaElectronica` | `empresa()`, `pedido()` BT | `estado → EstadoFactura` (enum), `respuesta_proveedor → array` | — |
| `GrupoMesa` | `empresa()`, `sede()`, `mesaPrincipal()` BT Mesa, `creadoPor()`/`disueltoPor()` BT User, `mesas()` HM | `estado → EstadoGrupoMesa` (enum) | — |
| `Insumo` | `empresa()`, `recetaItems()` HM, `inventarios()` HM, `movimientos()` HM | `estado` string plano | — |
| `Inventario` | `empresa()`, `sede()`, `insumo()` BT | — | — |
| `ItemPedido` | `pedido()`, `producto()` BT, `areaPreparacion()` BT, `comanda()` BT | — | `creating`: rechaza ítems en pedido no `Abierto` y cantidad &lt;1; `deleting`: rechaza borrar un ítem ya en comanda |
| `Mesa` | `empresa()`, `sede()`, `grupoMesa()` BT | `estado` string plano | `saving`: revalida que el usuario autenticado tenga rol en la sede destino |
| `MovimientoCaja` | `caja()` BT, `empresa()`, `usuario()` BT | `tipo → TipoMovimientoCaja` (enum) | `creating`: rechaza movimiento en caja cerrada, monto no positivo, o usuario sin acceso a la sede |
| `MovimientoInventario` | `empresa()`, `sede()`, `insumo()`, `usuario()`, `pedido()` BT, `compra()` BT, `trasladoInventario()` BT | `tipo → TipoMovimientoInventario` (enum) | — |
| `Pago` | `empresa()`, `pedido()`, `subCuenta()` BT, `usuario()` | `medio → MedioPago` (enum) | — |
| `Pedido` | `empresa()`, `sede()`, `mesa()` BT, `usuario()`, `items()`/`comandas()`/`pagos()`/`subCuentas()` HM | `tipo → TipoPedido`, `estado → EstadoPedido` (ambos enum) | — |
| `Producto` | `empresa()`, `categoria()` BT, `recetaItems()` HM | `estado` string plano | — |
| `Proveedor` | `empresa()`, `compras()` HM | `estado` string plano | — |
| `RecetaItem` | `producto()` BT, `insumo()` BT | — | — |
| `Sede` | `empresa()`, `accesos()`/`mesas()`/`cajas()`/`areasPreparacion()`/`pedidos()` HM | `estado` string plano | — |
| `SubCuenta` | `empresa()`, `pedido()`, `creadoPor()` BT, `items()` HM, `pagos()` HM | — | — |
| `SubCuentaItem` | `subCuenta()` BT, `itemPedido()` BT | — | — |
| `TrasladoInventario` | `empresa()`, `insumo()`, `sedeOrigen()`/`sedeDestino()` BT Sede, `usuario()` | — | — |
| `User` | `accesos()` HM | `password → hashed` | — (muchos métodos de dominio: `esAdminCentralDe`, `rolEnSede`, `sedesAccesibles`, etc.) |

**Inconsistencia real encontrada — `estado` con Enum vs. string plano**: `Caja`, `Comanda`, `Empresa`, `FacturaElectronica`, `GrupoMesa` y `Pedido` castean su columna de estado a un Enum de PHP real (con `HasLabel`/`HasColor` para Filament). En cambio, `AreaPreparacion`, `Categoria`, `Insumo`, `Mesa`, `Producto`, `Proveedor` y `Sede` tienen una columna `estado` (con default `'activa'`/`'activo'` en la migración) **sin ningún Enum correspondiente** — se valida solo por lo que cada formulario de Filament declara de forma independiente (ver duplicación en sección 3).

### Tablas de la BD (34 migraciones, orden cronológico) y su cruce contra los modelos

1. `create_users_table` (+ `password_reset_tokens`, `sessions`)
2. `create_cache_table`
3. `create_jobs_table` (+ `job_batches`, `failed_jobs`)
4. `create_empresas_table`
5. `create_sedes_table`
6. `create_accesos_table` — **sin unique constraint** para `(user_id, empresa_id, sede_id, rol)`; el propio comentario de la migración explica que Postgres no trata los `NULL` como iguales, así que los duplicados se evitan solo a nivel de aplicación (formulario), no de constraint — brecha reconocida explícitamente por quien escribió la migración
7. `create_categorias_table`
8. `create_productos_table`
9. `create_mesas_table`
10. `create_cajas_table` — incluye un **índice único parcial** `cajas_una_abierta_por_sede` (una sola caja abierta por sede, garantizado a nivel de BD, no solo de aplicación)
11. `create_movimiento_cajas_table`
12. `create_personal_access_tokens_table` (Sanctum)
13. `create_area_preparacions_table`
14. `create_pedidos_table` (`idempotency_key` único)
15. `create_comandas_table`
16. `create_item_pedidos_table`
17. `create_pagos_table` (`idempotency_key` único)
18. `create_insumos_table`
19. `create_receta_items_table` (único `[producto_id, insumo_id]`)
20. `create_inventarios_table` (único `[sede_id, insumo_id]`)
21. `create_movimiento_inventarios_table`
22. `create_factura_electronicas_table` (único en `pedido_id`)
23. `add_datos_fiscales_dian_a_productos_table` — altera `productos`: agrega `codigo_impuesto_dian`, `tasa_iva`
24. `create_proveedores_table`
25. `create_compras_table`
26. `create_compra_items_table`
27. `create_traslado_inventarios_table`
28. `add_origen_a_movimiento_inventarios_table` — altera `movimiento_inventarios`: agrega `compra_id`, `traslado_inventario_id`
29. `create_grupo_mesas_table`
30. `add_grupo_mesa_id_a_mesas_table` — altera `mesas`: agrega `grupo_mesa_id`
31. `create_sub_cuentas_table`
32. `create_sub_cuenta_items_table`
33. `add_sub_cuenta_id_a_pagos_table` — altera `pagos`: agrega `sub_cuenta_id`
34. `add_datos_de_perfil_a_empresas_table` — altera `empresas`: agrega 11 columnas de perfil (NIT, DV, régimen, contacto, logo, etc.)

**Resultado del cruce**:
- **Migraciones huérfanas (tabla sin modelo)**: ninguna entre las tablas de negocio — cada tabla propia del dominio tiene su modelo Eloquent correspondiente. Las únicas tablas sin modelo son infraestructura estándar de Laravel/Sanctum (`password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `personal_access_tokens`) — esperado, no es un hallazgo.
- **Campos `#[Fillable]` sin columna de migración correspondiente**: no se encontró ninguno — se verificó cada modelo contra su(s) migración(es), incluyendo las 5 migraciones "add_*".
- **Migraciones conflictivas/duplicadas**: no se encontró ninguna — las migraciones "add_*" agregan columnas genuinamente nuevas, ninguna redefine una columna ya existente de forma contradictoria. Todas las 34 migraciones están fechadas 2026-09-03/04 (el esquema completo se construyó en una sola ráfaga, consistente con `docs/DECISIONES.md` DEC-000: el repo arrancó de cero).

### Campos duplicados o redundantes entre tablas

Búsqueda específica de campos que dupliquen el concepto de otra tabla donde una relación sería más correcta:

- **`ItemPedido.nombre_producto`/`precio_unitario`** son una copia deliberada de `Producto.nombre`/`Producto.precio` en el momento de la venta — es el patrón estándar de "snapshot de línea de pedido" (preserva lo que el cliente pagó aunque el producto cambie después). **No es un defecto**, es necesario para la exactitud histórica de recibos/facturas.
- **`GrupoMesa.mesa_principal_id`** ↔ **`Mesa.grupo_mesa_id`** — par de FK bidireccional. No es "dato duplicado" clásico, pero la consistencia entre ambas direcciones (¿`grupo.mesaPrincipal` siempre tiene `grupo_mesa_id === grupo.id`?) se garantiza solo por código de aplicación (`GrupoMesa::unir()`/`disolver()`), no por una constraint de BD. Vale la pena mencionarlo como punto blando de integridad referencial, no como "campo redundante".
- **`Empresa.nombre` vs. `Empresa.nombre_comercial`** — a primera vista podría parecer redundante, pero el propio comentario de la migración documenta la separación deliberada: `nombre` es el identificador interno (slug, tenancy), `nombre_comercial` es para mostrar en recibos. Intencional y documentado.

**Conclusión: no se encontraron campos genuinamente redundantes** más allá del patrón esperado de "cada tabla tiene su propio `estado`" (convención del proyecto, no un olvido) y los dos casos documentados arriba (snapshot de `ItemPedido`, split de `Empresa.nombre`). El único punto blando real es la pareja `GrupoMesa`/`Mesa` dependiendo de consistencia a nivel de aplicación.

---

## 3. Lógica de negocio

### Dónde vive la lógica — y una desviación real de la convención documentada del propio proyecto

`.claude/rules/laravel.md` (regla propia del proyecto) dice explícitamente:
> "Lógica de negocio (cálculos, reglas de dominio) en clases de servicio o 'acciones' dedicadas, no en modelos Eloquent ni controladores."
> "Controladores delgados: la lógica de negocio va en servicios/acciones, no en el controlador."

**La realidad observada en el código contradice esta regla.** Casi toda la lógica de negocio (transiciones de estado, cálculos, validaciones más allá de reglas simples de campo, e incluso orquestación de transacciones multi-tabla) vive directamente como métodos estáticos/de instancia en los modelos Eloquent, no en clases de servicio dedicadas. Ejemplos concretos:

- `Caja::abrir()` / `Caja->cerrar()` — apertura/cierre completo de turno, con `DB::transaction`, `lockForUpdate`, cálculo de diferencia con `bcsub`.
- `Compra::registrar()` — crea cabecera + ítems + incrementa inventario + escribe movimientos, todo en una transacción, dentro del modelo.
- `GrupoMesa::unir()` / `->disolver()` — bloqueo de filas, validación de invariantes de negocio, mutación de múltiples `Mesa`.
- `Inventario::registrarMovimiento()` — `match()` sobre tipo de movimiento, muta stock, escribe el ledger.
- `Pago::registrar()` — la lógica más compleja del proyecto: idempotencia, bloqueo de filas, transición de estado del pedido, dispara descuento de inventario y emisión de factura, todo en una transacción con fallback ante condición de carrera.
- `Pedido::abrir()`, `->enviarComanda()`, `->anular()`, `->descontarInventario()`.
- `SubCuenta::crear()` (validación de porcentajes) / `->eliminar()`.
- `TrasladoInventario::realizar()` — bloqueo determinista de dos filas para evitar deadlocks.
- `FacturaElectronica::emitirPara()`, `->reintentar()`, `->marcarEmitida()`, `->marcarRechazada()`.
- Autorización/validación embebida en `booted()` de `Acceso`, `Mesa`, `MovimientoCaja`, `Empresa`, `ItemPedido` — justificada explícitamente en comentarios como "defensa en profundidad" (no confiar solo en el frontend/Policy).

Los controladores (`app/Http/Controllers/Pos/*.php`) sí son delgados — pero **porque la lógica se movió a los modelos, no porque exista una capa de servicios** (lo opuesto de lo que pide la regla propia del proyecto).

**Única excepción que sí sigue la regla documentada**: `app/Services/Facturacion/` (`FactusProveedor`, `NuloProveedor`, `ResultadoEmisionFactura`) — encapsula correctamente la integración de facturación electrónica DIAN detrás de la interfaz `App\Contracts\ProveedorFacturacionElectronica`. Es la única parte del código que coincide con la arquitectura documentada; todo lo demás (caja, compra, inventario, pedido, pago, sub-cuenta, grupo de mesas, ciclo de vida de factura) vive en los modelos.

**Veredicto**: el código se desvía de su propia regla documentada. No es necesariamente "incorrecto" (el patrón "modelo rico" es válido en otras convenciones), pero **no es lo que el proyecto dice que hace**, y es una desviación consistente, no un caso aislado.

### Código repetido (misma lógica en varios lugares)

1. **Bloque de autorización `Filament::getTenant()` + `esAdminCentralDe() || sedesAccesibles()->isNotEmpty()`** — idéntico, carácter por carácter, en 9 archivos de Policy: `AccesoPolicy`, `AreaPreparacionPolicy`, `CajaPolicy`, `CompraPolicy`, `InventarioPolicy`, `MesaPolicy`, `MovimientoCajaPolicy`, `TrasladoInventarioPolicy` (variante con `count() >= 2`), y repetido otra vez en los métodos `deleteAny()` de `AccesoPolicy`/`MesaPolicy`. Ya existe un trait compartido (`App\Policies\Concerns\ChecaAdminCentralDelTenant`, usado por 5 *otras* policies para una variante más simple) que no se aplicó de forma consistente a estas 9.
2. **Chequeo `rolEnSede($x) !== null` inline**, repetido 50 veces en 20 archivos — más concentrado en `PedidoController` (9 veces) y repetido con la misma forma exacta (`abort_unless($request->user()->rolEnSede($sede) !== null, 403);`) al inicio de casi todos los métodos de `CajaController`, `ComandaController`, `GrupoMesaController`, `PedidoController`, `ReferenciaController`, `SubCuentaController`. Es un patrón reconocido y justificado explícitamente en el docblock de `PedidoPolicy` ("para no multiplicar clases casi idénticas" en Comanda/Pago) — una decisión consciente, pero sigue siendo exactamente el patrón que `.claude/rules/laravel.md` pide evitar ("Autorización mediante Policies, no `if` sueltos comprobando roles dentro de controladores").
3. **Listas de opciones de `estado` hardcodeadas**, duplicadas entre el formulario y la tabla de 7 Resources (14 archivos en total): `AreaPreparacionForm`/`Table`, `CategoriaForm`/`Table`, `InsumoForm`/`Table`, `MesaForm`/`Table`, `ProductoForm`/`Table`, `ProveedorForm`/`Table`, `SedeForm`/`Table` — cada uno re-declara independientemente `['activa' => 'Activa', 'inactiva' => 'Inactiva']` (o variante) y la misma comparación de string para el color del badge. Esto es consecuencia directa del hallazgo de la sección 2 (estos 7 modelos no tienen un Enum de `estado`) — es un caso concreto y corregible de "misma lógica en más de un lugar".
4. No se encontró duplicación real entre una validación de Form Request y un guardián de modelo `booted()` que verifiquen exactamente lo mismo — en general cubren cosas distintas (forma/tipo de entrada vs. invariantes de estado/autorización).

### Archivos grandes (&gt;300 líneas)

**PHP**: ningún archivo en `app/` supera 300 líneas. Los más grandes: `Pedido.php` (247), `PedidoController.php` (231), `FactusProveedor.php` (221), `GrupoMesa.php` (163), `Caja.php` (153).

**Vue**: un solo archivo supera el umbral — **`resources/js/pos/views/PosView.vue`, 543 líneas** (carrito + catálogo + todos los modales/formularios del flujo principal de venta en un solo archivo). El resto está muy por debajo: `CajaView.vue` (276), `DividirCuentaModal.vue` (222), `GrupoMesaView.vue` (184), `PagoModal.vue` (139).

---

## 4. Filament / Livewire

### Resources, widgets y componentes Livewire existentes

- **12 Resources de Filament**: `AccesoResource`, `AreaPreparacionResource`, `CajaResource`, `CategoriaResource`, `CompraResource`, `FacturaElectronicaResource`, `InsumoResource`, `InventarioResource`, `MesaResource`, `ProductoResource`, `ProveedorResource`, `SedeResource`, `TrasladoInventarioResource`.
- **1 página raíz fuera de Resource**: `ReporteConsolidado` (dashboard de reporte consolidado multisede).
- **2 páginas de Tenancy**: `EditEmpresaProfile` (perfil de la empresa/tenant), `RegisterEmpresa` (alta de tenant nuevo).
- **0 Widgets** (`find app/Filament -iname "*widget*"` no devolvió nada).
- **0 componentes Livewire independientes** (no existe `app/Livewire/`) — Livewire está presente solo como motor interno de Filament.

### Convenciones — en general consistentes, con dos excepciones puntuales

La estructura de carpetas es uniforme en los 12 Resources: `NombreResource.php` + `Pages/{List,Create,Edit,View}Nombre.php` + `Schemas/NombreForm.php` + `Tables/NombresTable.php`, y `RelationManagers/` donde aplica (Cajas→Movimientos, Compras→Items, Productos→RecetaItems). No se encontró un Resource que rompa este patrón de ubicación de archivos.

Dos observaciones puntuales:
1. **`AccesoResource` gestiona el modelo `Acceso` pero se etiqueta "Usuarios" en el panel** (`getModelLabel()`/`getPluralModelLabel()` devuelven "usuario"/"usuarios", no "acceso"/"accesos"). Es una decisión de UX documentada (`docs/DECISIONES.md` DEC-037: el modelo interno es `Acceso` porque `User` no tiene `empresa_id` propio y no hereda el aislamiento de tenancy de Filament) — no es un descuido, pero sí una divergencia entre el nombre de clase/carpeta y la etiqueta visible que vale la pena tener presente si alguien busca "dónde está el CRUD de usuarios" por nombre de archivo.
2. Ya documentado en sesiones anteriores del propio proyecto (`docs/DECISIONES.md`): Filament pluraliza automáticamente **solo la última palabra en camelCase** de un nombre de Resource compuesto — causó una ruta/tabla con pluralización rara para `FacturaElectronicaResource` en su momento. Quedó anotado como lección aprendida y evitado proactivamente en resources posteriores, pero es una característica del framework que puede volver a sorprender si se agrega un Resource nuevo con nombre compuesto sin tenerlo en cuenta.

---

## 5. Botones y funcionalidades (UI)

Cobertura: los 12 Resources completos (toda página List/Create/Edit/View, cada Table, cada RelationManager), más las 3 páginas fuera de Resource. Para cada acción se indica ubicación, qué dice, qué hace realmente en el código, si tiene confirmación/modal, y qué la autoriza.

| Ubicación | Botón/Acción | Estado | Prioridad | Nota |
|---|---|---|---|---|
| AccesoResource · ListAccesos | Crear (usuario) | OK | — | `CreateAction` estándar; formulario permite elegir usuario existente o crearlo inline (`createOptionForm`). Autorizado por `AccesoPolicy::create()`. |
| AccesoResource · EditAcceso | Eliminar | OK | — | `DeleteAction` estándar, con confirmación. Autorizado por `AccesoPolicy::delete()` (`puedeGestionar()`). |
| AccesoResource · Tabla | Editar | OK | — | Estándar, `AccesoPolicy::update()`. |
| AccesoResource · Tabla | Eliminar seleccionados | OK | — | Envuelto en `BorradoSeguro::variosRegistros()` — mensaje legible si hay FKs asociadas. |
| AreaPreparacionResource · List/Edit/Tabla | Crear / Eliminar / Editar / Eliminar seleccionados | OK | — | Las 4 acciones estándar, todas con Policy correspondiente; bulk-delete envuelto en `BorradoSeguro`. |
| CajaResource · ListCajas | "Abrir caja" | OK | — | `CreateCaja::handleRecordCreation()` llama a `Caja::abrir()`; captura `RuntimeException` (ej. ya hay una caja abierta) con notificación legible. Autorizado por `CajaPolicy::create()`. |
| CajaResource · ViewCaja | "Cerrar caja" | OK | — | Formulario (monto real + nota) → `$record->cerrar()`; captura `RuntimeException`. Visible solo si `estaAbierta()` **y** `Auth::user()->can('cerrar', $record)` — doble gate bien implementado. |
| CajaResource · Tabla | (solo Ver) | OK | — | Deliberadamente sin Editar/Eliminar — las cajas son historial de auditoría, documentado en el propio código. |
| CajaResource · MovimientosRelationManager | "Registrar movimiento" | OK | MEDIA (nota) | Funciona correctamente — visible solo si `estaAbierta()`. No llama a `MovimientoCajaPolicy` de forma explícita (esa policy existe pero está pensada para no poder validar la caja concreta, según su propio comentario, y delega la validación real al estado de negocio + al guardián en `MovimientoCaja::booted()`) — decisión deliberada, no un descuido, pero vale la pena saber que `MovimientoCajaPolicy` no se invoca desde ningún punto de `app/Filament/**`. |
| CategoriaResource · List/Edit/Tabla | Crear / Eliminar / Editar / Eliminar seleccionados | OK | — | Las 4, restringidas a administración central; bulk envuelto en `BorradoSeguro`. |
| CompraResource · ListCompras | "Registrar compra" | OK | — | `CreateCompra::handleRecordCreation()` → `Compra::registrar()` (transacción completa); captura `InvalidArgumentException`. |
| CompraResource · Tabla | (solo Ver) | OK | — | Sin editar/eliminar — compras son inmutables por diseño (documentado). |
| CompraResource · ItemsRelationManager | (ninguna acción) | OK | — | Completamente de solo lectura, por diseño explícito en el código — no hay botón que revisar. |
| FacturaElectronicaResource · Tabla | "Reintentar" | OK | — | Solo visible si `estado === Rechazada` **y** `Auth::user()->can('update', $record)`; tiene `requiresConfirmation()`. Buen ejemplo de doble gate (estado + Policy). |
| InsumoResource · List/Edit/Tabla | Crear / Eliminar / Editar / Eliminar seleccionados | OK | — | Las 4, restringidas a admin central; bulk envuelto en `BorradoSeguro` — relevante porque `insumos` tiene 5 tablas con `restrictOnDelete()` apuntándole. |
| InventarioResource · ListInventarios | "Registrar movimiento" | **A_MEDIAS** | **MEDIA** | La acción no tiene `->visible()` ni `->authorize()` propios — funciona (llama a `Inventario::registrarMovimiento()`), pero `InventarioPolicy::create()` **existe y su propio comentario dice explícitamente que fue escrita para autorizar este botón**, y sin embargo nunca se invoca desde aquí. Es una autorización "olvidada sin conectar", no solo una decisión deliberada como en el caso de Caja. Impacto práctico limitado porque solo admin central/de sede llegan al panel, pero es una inconsistencia real frente al patrón usado en Factura/Caja. |
| MesaResource · List/Edit/Tabla | Crear / Eliminar / Editar | OK | — | Estándar, con Policy. |
| MesaResource · Tabla | Eliminar seleccionados | **ROTO** | **ALTA** | `DeleteBulkAction` **sin envolver en `BorradoSeguro`**, a diferencia de every otro Resource con el mismo patrón (Accesos, AreaPreparacion, Categoria, Insumo, Proveedor, Sede sí lo hacen). `mesas.id` tiene FKs `restrictOnDelete()` reales desde `pedidos.mesa_id` y `grupo_mesas.mesa_principal_id` — borrar en bloque una mesa que ya tuvo un pedido lanza una `QueryException` sin capturar directo a la pantalla de error de Filament, en vez del aviso legible que `BorradoSeguro` existe justamente para dar (su propio docblock menciona "mesas" como ejemplo). Se dispara solo cuando la mesa tiene registros asociados, pero eso es lo normal después de un tiempo de uso — prioridad alta porque Mesas es una pantalla de uso diario. |
| ProductoResource · List/Edit/Tabla | Crear / Eliminar / Editar | OK | — | Estándar, restringido a admin central. |
| ProductoResource · Tabla | Eliminar seleccionados | **ROTO** | **ALTA** | Mismo problema exacto que Mesas: `DeleteBulkAction` sin `BorradoSeguro`, y `productos.id` tiene `restrictOnDelete()` desde `item_pedidos.producto_id` — borrar en bloque un producto ya vendido revienta con una excepción sin capturar. Segunda ocurrencia del mismo patrón de bug. |
| ProductoResource · RecetaItemsRelationManager | Crear / Editar / Eliminar / Eliminar seleccionados | OK | — | Ninguna envuelta en `BorradoSeguro`, pero riesgo bajo real: ninguna FK restrictiva apunta *a* filas de `receta_items` mismas (solo `insumo_id` es `restrictOnDelete`, no aplica al borrar la línea de receta). |
| ProveedorResource · List/Edit/Tabla | Crear / Eliminar / Editar / Eliminar seleccionados | OK | — | Las 4; bulk correctamente envuelto (`proveedores.id` es `restrictOnDelete` desde `compras.proveedor_id`). |
| SedeResource · List/Edit/Tabla | Crear / Eliminar / Editar / Eliminar seleccionados | OK | — | Las 4; bulk correctamente envuelto — el ejemplo más cargado de FKs restrictivas de todo el esquema (cajas, mesas, pedidos, áreas, inventarios, movimientos, compras, traslados x2, grupos de mesa), y aun así está bien protegido. |
| TrasladoInventarioResource · ListTrasladoInventarios | "Nuevo traslado" | **DUPLICADO** (de `TrasladoInventarioPolicy::create()`) | **MEDIA** | La acción funciona (llama a `TrasladoInventario::realizar()` tras validar), pero implementa **su propia lógica de autorización inline** (revalida acceso a ambas sedes elegidas) en vez de invocar `TrasladoInventarioPolicy::create()`, que ya existe, ya está probada por un test, y exige exactamente la misma regla (≥2 sedes o admin central) de forma independiente. Dos implementaciones de la misma regla que pueden desincronizarse con el tiempo. El riesgo práctico está acotado porque el cierre inline sí revalida al enviar el formulario, pero el botón es visible para cualquier usuario del panel sin pasar por la Policy pensada para esa decisión. |
| ReporteConsolidado (página raíz) | Filtro de fechas / "Actualizar" | OK | — | El método `actualizar()` tiene el cuerpo vacío **a propósito** (documentado: Livewire ya sincroniza las fechas al enviar, el método solo dispara el recálculo del computed `filas`) — no es un bug. Página restringida a admin central vía `canAccess()`. |
| EditEmpresaProfile (Tenancy) | Guardar perfil | OK | — | Botón nativo de Filament (`EditTenantProfile`); NIT/DV se deshabilitan en el formulario una vez fijados, con el guardián real en `Empresa::booted()`. |
| RegisterEmpresa (Tenancy) | Registrar empresa | OK | — | Autoregistro: crea la empresa + el primer `Acceso` de administración central. `EmpresaPolicy::create()` devuelve `true` sin condición — es el flujo de alta self-service intencional del SaaS, no un descuido (ver sección 8). |

### Resumen de la sección 5

| Estado | ALTA | MEDIA | BAJA | Total |
|---|---|---|---|---|
| ROTO | 2 | 0 | 0 | **2** |
| VACÍO | 0 | 0 | 0 | **0** |
| A_MEDIAS | 0 | 1 | 0 | **1** |
| DUPLICADO | 0 | 1 | 0 | **1** |
| SIN_PERMISOS | 0 | 0 | 0 | **0** |
| **OK** | — | — | — | **~30** |

Los 2 ROTO (Mesas y Productos, "Eliminar seleccionados" sin `BorradoSeguro`) son el hallazgo más accionable de esta sección: mismo bug, mismo patrón, dos ocurrencias, prioridad alta por ser pantallas de uso diario.

---

## 6. Deuda técnica visible

- **TODO/FIXME/`dd()`/`dump()`**: cero ocurrencias reales en `app/`, `resources/`, `routes/`. Los únicos matches de la búsqueda de `dd(`/`dump(` fueron falsos positivos de `bcadd(`/`bcdiv(`/`bcmul(` (funciones de precisión decimal, no debug).
- **Código comentado (muerto)**: un solo caso — `app/Models/User.php:5`, un `use Illuminate\Contracts\Auth\MustVerifyEmail;` comentado, remanente del esqueleto por defecto de Laravel, nunca limpiado. Cosmético, sin riesgo.
- **`MovimientoCajaPolicy` no referenciada**: existe como archivo (`app/Policies/MovimientoCajaPolicy.php`) pero ningún punto de `app/Filament/**` la invoca (ver sección 5, fila de Caja/Movimientos) — código de policy efectivamente muerto desde la perspectiva del panel, aunque su intención está documentada en su propio comentario.
- **Dependencias**: no se encontró ninguna declarada y sin usar de forma confirmada, salvo `@laravel/multiplex` (dependencia opcional de npm) — no se encontró ningún `import`/`require` directo en `resources/js/pos/`, pero podría ser una dependencia indirecta de tooling de Reverb/Echo; no se pudo confirmar su uso real solo por grep estático, así que se marca como "posiblemente sin usar, no verificado del todo" en vez de afirmarlo. No se detectó ninguna dependencia obsoleta por versión mayor. No se ejecutó `composer audit`/`npm audit` (sin acceso a red en esta auditoría) — no hay información sobre CVEs conocidas.
- **Falta de unique constraint real en `accesos`**: reconocida por el propio comentario de la migración (ver sección 2) — duplicados de `(user_id, empresa_id, sede_id, rol)` solo se evitan a nivel de formulario, no de base de datos.
- **Políticas incompletas / modelos sin Policy**: `GrupoMesa` y `SubCuenta` son los dos hallazgos genuinos — ambos son entidades de negocio de primer nivel (con sus propios métodos estáticos de negocio: `GrupoMesa::unir()/disolver()`, `SubCuenta::crear()/eliminar()`) pero **no tienen Resource de Filament ni Policy propia** — su única autorización son chequeos `abort_unless(...rolEnSede...)` manuales dentro de `GrupoMesaController`/`SubCuentaController`. Esto es exactamente el patrón que `.claude/rules/laravel.md` pide evitar. (`Comanda`, `Pago`, `RecetaItem`, `CompraItem`, `SubCuentaItem`, `ItemPedido` también carecen de Policy propia, pero está justificado explícitamente en comentarios del código como una decisión consciente para no multiplicar clases casi idénticas — GrupoMesa y SubCuenta son los dos casos sin esa justificación explícita.)

---

## 7. Riesgos

1. **Los dos "Eliminar seleccionados" sin `BorradoSeguro` (Mesas, Productos)** — sección 5 — es el riesgo más concreto y fácil de reproducir: un admin que seleccione varias mesas o productos (incluyendo alguno con historial real) para borrar en bloque se encuentra con una pantalla de error de Laravel en vez de un aviso, en dos de las pantallas más usadas del panel.
2. **`accesos` sin unique constraint real** — nada a nivel de base de datos impide que, por una condición de carrera o un bug futuro, un mismo usuario termine con dos filas idénticas de acceso a la misma sede con el mismo rol. Hoy se previene solo en el formulario de `AccesoResource` (validación de aplicación, ver DEC-037), no en el esquema.
3. **`GrupoMesa`/`Mesa` con integridad referencial cruzada dependiente de código de aplicación**, no de una constraint — si en el futuro se agrega otro punto de escritura a `grupo_mesas`/`mesas` que no pase por `GrupoMesa::unir()`/`disolver()`, nada a nivel de BD impediría que las dos direcciones del FK queden inconsistentes.
4. **Desviación consistente de la regla propia de arquitectura** (lógica de negocio en modelos en vez de servicios/acciones dedicadas) — no es un bug hoy, pero significa que cualquier "limpieza hacia la convención documentada" en el futuro es un refactor de fondo, no un ajuste menor, porque toca prácticamente todos los modelos operativos.
5. **Autorización parcialmente "olvidada"**: `InventarioPolicy::create()` (escrita para un botón específico, nunca conectada) y la reimplementación paralela de `TrasladoInventarioPolicy::create()` en `ListTrasladoInventarios` son dos ejemplos concretos de que una Policy puede existir, estar bien escrita, y aun así no estar realmente protegiendo el botón para el que fue pensada — un patrón a vigilar si se agregan más acciones personalizadas en Filament.
6. **Pluralización de nombres compuestos en Filament** (documentado ya una vez en `docs/DECISIONES.md` para `FacturaElectronicaResource`) — riesgo latente para cualquier Resource nuevo con nombre compuesto en camelCase si no se tiene en cuenta explícitamente.
7. **`estado` como string libre en 7 modelos** (sección 2/3) — sin un Enum, nada impide que un valor fuera de las dos opciones esperadas ("activa"/"inactiva" o variantes) termine guardado por un canal que no pase por el formulario de Filament (un seeder, un `tinker`, una futura API) — los modelos con Enum (`Caja`, `Pedido`, etc.) no tienen este riesgo porque el cast normaliza/valida el valor.
8. **`EmpresaPolicy::create()` retorna `true` sin condición** — verificado como decisión deliberada (flujo de autoregistro SaaS), no un descuido, pero es la única Policy del proyecto con un `return true` incondicional en un verbo sensible (`create`) sin ningún chequeo adicional — vale la pena que quede documentado como una decisión consciente y no algo que se re-audite como "bug" en el futuro sin este contexto.

---

## Nota final sobre el método de esta auditoría

Las secciones 4-8 se investigaron con dos agentes de exploración de código en paralelo, cada uno leyendo directamente los archivos fuente citados (sin ejecutar la aplicación ni pruebas). Las referencias de archivo:línea provienen de esas lecturas reales, no de suposición — pero como cualquier análisis estático, un número de línea puede haber cambiado si el archivo se editó después de esta auditoría; verifica el archivo actual antes de actuar sobre una referencia puntual. No se inventó ningún hallazgo: donde algo no se pudo confirmar por completo (uso real de `@laravel/multiplex`, estado de CVEs de dependencias), se dice explícitamente en el texto en vez de asumir.
