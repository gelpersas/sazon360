---
name: crear-modulo
description: Implementar verticalmente un módulo o función nueva de Sazón360, desde el plan hasta pruebas y documentación actualizada. Úsalo cuando el usuario pida construir/agregar un módulo o función funcional del POS (no para tareas de investigación o revisión).
---

# /crear-modulo

Procedimiento para construir un módulo o función funcional de Sazón360, un módulo a la vez.

1. Lee `CLAUDE.md`.
2. Lee `docs/MODULO-ACTUAL.md`.
3. Consulta únicamente la documentación relacionada con el módulo pedido (no releas todo `docs/` de punta a punta) — típicamente: `docs/REGLAS-NEGOCIO.md` (sección del módulo), `docs/MODELO-DATOS.md` (entidades del módulo), y `docs/DECISIONES.md` si hay una decisión pendiente que lo afecte.
4. Inspecciona el código existente relevante al módulo (búsquedas específicas, no lectura completa del repo).
5. Identifica preguntas o decisiones bloqueantes (ej. una decisión pendiente en `docs/DECISIONES.md` que el módulo necesita resuelta). Si existe una, pregunta antes de continuar en vez de asumir.
6. Define explícitamente alcance y fuera de alcance de esta tarea — escríbelo antes de tocar código.
7. Presenta un plan breve (qué se va a crear/modificar, qué reglas de `.claude/rules/` aplican).
8. Espera aprobación explícita del usuario antes de cambios de arquitectura importantes (nuevas tablas centrales, cambios al modelo de tenant, nuevas dependencias).
9. Implementa verticalmente: una función completa de extremo a extremo (modelo/migración → lógica de negocio → autorización → UI mínima necesaria), no fragmentos sueltos ni funciones especulativas fuera del alcance definido.
10. Crea pruebas (Pest) para la lógica nueva, incluyendo casos de aislamiento multiempresa/multisede si el módulo maneja datos operativos.
11. Ejecuta las pruebas relacionadas con el módulo (no toda la suite).
12. Actualiza la documentación: `docs/MODULO-ACTUAL.md` siempre; `docs/MODELO-DATOS.md`/`docs/REGLAS-NEGOCIO.md` si el módulo introdujo entidades o reglas nuevas que antes eran solo "propuesta".

No implementes funciones fuera del alcance definido en el paso 6, aunque parezcan naturales de agregar — regístralas como pendientes en `docs/MODULO-ACTUAL.md` en vez de construirlas sin pedir.
