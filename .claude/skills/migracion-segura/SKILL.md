---
name: migracion-segura
description: Revisar una migración de base de datos de Sazón360 antes de aplicarla, evaluando compatibilidad con datos existentes, bloqueos, reversión y riesgo en producción. Úsalo antes de crear o ejecutar cualquier migración sobre una tabla que pueda tener datos reales.
---

# /migracion-segura

Revisión de una migración (nueva o propuesta) antes de aplicarla. No ejecutes `php artisan migrate` ni comandos destructivos (`migrate:fresh`, `migrate:reset`, `db:wipe`) — estos están además bloqueados en `.claude/settings.json`.

Revisa:

- Compatibilidad con datos existentes: ¿la migración puede fallar o corromper filas actuales?
- Llaves foráneas: ¿se declaran correctamente, con el `onDelete`/`onUpdate` adecuado?
- Índices: ¿las columnas nuevas usadas en filtros/joins tienen índice?
- Restricciones únicas: ¿son necesarias y no rompen datos ya existentes?
- Tenant y sede: ¿la tabla nueva/modificada respeta el aislamiento `empresa_id`/`sede_id` (ver `.claude/rules/base-datos.md`)?
- Valores predeterminados: ¿columnas nuevas `NOT NULL` en tablas con datos tienen `default` o backfill definido?
- Campos nulos: ¿es intencional que un campo permita NULL, o falta una restricción?
- Reversión: ¿el método `down()` realmente revierte el cambio sin pérdida de datos innecesaria?
- Bloqueos de tabla: ¿la operación (ej. agregar columna `NOT NULL` sin default en PostgreSQL, crear índice sin `CONCURRENTLY`) puede bloquear una tabla grande en producción?
- Riesgo durante despliegue: ¿requiere ventana de mantenimiento o puede aplicarse sin interrumpir el servicio?
- Backfill de información: si la migración requiere poblar datos derivados, ¿el backfill está definido y es seguro re-ejecutar (idempotente)?
- Posibilidad de ejecutar sin interrumpir producción: preferir cambios aditivos y en varios pasos sobre cambios destructivos de una sola vez.

Entrega la evaluación como lista de riesgos (si los hay) antes de que el usuario decida aplicar la migración.
