---
paths:
  - "app/**/*.php"
  - "routes/**/*.php"
---

# Reglas de Laravel — Sazón360

> Nota: el repositorio aún no tiene app Laravel (ver `docs/DECISIONES.md` DEC-000). Estas reglas se aplican desde el momento en que exista código en `app/` o `routes/`.

- Sigue las convenciones que ya existan en el proyecto una vez creado (naming, estructura de carpetas) — no las reinventes archivo por archivo.
- Controladores delgados: la lógica de negocio va en servicios/acciones, no en el controlador.
- Usa Form Requests para validación de entrada cuando la validación tenga más de un par de reglas simples.
- Lógica de negocio (cálculos, reglas de dominio) en clases de servicio o "acciones" dedicadas, no en modelos Eloquent ni controladores.
- Autorización mediante Policies, no `if` sueltos comprobando roles dentro de controladores.
- Procesos desacoplados (ej. notificar a KDS, disparar impresión) vía Eventos + Listeners, no llamadas directas encadenadas.
- Tareas largas o que no deben bloquear la respuesta (impresión, reportes pesados, notificaciones) van en Jobs con cola.
- Operaciones críticas (cobro, cierre de caja, anulaciones) dentro de transacciones de base de datos (`DB::transaction`).
- Evita consultas N+1: usa `with()`/`load()` explícito al iterar relaciones; revisa con el profiler o `DB::listen` en desarrollo si hay dudas.
- Tipado estricto (`declare(strict_types=1)` donde el proyecto lo adopte) y nombres consistentes con el dominio del negocio (español para conceptos de negocio si el resto del proyecto ya lo hace así, para no mezclar idiomas en el mismo concepto).
