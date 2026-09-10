---
paths:
  - "tests/**/*.php"
---

# Reglas de pruebas — Sazón360

> Nota: el repositorio aún no tiene suite de pruebas (ver `docs/DECISIONES.md` DEC-000). Pest está previsto como framework de pruebas, sin instalar todavía.

- Framework: Pest.
- Pruebas unitarias para reglas de negocio puras (cálculo de totales, validaciones de estado) que no requieran base de datos ni HTTP.
- Pruebas de integración para flujos críticos completos (abrir caja → vender → cerrar caja; crear pedido → enviar comanda → cobrar).
- Pruebas de autorización: verificar explícitamente que un usuario sin permiso no puede ejecutar una acción sensible.
- Aislamiento multiempresa: al menos un test que confirme que datos de una empresa no son visibles/editables desde otra.
- Aislamiento multisede: al menos un test que confirme que datos de una sede no son visibles/editables desde otra sede de la misma empresa.
- Concurrencia e idempotencia: pruebas para operaciones críticas repetidas (ej. enviar la misma comanda dos veces, cerrar caja dos veces) que confirmen que no se duplican efectos.
- Ejecuta primero las pruebas relacionadas con el módulo que se está tocando, no toda la suite, salvo antes de cerrar una tarea (`/cerrar-tarea` sí corre lo relacionado y lo reporta).
- No cambies una prueba que está correctamente detectando un error solo para que pase — corrige el código, o si la prueba está mal planteada, dilo explícitamente y pide confirmación antes de modificarla.
