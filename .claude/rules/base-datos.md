---
paths:
  - "database/**/*.php"
  - "app/Models/**/*.php"
---

# Reglas de base de datos — Sazón360

> Nota: el repositorio aún no tiene migraciones ni modelos (ver `docs/DECISIONES.md` DEC-000). PostgreSQL 18.2 está disponible en Laragon localmente (verificado), sin base de datos del proyecto creada todavía.

- Motor: PostgreSQL. No asumas sintaxis o funciones específicas de MySQL.
- Toda relación debe declarar llave foránea real (constraint), no solo una columna `*_id` sin `foreign()`.
- Índices en toda columna usada para filtrar/ordenar con frecuencia, especialmente `empresa_id` y `sede_id`.
- Restricciones únicas (`unique()`) donde el negocio lo exija (ej. nombre de sede único dentro de una empresa) — no confiar solo en validación de aplicación para invariantes de datos.
- Valores monetarios en tipo seguro (enteros en la unidad mínima o `decimal` con precisión fija) — nunca `float`/`double` para dinero.
- Fechas y horas en UTC en base de datos; conversión a zona horaria de la sede/empresa en la capa de presentación.
- Operaciones críticas (cierre de caja, cobro, anulaciones, movimientos de inventario) dentro de transacciones (`DB::transaction`), con bloqueo (`lockForUpdate()`) donde haya riesgo real de concurrencia (ej. cierre de caja, descuento de inventario).
- Soft delete (`deleted_at`) solo donde tenga sentido de negocio (ej. no debería usarse en comandas, que se anulan con trazabilidad en vez de "borrarse" — ver `docs/REGLAS-NEGOCIO.md`); no lo apliques por defecto a toda tabla.
- Auditoría: las tablas que registran acciones sensibles (anulaciones, descuentos, cambios de precio, movimientos de caja/inventario) deben guardar quién y cuándo, no solo el resultado final.
- Toda tabla operativa debe tener `empresa_id` y, cuando aplique, `sede_id`, con índice — nunca confiar en el scoping hecho solo desde la aplicación sin respaldo de constraint/índice.
- Las migraciones deben ser compatibles con producción: evita operaciones bloqueantes largas sobre tablas con datos (ver skill `/migracion-segura` antes de escribir una migración sobre una tabla que ya tenga datos reales).
