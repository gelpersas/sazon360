---
paths:
  - "app/Filament/**/*.php"
---

# Reglas de Filament — Sazón360

> Nota: el repositorio aún no tiene Filament instalado (ver `docs/DECISIONES.md` DEC-000). Estas reglas se aplican desde que exista código en `app/Filament/`.

- Filament es solamente para administración (empresa, sede, catálogo, usuarios, reportes) — no construyas el POS táctil como un conjunto improvisado de Resources de Filament; el POS táctil es la app Vue PWA (ver `frontend-pos.md`).
- Todo Resource que liste/edite datos operativos debe filtrar por empresa y, cuando corresponda, por sede del usuario autenticado — nunca mostrar datos de otra empresa/sede aunque el usuario adivine un ID.
- Acciones sensibles (anulación, descuento, reapertura de caja/pedido, cambio de precio) requieren `authorize()`/Policy explícita y confirmación en la UI antes de ejecutarse.
- Formularios y tablas deben seguir un patrón consistente con los Resources ya existentes en el proyecto (mismos componentes, mismo estilo de validación) — no introduzcas un patrón distinto sin justificarlo.
- Evita consultas costosas sin paginar/filtrar en dashboards y widgets — usa agregados cacheados o consultas acotadas por rango de fecha/sede en vez de cargar todo el histórico.
