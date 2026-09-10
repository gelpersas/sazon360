# Módulo actual

> Actualizar este archivo al finalizar cada sesión o tarea importante. Es el primer archivo (junto con `CLAUDE.md`) que debe leerse al empezar una sesión nueva.

## Nombre del módulo actual

Impresión térmica por red (DEC-069) — configuración de impresora por IP/puerto en `AreaPreparacion`, disparada al enviar una comanda, conviviendo con el KDS digital. Segundo y último de los dos módulos elegidos por el usuario (el primero, edición de perfil, ya está en DEC-068).

## Objetivo

El usuario pidió un módulo para configurar impresora por puerto y área, con impresión directa sin pedir nada. La investigación encontró que `docs/ARQUITECTURA.md` ya había anticipado el problema real (backend en VPS remoto, impresoras en red local de Dulcita) sin resolverlo — este módulo lo resuelve.

## Alcance incluido (DEC-069)

Ver DEC-069 para el detalle completo. Resumen: en vez de un "agente local" (lo que `ARQUITECTURA.md` había anticipado), Dulcita usa una IP pública con reenvío de puertos restringido a la IP del VPS (confirmado con el usuario) — el backend se conecta directo. `AreaPreparacion.impresora_ip`/`impresora_puerto` (nullable, configurable en Filament). `app/Listeners/ImprimirComandaAlEnviar.php` escucha el mismo evento que ya notifica al KDS y despacha `ImprimirComandaJob` (en cola, con reintentos nativos) solo cuando la comanda es nueva (`Pendiente`) y el área tiene impresora. `app/Services/Impresion/TicketComanda.php` construye el ticket ESC/POS (`mike42/escpos-php`, dependencia nueva). La impresión convive con el KDS, nunca lo reemplaza.

## Fuera de alcance (a propósito)

Un "agente local" de impresión (descartado a favor de IP pública, ver DEC-069). Reintentos/alertas visibles en la UI si la impresora falla (el usuario confirmó que reintentar en silencio es suficiente). Imprimir en cada avance de estado del KDS (solo se imprime una vez, al crear la comanda).

## Reglas relacionadas

Sigue `.claude/rules/laravel.md` al pie de la letra: "impresión" es el ejemplo textual que ya pedía Eventos+Listeners (no llamadas directas) y Jobs con cola (no bloquear la respuesta).

## Archivos relacionados

`database/migrations/2026_09_10_170000_add_impresora_a_area_preparacions_table.php`, `app/Models/AreaPreparacion.php`, `app/Filament/Resources/AreaPreparacions/Schemas/AreaPreparacionForm.php`, `app/Services/Impresion/TicketComanda.php`, `app/Jobs/ImprimirComandaJob.php`, `app/Listeners/ImprimirComandaAlEnviar.php`, `tests/Feature/ImpresionComandaTest.php`, `composer.json` (+`mike42/escpos-php`), `docs/DECISIONES.md` (DEC-069).

## Trabajo terminado

Implementado y verificado de punta a punta. `composer test`: 191/191 en verde (185 previos + 6 nuevos). `composer lint`: verde. Verificado con Playwright real (formulario con los campos nuevos, edición real guardada) y un flujo end-to-end real vía `tinker` (comanda real → `ImprimirComandaJob` encolado en la tabla `jobs` real, sin poder probar contra una impresora física real). Datos de prueba revertidos al terminar.

## Trabajo pendiente

- **No probado contra una impresora física real** — no hay ninguna disponible en este entorno de desarrollo. Cuando Dulcita tenga la IP pública/reenvío de puertos configurado en su router, hay que confirmar con una impresora real que el ticket se imprime correctamente (formato, corte de papel, codificación de caracteres).
- Con eso resueltos los 2 módulos que el usuario eligió de la consulta de prioridades. Quedan sin abordar (no pedidos todavía): funcionamiento offline, y los bloqueados esperando datos del usuario (Fase 6 DIAN, DEC-042, credenciales `FACTUS_*`).

## Pruebas ejecutadas

- `composer test` (Pest): 191/191 passed, 581 assertions.
- `composer lint` (Pint): verde.
- Verificación manual con Playwright (temporal, instalado/desinstalado) + `tinker` contra datos reales de Dulcita.

## Errores conocidos

Ninguno abierto. No probado contra hardware real (ver "Trabajo pendiente").

## Decisiones pendientes

Ninguna bloqueante para este módulo.

## Próxima acción exacta

Esperar a que Dulcita configure la IP pública/reenvío de puertos en su router para probar contra una impresora real. Mientras tanto, esperar instrucción del usuario sobre qué sigue — el offline es la única de las 4 features originales sin abordar; los demás pendientes de fondo (Fase 6 DIAN, DEC-042, `FACTUS_*`) siguen bloqueados esperando datos del usuario.
