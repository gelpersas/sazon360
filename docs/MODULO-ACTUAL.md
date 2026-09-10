# Módulo actual

> Actualizar este archivo al finalizar cada sesión o tarea importante. Es el primer archivo (junto con `CLAUDE.md`) que debe leerse al empezar una sesión nueva.

## Nombre del módulo actual

Historial de ventas en Filament (DEC-067) — Resource "Ventas" con filtros/búsqueda, exportación Excel/CSV/PDF y recibo PDF por venta individual.

## Objetivo

Tras el despliegue a producción, el usuario consultó el estado de 4 features candidatas (reportes/export, edición de usuario extendida, impresión por red, offline) y pidió avanzar con la primera: "reportes y exportación filtros y búsquedas". La investigación encontró que no existía ningún Resource de Filament para ver el historial de ventas — solo "Pedidos abiertos" del POS (en vivo) y el "Reporte consolidado" (Fase 8, solo totales por sede) — exactamente el pendiente que DEC-024 había dejado anotado.

## Alcance incluido (DEC-067)

Ver DEC-067 para el detalle completo. Resumen: `VentaResource` (modelo `Pedido`, solo lectura, mismo patrón de scoping de sede que `CajaResource`) con tabla filtrable (rango de fecha, sede, estado, medio de pago) y búsqueda; exportación Excel/CSV nativa de Filament (`VentaExporter`, reutiliza `openspout` ya instalado); exportación PDF del listado filtrado y recibo PDF por venta individual (`barryvdh/laravel-dompdf`, dependencia nueva); dos `RelationManager` de solo lectura (ítems, pagos) en el detalle de cada venta.

## Fuera de alcance (a propósito)

Las otras 3 features consultadas en la ronda anterior (edición de usuario extendida, impresión térmica por red, funcionamiento offline) — quedan como pendientes de fondo, a elegir por el usuario cuál sigue.

## Reglas relacionadas

Ninguna nueva. Reutiliza el mecanismo nativo de exportación de Filament v4 (`ExportAction`/`Exporter`) en vez de construir uno paralelo.

## Archivos relacionados

`database/migrations/2026_09_10_150000_create_exports_table.php`, `app/Filament/Resources/Ventas/` (Resource completo), `app/Filament/Exports/VentaExporter.php`, `app/Support/ReciboPdf.php`, `resources/views/pdf/{recibo,ventas-listado}.blade.php`, `composer.json` (+`barryvdh/laravel-dompdf`), `tests/Feature/VentaResourceTest.php`, `tests/Feature/ReporteConsolidadoTest.php` (`venderEnSede()` ahora retorna el `Pedido`), `docs/DECISIONES.md` (DEC-067).

## Trabajo terminado

Implementado y verificado de punta a punta. `composer test`: 182/182 en verde (175 previos + 7 nuevos). `composer lint`: verde. `npm run build`: verde. Verificado con Playwright real (instalado/desinstalado) contra la sede real de Dulcita: listado, filtros, detalle con ítems/pagos, descarga real del recibo PDF y del PDF del listado (contenido verificado correcto), modal de exportación Excel/CSV de Filament abriendo correctamente. Durante la verificación se encontró y corrigió un bug real (no cosmético): las Actions de PDF no disparaban la descarga porque Livewire solo reconoce `StreamedResponse`/`BinaryFileResponse` como descarga, y `Pdf::download()` devuelve un `Response` plano — corregido envolviendo el contenido en `response()->streamDownload()`.

## Trabajo pendiente

Ninguno bloqueante para este módulo. Pendiente de decisión del usuario: cuál de las otras 3 features (edición de usuario extendida, impresión térmica por red, offline) abordar después.

## Pruebas ejecutadas

- `composer test` (Pest): 182/182 passed, 551 assertions.
- `composer lint` (Pint): verde.
- `npm run build`: verde.
- Verificación manual con Playwright (temporal, instalado/desinstalado) contra datos reales de Dulcita — incluida la descarga real de ambos PDFs y verificación de su contenido.

## Errores conocidos

Ninguno abierto. Nota aparte (no de este módulo): la sede real de Dulcita acumula datos de prueba de rondas anteriores de esta sesión (68 "ventas" en distintos estados) — visible ahora en el nuevo listado; no afecta la corrección del módulo, pero conviene que el usuario los revise si no corresponden a actividad real.

## Decisiones pendientes

Ninguna bloqueante para este módulo.

## Próxima acción exacta

Esperar instrucción del usuario sobre cuál de las 3 features restantes de la consulta anterior abordar (edición de usuario extendida, impresión térmica por red, offline) — o cualquier otra tarea. Pendientes de fondo sin relación: Fase 6 (impuesto DIAN reales de Dulcita), y los 2 puntos restantes de DEC-042 (POS electrónico, nota crédito/débito). Falta configurar `FACTUS_*` en producción cuando el usuario tenga las credenciales reales.
