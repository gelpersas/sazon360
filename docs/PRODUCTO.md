# Producto — Sazón360

> Estado del proyecto: repositorio nuevo, sin código todavía (ver [DECISIONES.md](DECISIONES.md) DEC-000). Todo lo aquí descrito es la visión de producto acordada en el prompt de arranque; nada de esto está implementado aún.
>
> **Cliente piloto: Pastelería Dulcita** (ver [DECISIONES.md](DECISIONES.md) DEC-001). Es pastelería, no solo "cafetería" — al construir Productos/Pedidos, validar si necesita pedidos por encargo con fecha futura de entrega (no contemplado en el alcance original, a confirmar con el usuario cuando se llegue a ese módulo).

## Problema que resuelve

Cafeterías y restaurantes con una o varias sedes necesitan un POS táctil que, además de cobrar, controle turnos, caja, comandas por área, inventario por receta y reportes centralizados — sin depender de hojas de cálculo ni de sistemas que solo cubren captura de pedidos.

## Tipos de clientes

- Negocios de una sola sede (cafetería, restaurante pequeño).
- Cadenas con múltiples sedes bajo una misma empresa.
- Operadores con más de una empresa/marca en la misma plataforma (uso SaaS multiempresa).

## Usuarios del sistema

- **Administración central**: dueño(s) u operaciones corporativas — ve y administra todas las sedes de su(s) empresa(s).
- **Administración de sede**: gerente de una sede específica.
- **Caja**: apertura/cierre de caja, cobro, medios de pago.
- **Mesero**: toma de pedidos, gestión de mesas, división/unión de cuentas.
- **Área de preparación** (cocina, barra, panadería, etc.): recibe comandas de su área vía KDS o impresión.

Ver [GLOSARIO.md](GLOSARIO.md) para las diferencias exactas entre estos roles y los conceptos de empresa/sede/caja/turno.

## Alcance del MVP

1. Empresas y sedes.
2. Usuarios, roles y permisos.
3. Productos, categorías y precios.
4. Pisos, zonas y mesas.
5. Apertura y cierre de caja.
6. Creación de pedidos.
7. Comandas por área.
8. Cocina o KDS — **requiere tiempo real**; el MVP usa polling temporal (cada 3-5s) y migra a WebSockets (Reverb) en una fase posterior, ya confirmado (ver [DECISIONES.md](DECISIONES.md) DEC-002).
9. Cobro y medios de pago.
10. Reportes operativos básicos.

## Funciones posteriores (post-MVP, no en el orden inicial)

- Recetas e inventario por ingredientes (considerar adelantar — ver [ROADMAP.md](ROADMAP.md), es diferenciador frente a competencia).
- Compras, proveedores, producción, desperdicios y mermas.
- Traslados de inventario entre sedes.
- Clientes, fidelización y promociones.
- Facturación electrónica e integraciones (considerar adelantar, mismo motivo).
- Reportes y administración central avanzada.
- Turnos y asistencia de empleados (más allá del control básico de caja).

## Expresamente fuera del MVP

- Contabilidad completa.
- Nómina.
- Inteligencia artificial.
- Domicilios avanzados (ruteo, tracking de repartidores, etc.).
- Marketplace.

## Roles operativos — diferencias clave

| Rol | Ve | Puede |
|---|---|---|
| Administración central | Todas las empresas/sedes a las que tiene acceso | Configurar empresas, sedes, planes, usuarios globales, ver reportes consolidados |
| Administración de sede | Su sede | Configurar productos/mesas/turnos de su sede, autorizar anulaciones y descuentos |
| Caja | Su turno de caja | Abrir/cerrar caja, cobrar, registrar medios de pago |
| Mesero | Mesas/pedidos asignados | Crear pedidos, dividir/unir cuentas, enviar comandas |
| Área de preparación | Comandas de su área (KDS o impresas) | Marcar preparación/listo, no ve el resto del pedido salvo lo relevante a su área |

## Decisiones relacionadas (ya resueltas)

- DEC-001 (cliente piloto: Pastelería Dulcita), DEC-002 (polling → WebSockets), DEC-003 (versiones PHP/Node) — ver [DECISIONES.md](DECISIONES.md).
