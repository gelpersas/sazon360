# Módulo actual

> Actualizar este archivo al finalizar cada sesión o tarea importante. Es el primer archivo (junto con `CLAUDE.md`) que debe leerse al empezar una sesión nueva.

## Nombre del módulo actual

Dashboard principal / Home (DEC-065) — pantalla "Inicio" en el POS táctil (todos los roles operativos) y Dashboard de administración en Filament (consolidado de sedes + drill-down por sede).

## Objetivo

El usuario pegó un "Prompt Maestro" pidiendo una página de inicio con contenido según el rol autenticado: selector Hoy/Semana/Mes, métricas de la sede propia para cajero/mesero/vitrina/pastelero, y para administrador un consolidado de las 3 sedes con drill-down. Pidió auditar la estructura real antes de escribir código y detenerse ante cualquier decisión de arquitectura no cubierta.

## Alcance incluido (DEC-065)

Ver DEC-065 para el análisis completo. Resumen: el prompt asumía roles/mecanismos que no existen tal cual en Sazón360 (rol "vitrina", una pantalla de inicio ya existente, un criterio de "producto top"/"empleado destacado" ya calculado) — se presentaron las 4 decisiones no cubiertas en un solo `AskUserQuestion` con recomendación, confirmadas antes de implementar. Backend: `Pos\DashboardController` (ventas/pedidos activos/mesas ocupadas/inventario bajo de una sede) + `Mesa::ocupadasEnSedes()` (reutilizado por POS y Filament, expande grupos en Modo General). Frontend POS: `InicioView.vue` nueva, destino opcional en el sidebar (no reemplaza el aterrizaje directo a Mostrador/Cocina). Filament: `App\Filament\Pages\Dashboard` con filtros nativos Rango/Sede (`HasFiltersForm`) + 3 widgets nuevos (`VentasResumenWidget`, `ComparativoSedesWidget`, `InventarioBajoWidget`) que resuelven su alcance de sedes vía el trait `Concerns\ResuelveAlcanceDashboard` — la misma tabla de `ComparativoSedesWidget` sirve para consolidado (N filas) y drill-down (1 fila).

## Fuera de alcance (a propósito)

Reportes avanzados, exportación o gráficas históricas (el prompt lo excluyó explícitamente). Un rol "vitrina" separado (se trata como Caja/Mostrador, confirmado con el usuario). Persistir el rango Hoy/Semana/Mes en base de datos (el prompt no lo pedía, solo durante la sesión).

## Reglas relacionadas

Ninguna nueva. Reutiliza el mecanismo nativo de filtros de Filament v4 (`Dashboard\Concerns\HasFiltersForm`/`Widgets\Concerns\InteractsWithPageFilters`) en vez de construir uno paralelo.

## Archivos relacionados

`app/Http/Controllers/Pos/DashboardController.php`, `app/Models/Mesa.php` (+`ocupadasEnSedes()`), `routes/pos.php`, `resources/js/pos/stores/dashboard.js`, `resources/js/pos/views/InicioView.vue`, `resources/js/pos/router.js`, `resources/js/pos/components/AppShell.vue`, `app/Filament/Pages/Dashboard.php`, `app/Filament/Widgets/{VentasResumenWidget,ComparativoSedesWidget,InventarioBajoWidget,Concerns/ResuelveAlcanceDashboard}.php`, `resources/views/filament/widgets/{comparativo-sedes-widget,inventario-bajo-widget}.blade.php`, `app/Providers/Filament/AdminPanelProvider.php`, `tests/Feature/{DashboardTest,DashboardAdminTest}.php`, `docs/DECISIONES.md` (DEC-065).

## Trabajo terminado

Implementado y verificado de punta a punta. `composer test`: 175/175 en verde (165 previos + 10 nuevos). `composer lint`: verde. `npm run build`: verde. Verificado con Playwright real (instalado/desinstalado) contra la sede real de Dulcita: los 3 roles operativos del POS ven "Inicio" con datos y énfasis correctos por rol; admin central y admin de sede ven el Dashboard de Filament con Rango/Sede, comparativo por sede e inventario bajo funcionando (el selector "Sede" queda oculto porque Dulcita solo tiene 1 sede real — el caso multi-sede consolidado quedó cubierto por los 5 tests de `DashboardAdminTest.php`, no por verificación visual, al no haber una segunda sede real disponible sin crear datos temporales). Cero errores de consola. No se modificó ningún dato real de Dulcita.

## Trabajo pendiente

Ninguno bloqueante. Nota de calidad de datos detectada de paso (no de este módulo): la sede real de Dulcita tiene comandas/mesas quedadas en estado activo de rondas de verificación anteriores de esta sesión (8 comandas activas, 4 mesas ocupadas) — no afecta la corrección del dashboard (que solo refleja lo que hay), pero conviene que el usuario las revise/limpie si no corresponden a actividad real.

## Pruebas ejecutadas

- `composer test` (Pest): 175/175 passed.
- `composer lint` (Pint): verde.
- `npm run build`: verde.
- Verificación manual con Playwright (temporal, instalado/desinstalado) contra datos reales de Dulcita — solo lectura, sin crear ni modificar datos.

## Errores conocidos

Ninguno abierto.

## Decisiones pendientes

Ninguna bloqueante para este módulo.

## Próxima acción exacta

Esperar instrucción del usuario. Pendientes de fondo sin relación, bloqueados sin su input: Fase 6 (impuesto DIAN reales de los productos de Dulcita), infraestructura de despliegue (cPanel), y los 2 puntos restantes de DEC-042 (POS electrónico, nota crédito/débito).
