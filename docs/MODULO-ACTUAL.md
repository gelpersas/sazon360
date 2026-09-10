# Módulo actual

> Actualizar este archivo al finalizar cada sesión o tarea importante. Es el primer archivo (junto con `CLAUDE.md`) que debe leerse al empezar una sesión nueva.

## Nombre del módulo actual

Edición de perfil de usuario (DEC-068) — nombre/correo/teléfono/documento de identidad editables desde `AccesoResource`. Primero de los dos módulos elegidos por el usuario; sigue impresión térmica por red.

## Objetivo

El usuario pidió poder editar nombre/email de un usuario ya existente y agregar campos de perfil (teléfono, documento de identidad) — hoy `User` solo tenía name/email/password/tema, y solo eran editables al CREAR un usuario nuevo, nunca después.

## Alcance incluido (DEC-068)

Ver DEC-068 para el detalle completo. Resumen: `users.telefono`/`users.documento_identidad` (migración aditiva); 4 campos nuevos en el modal de edición de `AccesoResource` (`AccesoForm.php`), visibles solo al editar, prellenados con los datos reales del usuario; se guardan sobre `$record->user` dentro de la misma transacción que ya reconcilia los roles. Sin `UserResource` propio (sigue vigente DEC-037 — `User` es un modelo global sin `empresa_id`).

## Fuera de alcance (a propósito)

Un `UserResource` independiente. Mostrar teléfono/documento como columnas en el listado (no se pidió). Cualquier campo de perfil más allá de teléfono y documento de identidad.

## Reglas relacionadas

Ninguna nueva. Sigue el patrón ya establecido de DEC-041 (modal, no página aparte) y DEC-037 (gestión de usuarios vía `AccesoResource`, nunca un Resource propio).

## Archivos relacionados

`database/migrations/2026_09_10_160000_add_telefono_y_documento_a_users_table.php`, `app/Models/User.php`, `app/Filament/Resources/Accesos/Schemas/AccesoForm.php`, `app/Filament/Resources/Accesos/Tables/AccesosTable.php`, `tests/Feature/AccesoResourceTest.php`, `docs/DECISIONES.md` (DEC-068).

## Trabajo terminado

Implementado y verificado de punta a punta. `composer test`: 185/185 en verde (182 previos + 3 nuevos). `composer lint`: verde. Verificado con Playwright real (instalado/desinstalado) contra la sede real de Dulcita: modal de edición con los 4 campos prellenados, edición real guardada y confirmada contra la base de datos, datos de prueba revertidos al terminar. Cero errores de consola.

## Trabajo pendiente

Ninguno bloqueante para este módulo. Sigue el segundo módulo elegido por el usuario: **impresión térmica por red** (configurar impresora por IP:puerto por área de preparación, envío directo sin pantalla intermedia) — subsistema completo, nada existe hoy (ver la investigación de la ronda anterior).

## Pruebas ejecutadas

- `composer test` (Pest): 185/185 passed, 568 assertions.
- `composer lint` (Pint): verde.
- Verificación manual con Playwright (temporal, instalado/desinstalado) contra datos reales de Dulcita.

## Errores conocidos

Ninguno abierto.

## Decisiones pendientes

Ninguna bloqueante para este módulo.

## Próxima acción exacta

Arrancar el módulo de impresión térmica por red (segundo elegido por el usuario): definir dónde vive la configuración de impresora por área (¿campo nuevo en `AreaPreparacion`, o tabla aparte?), qué protocolo exacto usar para hablar con la impresora (ESC/POS por socket TCP es lo estándar), y en qué punto del flujo se dispara la impresión (al enviar la comanda, mismo lugar que hoy notifica al KDS). Seguir `/crear-modulo` — hay decisiones de arquitectura que probablemente necesiten confirmación del usuario antes de programar (ej. si la impresión reemplaza o convive con el KDS digital, ya que el propio usuario mencionó ambas opciones). Pendientes de fondo sin relación: Fase 6 (impuesto DIAN reales de Dulcita), DEC-042 (POS electrónico, nota crédito/débito), credenciales `FACTUS_*` en producción.
