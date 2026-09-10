---
paths:
  - "resources/js/pos/**/*.vue"
  - "resources/js/pos/**/*.js"
---

# Reglas del frontend POS (Vue PWA) — Sazón360

> El POS táctil vive en `resources/js/pos/` dentro del mismo proyecto Laravel (Vite, segundo entry point), no en un paquete Node separado — ver `docs/DECISIONES.md` DEC-004. Desde la Fase 4 es una app funcional: Vue 3 + vue-router + Pinia + axios, autenticada contra `/api/pos/*` con Laravel Sanctum SPA (DEC-011), servida vía `resources/views/pos.blade.php` en `GET /pos/{cualquiera?}` (rutas cliente: `/pos/login`, `/pos/sede`, `/pos`, `/pos/kds`).

- Interfaz táctil: botones grandes, objetivos de toque amplios, texto legible a distancia de mostrador.
- Pocos pasos para completar una acción común (tomar pedido, cobrar) — evita flujos con múltiples pantallas intermedias para tareas frecuentes.
- Estados siempre visibles (mesa ocupada/libre, comanda enviada/pendiente, conexión activa/perdida).
- Prevención de doble toque: deshabilita el control o usa debounce mientras una acción está en curso (evita duplicar pedidos, comandas o cobros).
- Maneja explícitamente los estados de espera, error y reconexión — el usuario siempre debe saber si su acción se envió, falló o está reintentando.
- Diseño responsive y accesible (contraste suficiente, tamaños de fuente ajustables, soporte básico de teclado/lector si aplica).
- Operaciones idempotentes: reintentos por pérdida de conexión no deben duplicar pedidos, comandas ni cobros (ver `docs/REGLAS-NEGOCIO.md`).
- No confiar en el frontend para seguridad: cualquier validación de permisos o aislamiento de empresa/sede debe repetirse en el backend, el frontend solo mejora la UX.
- Separa claramente estado local (UI, borradores no enviados), estado del servidor (datos confirmados) y eventos en tiempo real (KDS, notificaciones) — no mezclarlos en un mismo store sin distinción.
