# Módulo actual

> Actualizar este archivo al finalizar cada sesión o tarea importante. Es el primer archivo (junto con `CLAUDE.md`) que debe leerse al empezar una sesión nueva.

## Nombre del módulo actual

Paleta "Moka Contraste" (DEC-070) — ajuste visual de color en modo claro del POS y del color primario de Filament, elegido por el usuario entre 4 opciones mostradas en un comparador visual.

## Objetivo

El usuario pidió mejor contraste de color, "un poco más oscuro", "más atractivo y moderno", pidiendo sugerencias concretas. Se armó un comparador visual (Artifact) con la paleta actual + 3 alternativas sobre los mismos componentes reales del POS, y el usuario eligió "Moka Contraste".

## Alcance incluido (DEC-070)

Ver DEC-070 para el detalle completo. Resumen: nuevos tokens de color en modo claro de `resources/css/pos.css` (`bg`/`surface-soft`/`text`/`text-muted`/`primary`/`primary-hover`/`border`/`warning`/`warning-soft`) y nuevo `primary` en `AdminPanelProvider.php` (mismo hex que el POS, para mantener la marca consistente entre panel y POS táctil, como ya era desde DEC-054). Modo oscuro del POS y resto de colores semánticos de Filament (`gray`/`danger`/`warning`/`success`) sin cambios.

## Fuera de alcance (a propósito)

Modo oscuro del POS (no se tocó). `gray`/`danger`/`warning`/`success` de Filament (solo cambió `primary`). `success`/`danger` del POS en modo claro (no mostrados en el comparador, no pedidos).

## Reglas relacionadas

Ninguna nueva.

## Archivos relacionados

`resources/css/pos.css`, `app/Providers/Filament/AdminPanelProvider.php`, `docs/DECISIONES.md` (DEC-070).

## Trabajo terminado

Implementado y verificado de punta a punta. `composer test`: 191/191 en verde (sin tests nuevos, cambio de CSS/config puro). `composer lint`: verde. `npm run build`: verde. Verificado con Playwright real (instalado/desinstalado) contra la sede real de Dulcita: POS en modo claro con la paleta nueva, panel Filament en modo oscuro y claro con el nuevo `primary` visible en la navegación activa. Cero errores de consola.

## Trabajo pendiente

Ninguno bloqueante para este módulo. Con esto quedan resueltas las 3 tareas de la última ronda (edición de usuario DEC-068, impresión térmica DEC-069, paleta DEC-070).

## Pruebas ejecutadas

- `composer test` (Pest): 191/191 passed (sin tests nuevos).
- `composer lint` (Pint): verde.
- `npm run build`: verde.
- Verificación manual con Playwright (temporal, instalado/desinstalado) contra datos reales de Dulcita, en ambos modos (claro/oscuro) del panel admin.

## Errores conocidos

Ninguno abierto.

## Decisiones pendientes

Ninguna bloqueante para este módulo.

## Próxima acción exacta

Esperar instrucción del usuario. Pendientes de fondo sin relación, bloqueados sin su input: funcionamiento offline (la única de las 4 features originales sin abordar), Fase 6 (impuesto DIAN reales de Dulcita), DEC-042 (POS electrónico, nota crédito/débito), credenciales `FACTUS_*` en producción, y probar la impresión térmica (DEC-069) contra una impresora física real cuando Dulcita tenga la IP pública/reenvío de puertos configurado.
