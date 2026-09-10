# Módulo actual

> Actualizar este archivo al finalizar cada sesión o tarea importante. Es el primer archivo (junto con `CLAUDE.md`) que debe leerse al empezar una sesión nueva.

## Nombre del módulo actual

Primer despliegue a producción (DEC-066) — repositorio Git publicado en GitHub, Dockerfile para EasyPanel (VPS/Docker, ver DEC-002/003) y fix de `trustProxies` para que el POS/panel admin carguen bien detrás del proxy.

## Objetivo

El usuario pidió subir Sazón360 a un repositorio Git y desplegarlo en EasyPanel. El repositorio no tenía `git init` ni Dockerfile todavía. El despliegue real (crear los servicios, dar "Implementar", leer logs de build) lo operó el usuario a través de **Claude para Chrome** contra la UI de EasyPanel — esta sesión (Claude Code) preparó el repo, escribió el Dockerfile y corrigió cada error real que Claude para Chrome fue reportando, de forma iterativa.

## Alcance incluido (DEC-066)

Ver DEC-066 para el detalle completo. Resumen: `git init` + primer commit + push a `https://github.com/gelpersas/ControlPrintIA.git` (rama `main`); `Dockerfile` multi-stage (Node 22 para Vite + PHP 8.3-FPM/Nginx/supervisord), una sola imagen reutilizada por los 3 procesos (`web`/`reverb`/`queue-worker`, estos dos últimos solo cambian el comando de arranque en EasyPanel); 3 rondas de corrección contra errores reales de build (`vendor/` ausente en el stage de Vite, extensiones PHP `intl`/`zip` faltantes, `trustProxies` para que los assets no se pidan por `http://` detrás del proxy).

## Fuera de alcance (a propósito)

Nada quedó deliberadamente fuera — el ciclo se hizo completo hasta que los 3 servicios respondieron correctamente en producción.

## Reglas relacionadas

Ninguna nueva. Se respetó el bloqueo de `git push`/edición de `.env` de `.claude/settings.json` en todo momento salvo `git push`, que el usuario pidió explícitamente desbloquear (sigue desbloqueado, ver DEC-066 — decisión pendiente de si se vuelve a bloquear).

## Archivos relacionados

`Dockerfile`, `docker/nginx.conf`, `docker/supervisord.conf`, `.dockerignore`, `bootstrap/app.php` (+`trustProxies`), `.claude/settings.json` (`git push` desbloqueado), `docs/DECISIONES.md` (DEC-066).

## Trabajo terminado

Los 3 servicios (`web`, `reverb`, `queue-worker`) corriendo en EasyPanel; `migrate --force` y `storage:link` aplicados contra la base de datos real de producción; `composer test` (175/175) y `composer lint` en verde en cada commit. `/admin/login` y `/pos/login` responden 200; el fix de `trustProxies` para que carguen con CSS/JS por HTTPS quedó pusheado — verificación final de esa última corrección en curso por parte del usuario/Claude para Chrome al cerrar esta entrada.

## Trabajo pendiente

- Confirmar (usuario/Claude para Chrome) que `/admin` y `/pos` cargan con estilos tras el fix de `trustProxies`.
- Decidir si se vuelve a bloquear `git push` en `.claude/settings.json` ahora que el despliegue inicial terminó.
- El usuario pegó un token de GitHub (PAT) en el chat — se le recomendó revocarlo/regenerarlo; no confirmado si ya lo hizo.
- Variables de entorno de producción reales (Factus, mail, etc.) — no las tiene esta sesión, las cargó el usuario directamente en EasyPanel.

## Pruebas ejecutadas

- `composer test` (Pest): 175/175 passed, tras cada cambio de código (`trustProxies`).
- `composer lint` (Pint): verde en cada commit.
- Verificación en producción real (no local): logs de build de EasyPanel, respuestas HTTP de `/admin/login` y `/pos/login`, reportados por el usuario vía Claude para Chrome.

## Errores conocidos

Ninguno abierto a la espera de la confirmación final del fix de `trustProxies`.

## Decisiones pendientes

Si se debe re-bloquear `git push` en `.claude/settings.json` — preguntar al usuario en la próxima sesión si no se resuelve antes.

## Próxima acción exacta

Esperar confirmación de que `/admin` y `/pos` cargan correctamente en producción tras el último push. Pendientes de fondo sin relación, bloqueados sin input del usuario: Fase 6 (impuesto DIAN reales de los productos de Dulcita), y los 2 puntos restantes de DEC-042 (POS electrónico, nota crédito/débito).
