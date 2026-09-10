Quiero que configures profesionalmente la memoria, las reglas y los procedimientos de Claude Code para este proyecto.

# DEC-000: Identidad del proyecto (resuelta)

El proyecto se llama **Sazón360**: un producto SaaS multiempresa/multisede nuevo y separado, distinto de "Dulcita POS" (app single-tenant para un solo cliente, stack cPanel/MySQL/Livewire, ya en desarrollo aparte). No reemplaza ni modifica esa decisión anterior — son dos codebases distintos.

Sigue pendiente, y no bloquea el trabajo de esta tarea, si Dulcita será el primer cliente piloto de Sazón360 o si el piloto será otro negocio. Regístralo en `docs/DECISIONES.md` como decisión abierta y de baja prioridad; no es necesario resolverlo para completar la configuración de Claude Code.

Si durante la inspección el repositorio resulta estar vacío o confirma ser un proyecto nuevo, registra `DEC-000` en `docs/DECISIONES.md` con estado `aprobada` usando el contexto de arriba y continúa.

# CONTEXTO DEL PROYECTO

**Sazón360**: sistema POS SaaS táctil para cafeterías y restaurantes con múltiples empresas y sedes.

Tecnologías previstas:

* Backend: Laravel.
* Panel administrativo: Filament.
* POS táctil: Vue.js como PWA.
* Base de datos: PostgreSQL.
* Tiempo real: Redis y WebSockets.
* Pruebas: Pest.
* Desarrollo local: Windows con Laragon.
* Producción: VPS administrado mediante Docker o EasyPanel.
* Control de versiones: Git y GitHub.
* Impresión: impresoras térmicas conectadas mediante red o un agente local instalado en Windows.

El sistema deberá manejar progresivamente:

* Empresas y planes SaaS.
* Múltiples sedes.
* Empleados, roles y permisos.
* Turnos y asistencia.
* Apertura y cierre de caja.
* Mesas, pisos y zonas.
* Pedidos de mesa, mostrador, para llevar y domicilio.
* Productos, categorías, variantes y modificadores.
* Comandas separadas para cocina, barra, panadería y otras áreas.
* Pantallas KDS.
* Impresión térmica por área.
* División, unión y traslado de cuentas y mesas.
* Diferentes medios de pago.
* Recetas e inventario por ingredientes.
* Compras, proveedores, producción, desperdicios y mermas.
* Traslados de inventario entre sedes.
* Clientes, fidelización y promociones.
* Facturación electrónica e integraciones futuras.
* Reportes y administración central de todas las sedes.

# OBJETIVO DE ESTA TAREA

En esta primera tarea NO debes desarrollar módulos funcionales del POS ni modificar la lógica existente.

Debes inspeccionar el repositorio y construir una estructura profesional para que Claude Code:

1. Recuerde las decisiones importantes del proyecto.
2. Consuma menos tokens.
3. No tenga que volver a investigar el proyecto en cada sesión.
4. Trabaje sobre un módulo a la vez.
5. No modifique archivos que no estén relacionados con la tarea.
6. Mantenga documentación actualizada.
7. Aplique buenas prácticas de seguridad, arquitectura y pruebas.
8. Evite convertir el proyecto en un desarrollo infinito sin prioridades.
9. Use los mecanismos nativos de Claude Code (auto memory, `/init`, hooks, permisos) en vez de reinventarlos con texto en `CLAUDE.md`.

# PRIMER PASO: INSPECCIÓN

Antes de crear archivos:

1. Registra `DEC-000` (arriba) en `docs/DECISIONES.md` tal como está resuelta.
2. Si el repositorio ya tiene código y tu versión de Claude Code lo soporta, ejecuta `/init` (con `CLAUDE_CODE_NEW_INIT=1` si está disponible) para generar una base de `CLAUDE.md` y detectar convenciones automáticamente. Usa este prompt para completar lo que `/init` no cubre: reglas de negocio del dominio POS, modelo multiempresa/multisede, glosario y skills específicas.
3. Inspecciona la estructura actual del repositorio.
4. Identifica las versiones reales de Laravel, PHP, Filament, Vue, Node y PostgreSQL cuando sea posible. Verifica también la versión de Claude Code instalada (`claude --version`) — los skills como slash-commands (`/crear-modulo`, etc.) requieren 2.1.1 o superior.
5. Revisa `composer.json`, `package.json`, `.env.example`, las migraciones y las carpetas principales.
6. Detecta si ya existen archivos `CLAUDE.md`, `.claude`, documentación o instrucciones similares.
7. No leas carpetas generadas o pesadas como:

   * `vendor`
   * `node_modules`
   * `.git`
   * archivos compilados
   * cachés
   * logs extensos
8. No supongas que una tecnología está instalada solamente porque aparece en este prompt.
9. Presenta un resumen breve de lo encontrado y el plan de archivos que crearás.
10. Después continúa con la configuración, siempre que no exista un conflicto importante.

Si el repositorio está vacío, crea la estructura de documentación usando las tecnologías previstas, pero marca claramente qué decisiones todavía deben confirmarse.

# ESTRUCTURA QUE DEBES CREAR

Crea o completa cuidadosamente esta estructura:

```text
CLAUDE.md
CLAUDE.local.md.example

docs/
├── PRODUCTO.md
├── ARQUITECTURA.md
├── REGLAS-NEGOCIO.md
├── MODELO-DATOS.md
├── DECISIONES.md
├── ROADMAP.md
├── MODULO-ACTUAL.md
└── GLOSARIO.md

.claude/
├── settings.json
├── rules/
│   ├── laravel.md
│   ├── filament.md
│   ├── frontend-pos.md
│   ├── base-datos.md
│   ├── testing.md
│   └── seguridad.md
└── skills/
    ├── crear-modulo/
    │   └── SKILL.md
    ├── revisar-modulo/
    │   └── SKILL.md
    ├── migracion-segura/
    │   └── SKILL.md
    ├── cerrar-tarea/
    │   └── SKILL.md
    └── investigar-error/
        └── SKILL.md
```

Antes de sobrescribir cualquier archivo existente, léelo y conserva todas las instrucciones válidas. Integra los cambios sin destruir información anterior.

# CONTENIDO DE CLAUDE.md

Crea un `CLAUDE.md` claro, específico y de máximo 200 líneas.

Debe contener:

* Objetivo resumido del producto.
* Tecnologías realmente detectadas.
* Comandos confirmados para instalar, ejecutar, probar y compilar.
* Organización general del proyecto.
* Principios de arquitectura.
* Reglas para multiempresa y multisede.
* Flujo obligatorio antes de modificar código.
* Estándares mínimos de seguridad y pruebas.
* Regla para trabajar sobre una sola función a la vez.
* Archivos de documentación que se deben consultar según la tarea.
* Acciones prohibidas (nota: las prohibiciones críticas —`git push`, editar `.env`, comandos destructivos— deben ir TAMBIÉN en `.claude/settings.json` como reglas `deny`; ver más abajo. Aquí en `CLAUDE.md` quedan como referencia legible, no como único mecanismo de bloqueo).
* Instrucciones para mantener actualizado `docs/MODULO-ACTUAL.md`.

No copies en `CLAUDE.md` toda la documentación del negocio. Debe funcionar como un índice operativo corto y no como un manual gigante. Recuerda que Auto Memory de Claude Code está activo por defecto y complementa este archivo con notas propias (tus correcciones, preferencias) — no dupliques ahí lo que Auto Memory ya puede capturar solo.

Incluye reglas como las siguientes:

* Toda entidad operativa debe estar asociada a una empresa cuando corresponda.
* Ventas, cajas, mesas, inventarios y empleados deben estar asociados a una sede.
* Nunca confiar únicamente en filtros enviados desde el frontend para aislar empresas o sedes.
* Los permisos deben validarse en el backend.
* Los precios históricos de una venta no deben cambiar si cambia el precio del producto.
* Una comanda enviada no debe eliminarse físicamente; debe anularse con trazabilidad.
* Descuentos, anulaciones y reaperturas requieren permisos y auditoría.
* Los movimientos de caja e inventario deben mantener historial.
* Los procesos críticos deben usar transacciones de base de datos.
* Las operaciones repetidas por pérdida de conexión deben ser idempotentes.
* No crear funciones futuras fuera del alcance del módulo actual.
* No agregar dependencias sin explicar su necesidad.
* No ejecutar comandos destructivos sin autorización.
* No editar `.env` ni exponer secretos.
* No borrar migraciones que pudieran haberse ejecutado.
* No afirmar que una tarea funciona sin ejecutar pruebas relacionadas.

# DOCUMENTACIÓN DEL PROYECTO

## docs/PRODUCTO.md

Documenta:

* Problema que resuelve el POS.
* Tipos de clientes.
* Usuarios del sistema.
* Alcance del MVP.
* Funciones posteriores.
* Funciones expresamente fuera del MVP.
* Diferencia entre administración central, sede, caja, mesero y área de preparación.

El MVP inicial debe concentrarse en:

1. Empresas y sedes.
2. Usuarios, roles y permisos.
3. Productos, categorías y precios.
4. Pisos, zonas y mesas.
5. Apertura y cierre de caja.
6. Creación de pedidos.
7. Comandas por área.
8. Cocina o KDS.
9. Cobro y medios de pago.
10. Reportes operativos básicos.

Nota: el punto 8 (KDS) requiere tiempo real desde el MVP — si Redis/WebSockets no están listos, decide explícitamente si el MVP usa polling como alternativa temporal y regístralo en `docs/DECISIONES.md`.

No incluyas inicialmente contabilidad completa, nómina, inteligencia artificial, domicilios avanzados ni marketplace.

## docs/ARQUITECTURA.md

Documenta una arquitectura inicial y señala qué partes ya existen y cuáles son propuestas.

Debe contemplar:

* SaaS multiempresa.
* Múltiples sedes por empresa.
* API backend.
* Panel administrativo.
* POS táctil PWA.
* WebSockets.
* Redis.
* KDS con estaciones configurables por el usuario — no fijas ni hardcodeadas en el código (una sede puede tener una sola estación de producción o varias, según su tamaño).
* Agente local de impresión.
* Impresoras térmicas en red.
* Manejo de pérdida temporal de Internet.
* Auditoría.
* Colas y trabajos en segundo plano.
* Copias de seguridad.
* Separación entre dominio, aplicación e infraestructura sin crear complejidad innecesaria.

No inventes microservicios. Comienza con un monolito modular bien organizado, salvo que el repositorio ya tenga otra arquitectura justificada.

## docs/REGLAS-NEGOCIO.md

Organiza las reglas por módulos:

* Empresas y sedes.
* Usuarios y permisos.
* Turnos.
* Caja.
* Productos.
* Mesas.
* Pedidos.
* Comandas.
* Cocina y KDS.
* División y unión de cuentas.
* Pagos.
* Inventario.
* Recetas.
* Compras.
* Mermas.
* Traslados.
* Auditoría.

Incluye, como propuesta a confirmar, una regla de tolerancia de pago para el módulo de suscripción/facturación: si el pago de la empresa se retrasa, no interrumpir un turno o caja que ya esté abierto — el bloqueo de nuevas operaciones aplica solo después de una ventana de tolerancia definida (ej. varios días), conservando siempre acceso de consulta y exportación de datos.

Distingue entre:

* Regla confirmada.
* Propuesta.
* Decisión pendiente.

No presentes las propuestas como si ya fueran decisiones definitivas.

## docs/MODELO-DATOS.md

Documenta las entidades detectadas actualmente y las entidades propuestas.

Para cada entidad incluye:

* Propósito.
* Empresa y sede a la que pertenece.
* Relaciones principales.
* Estados.
* Restricciones.
* Índices importantes.
* Campos que requieren auditoría.
* Riesgos de duplicación o concurrencia.

No crees migraciones todavía.

## docs/DECISIONES.md

Crea un registro de decisiones con este formato:

```markdown
## DEC-000: Identidad y modelo de tenant del proyecto

- Fecha:
- Estado: aprobada
- Contexto: Sazón360 es un producto SaaS multiempresa/multisede, independiente de Dulcita POS (single-tenant, proyecto separado).
- Decisión: Sazón360 es un repositorio y codebase propio, no una evolución de Dulcita POS.
- Motivo: mercado objetivo distinto (plataforma para la industria vs. app de un solo cliente); stacks incompatibles (PostgreSQL/Vue/VPS vs. MySQL/Livewire/cPanel).
- Consecuencias: ninguna decisión previa de Dulcita POS aplica aquí; el modelo de datos parte de cero con empresa → sede.
- Reemplaza: N/A

## DEC-001: ¿Dulcita será el cliente piloto?

- Fecha:
- Estado: propuesta
- Contexto: el roadmap prioriza una "cafetería piloto" funcional antes de features avanzadas.
- Decisión: pendiente.
- Motivo:
- Consecuencias:
- Reemplaza: N/A

## DEC-002: Nombre de la decisión

- Fecha:
- Estado: propuesta | aprobada | reemplazada
- Contexto:
- Decisión:
- Motivo:
- Consecuencias:
- Reemplaza:
```

Registra únicamente decisiones arquitectónicas importantes. No llenes el documento con decisiones triviales.

## docs/ROADMAP.md

Organiza el desarrollo por fases pequeñas, verificables y vendibles.

Cada fase debe incluir:

* Objetivo.
* Funciones.
* Criterio de terminación.
* Dependencias.
* Riesgos.
* Elementos que no se desarrollarán todavía.

Prioriza conseguir una cafetería piloto funcional antes de desarrollar características avanzadas.

Como criterio de priorización frente a competidores que solo cubren captura de pedidos y caja (sin inventario, recetas, compras ni facturación electrónica), considera adelantar recetas/inventario básico y facturación electrónica a una fase relativamente temprana del roadmap — son un diferenciador real, no solo una función más — sin que esto rompa la regla de "una cafetería piloto funcional primero".

## docs/MODULO-ACTUAL.md

Crea una plantilla operativa con:

* Nombre del módulo actual.
* Objetivo.
* Alcance incluido.
* Fuera de alcance.
* Reglas relacionadas.
* Archivos relacionados.
* Trabajo terminado.
* Trabajo pendiente.
* Pruebas ejecutadas.
* Errores conocidos.
* Decisiones pendientes.
* Próxima acción exacta.

Este documento debe actualizarse al finalizar cada sesión o tarea importante.

## docs/GLOSARIO.md

Define términos para evitar confusiones:

* Empresa.
* Sede.
* Punto de venta.
* Caja.
* Turno.
* Piso.
* Zona.
* Mesa.
* Pedido.
* Venta.
* Cuenta.
* Comanda.
* Área de preparación.
* KDS.
* Modificador.
* Receta.
* Insumo.
* Merma.
* Traslado.
* Cierre de caja.

# REGLAS ESPECIALIZADAS

Crea reglas dentro de `.claude/rules/`. **Cada archivo debe incluir frontmatter `paths:` que lo limite a las rutas reales del proyecto** (determinadas en la inspección) — sin esto, las 6 reglas se cargan en cada sesión sin importar la tarea, contradiciendo el objetivo de ahorro de tokens. Ejemplo de estructura esperada:

```markdown
---
paths:
  - "app/**/*.php"
---

# Reglas de Laravel
...
```

Sugerencia de alcance por archivo (ajusta tras la inspección real):

* `laravel.md` → código PHP general (`app/**/*.php`, `routes/**/*.php`).
* `filament.md` → solo Resources/Pages de Filament (`app/Filament/**/*.php`).
* `frontend-pos.md` → el proyecto Vue/PWA (confirma la carpeta real tras inspección, p. ej. `resources/js/**/*.vue` o un directorio `pos-app/` separado).
* `base-datos.md` → migraciones y modelos (`database/**/*.php`, `app/Models/**/*.php`).
* `testing.md` → pruebas (`tests/**/*.php`).
* `seguridad.md` → sin `paths:` (carga siempre). Es una excepción deliberada: por su carácter transversal y crítico (aislamiento multiempresa, autorización), conviene que esté presente en toda sesión en vez de depender de qué archivo se esté tocando.

## laravel.md

Incluye:

* Convenciones del proyecto.
* Controladores delgados.
* Validación mediante Form Requests cuando corresponda.
* Servicios o acciones para lógica de negocio.
* Policies para autorización.
* Eventos para procesos desacoplados.
* Jobs para tareas largas.
* Transacciones para operaciones críticas.
* Evitar consultas N+1.
* Tipado y nombres consistentes.

## filament.md

Incluye:

* Filament solamente para administración.
* No construir el POS táctil como un conjunto improvisado de Resources de Filament.
* Permisos por empresa y sede.
* Acciones sensibles con autorización y confirmación.
* Formularios y tablas consistentes.
* Evitar consultas costosas en dashboards.

## frontend-pos.md

Incluye:

* Interfaz táctil.
* Botones grandes.
* Pocos pasos.
* Estados visibles.
* Prevención de doble toque.
* Manejo de espera, error y reconexión.
* Accesibilidad.
* Diseño responsive.
* Operaciones idempotentes.
* No confiar en el frontend para seguridad.
* Separar estado local, estado del servidor y eventos en tiempo real.

## base-datos.md

Incluye:

* PostgreSQL.
* Llaves foráneas.
* Índices.
* Restricciones únicas.
* Valores monetarios seguros.
* Fechas y zonas horarias.
* Transacciones.
* Bloqueos y concurrencia.
* Soft delete solamente cuando tenga sentido.
* Auditoría.
* Reglas de tenant y sede.
* Migraciones compatibles con producción.

## testing.md

Incluye:

* Pest.
* Pruebas unitarias para reglas puras.
* Pruebas de integración para flujos críticos.
* Pruebas de autorización.
* Aislamiento multiempresa.
* Aislamiento multisede.
* Concurrencia e idempotencia.
* Ejecutar primero pruebas relacionadas.
* No cambiar una prueba correcta solo para ocultar un error.

## seguridad.md

Incluye:

* Autorización backend.
* Prevención de acceso cruzado entre empresas.
* Protección de secretos.
* Validación de entradas.
* Rate limiting cuando corresponda.
* Auditoría de acciones críticas.
* No registrar datos sensibles innecesariamente.
* Prevención de asignación masiva insegura.
* Revisión de dependencias.
* Principio de mínimo privilegio.

# SKILLS QUE DEBES CREAR

Cada skill debe tener un `SKILL.md` válido, una descripción precisa y un procedimiento reutilizable. Su contenido solamente debe cargarse cuando la skill se invoque o sea relevante. Con Claude Code 2.1.1+, cada carpeta en `.claude/skills/<nombre>/` se registra automáticamente como `/<nombre>` — confirma la versión instalada antes de asumir que esto funciona así.

## /crear-modulo

Debe indicar a Claude que:

1. Lea `CLAUDE.md`.
2. Lea `docs/MODULO-ACTUAL.md`.
3. Consulte únicamente la documentación relacionada.
4. Inspeccione el código existente.
5. Identifique preguntas o decisiones bloqueantes.
6. Defina alcance y fuera de alcance.
7. Presente un plan.
8. Espere aprobación para cambios de arquitectura importantes.
9. Implemente verticalmente una función completa.
10. Cree pruebas.
11. Ejecute pruebas relacionadas.
12. Actualice la documentación.

## /revisar-modulo

Debe revisar:

* Cumplimiento del alcance.
* Seguridad.
* Aislamiento multiempresa.
* Aislamiento multisede.
* Permisos.
* Transacciones.
* Concurrencia.
* Rendimiento.
* Consultas N+1.
* Experiencia táctil.
* Pruebas faltantes.
* Código duplicado.
* Cambios innecesarios.

Debe entregar hallazgos clasificados como críticos, altos, medios y bajos. No debe modificar código salvo que el usuario lo solicite.

## /migracion-segura

Debe revisar:

* Compatibilidad con datos existentes.
* Llaves foráneas.
* Índices.
* Restricciones únicas.
* Tenant y sede.
* Valores predeterminados.
* Campos nulos.
* Reversión.
* Bloqueos de tabla.
* Riesgo durante despliegue.
* Backfill de información.
* Posibilidad de ejecutar la migración sin interrumpir producción.

## /cerrar-tarea

Debe:

1. Revisar los cambios.
2. Ejecutar pruebas relacionadas.
3. Ejecutar formateadores correspondientes.
4. Mostrar pruebas ejecutadas y resultado.
5. Actualizar `docs/MODULO-ACTUAL.md`.
6. Actualizar `docs/DECISIONES.md` si corresponde.
7. Comprobar que no se incluyeron funciones fuera de alcance.
8. Preparar un resumen para commit.
9. No ejecutar `git push`.
10. No crear commits automáticamente salvo autorización.

## /investigar-error

Debe:

1. Reproducir el problema.
2. Obtener evidencia.
3. Reducir el problema al componente responsable.
4. Revisar logs de forma focalizada.
5. Identificar causa raíz.
6. Proponer la corrección mínima.
7. No reescribir módulos completos.
8. Crear una prueba que reproduzca el error.
9. Aplicar la solución solo si fue solicitada.
10. Confirmar que no produjo regresiones relacionadas.

# CONFIGURACIÓN Y SEGURIDAD

Crea `.claude/settings.json` únicamente con configuraciones seguras y compatibles con la versión instalada de Claude Code. Usa el formato real de permisos (`permissions.allow` / `permissions.ask` / `permissions.deny`, evaluados en ese orden: deny siempre gana). Ejemplo de punto de partida — **verifica cada patrón contra `/docs/en/permissions` de tu versión instalada antes de darlo por válido**:

```json
{
  "permissions": {
    "allow": [
      "Bash(git status)",
      "Bash(git diff:*)",
      "Bash(git log:*)",
      "Bash(php artisan test:*)",
      "Bash(./vendor/bin/pest:*)",
      "Bash(npm run test:*)"
    ],
    "ask": [
      "Bash(composer install:*)",
      "Bash(composer require:*)",
      "Bash(npm install:*)",
      "Bash(php artisan make:*)"
    ],
    "deny": [
      "Bash(git push:*)",
      "Bash(rm -rf:*)",
      "Bash(php artisan migrate:fresh:*)",
      "Bash(php artisan migrate:reset:*)",
      "Bash(php artisan db:wipe:*)",
      "Edit(.env)",
      "Read(.env)"
    ]
  }
}
```

No actives permisos amplios o peligrosos. Las reglas `deny` de arriba son el mecanismo real de bloqueo — las prohibiciones escritas como texto en `CLAUDE.md` son un respaldo legible, no el control principal.

Si no puedes verificar que una propiedad de configuración es válida, no la inventes. Documenta la recomendación en lugar de agregarla.

Crea `CLAUDE.local.md.example` para preferencias locales, pero no incluyas:

* Contraseñas.
* Tokens.
* Credenciales.
* Datos reales de clientes.
* Rutas privadas innecesarias.
* Información sensible.

Comprueba si `CLAUDE.local.md` debe agregarse a `.gitignore`. Conserva todas las reglas existentes del archivo.

# EFICIENCIA Y AHORRO DE TOKENS

Configura las instrucciones para que Claude:

* No lea todo el repositorio cuando no sea necesario.
* Empiece por `CLAUDE.md` y `docs/MODULO-ACTUAL.md`.
* Use búsquedas específicas antes de abrir archivos completos.
* No lea `vendor`, `node_modules`, cachés o builds.
* Ejecute pruebas relacionadas antes que toda la suite.
* Resuma logs extensos.
* Evite repetir documentación ya existente.
* Use skills para procedimientos largos.
* Actualice la memoria documental al terminar.
* Recomiende `/compact` cuando el contexto crezca demasiado.
* Recomiende iniciar una sesión nueva cuando cambie completamente de módulo.
* No use subagentes para tareas pequeñas.
* Use subagentes solamente para investigaciones grandes, revisión aislada o análisis de logs extensos.
* No ejecute varios subagentes que estudien exactamente lo mismo.
* Verifique que cada archivo en `.claude/rules/` tenga `paths:` en su frontmatter (salvo `seguridad.md`, ver excepción arriba) — sin esto no hay ahorro real de contexto.

# REGLAS DE IMPLEMENTACIÓN

Durante esta tarea:

* No desarrolles todavía módulos del POS.
* No cambies modelos, controladores, migraciones ni componentes funcionales.
* No instales dependencias.
* No ejecutes migraciones.
* No borres archivos existentes.
* No cambies secretos.
* No publiques ni despliegues.
* No hagas `git push`.
* No realices una refactorización general.
* Limítate a crear o mejorar la estructura de instrucciones, reglas, skills y documentación.
* Si encuentras cambios del usuario sin confirmar, consérvalos.
* Si tienes dudas menores, utiliza supuestos explícitos y márcalos como pendientes.
* Si una duda cambia significativamente la arquitectura, detente y pregúntame.

# VERIFICACIÓN FINAL

Cuando termines:

1. Muestra el árbol de archivos creados o modificados.
2. Explica brevemente la función de cada archivo.
3. Verifica que los archivos Markdown sean coherentes.
4. Comprueba que los `SKILL.md` tengan una estructura válida.
5. Comprueba que `settings.json` sea JSON válido y que sus reglas `deny` cubran al menos: `git push`, edición de `.env`, y comandos destructivos de base de datos.
6. Comprueba que cada archivo en `.claude/rules/` tenga `paths:` en su frontmatter, salvo `seguridad.md`.
7. Indica qué información fue detectada realmente y qué quedó como propuesta.
8. Muestra cualquier decisión que necesite mi aprobación, incluyendo DEC-001 (cliente piloto).
9. Indica cómo utilizar:

   * `/memory`
   * `/crear-modulo`
   * `/revisar-modulo`
   * `/migracion-segura`
   * `/cerrar-tarea`
   * `/investigar-error`
10. Sugiere el primer módulo pequeño que debería construirse.
11. No comiences a programar ese módulo hasta que yo lo autorice.

Empieza ahora inspeccionando el repositorio.
