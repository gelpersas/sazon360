---
name: revisar-modulo
description: Revisar un módulo o cambio existente de Sazón360 contra alcance, seguridad, aislamiento multiempresa/sede, permisos, transacciones, rendimiento y pruebas — sin modificar código. Úsalo cuando el usuario pida revisar, auditar o dar el visto bueno a código ya escrito.
---

# /revisar-modulo

Revisión de solo lectura. No modifiques código salvo que el usuario lo pida explícitamente después de ver los hallazgos.

Revisa:

- Cumplimiento del alcance definido para el módulo (vs. `docs/MODULO-ACTUAL.md` o el plan acordado).
- Seguridad (ver `.claude/rules/seguridad.md`): autorización backend, protección de secretos, validación de entradas.
- Aislamiento multiempresa: ¿algún query puede devolver o modificar datos de otra empresa?
- Aislamiento multisede: ¿algún query puede devolver o modificar datos de otra sede de la misma empresa?
- Permisos: ¿las acciones sensibles están protegidas por Policy/autorización real, no solo ocultas en la UI?
- Transacciones: ¿las operaciones críticas (caja, cobro, inventario, anulaciones) están dentro de `DB::transaction`?
- Concurrencia: ¿hay riesgo de condición de carrera (dos cierres de caja, dos descuentos de stock simultáneos) sin bloqueo?
- Rendimiento y consultas N+1.
- Experiencia táctil (si aplica al POS Vue): pasos mínimos, prevención de doble toque, estados visibles.
- Pruebas faltantes para los casos críticos del módulo.
- Código duplicado que debería reutilizar algo existente.
- Cambios innecesarios o fuera del alcance del módulo revisado.

Entrega los hallazgos clasificados como **críticos**, **altos**, **medios** y **bajos**, con archivo y línea cuando sea posible. No apliques correcciones por tu cuenta.
