<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Tenancy\EditEmpresaProfile;
use App\Filament\Pages\Tenancy\RegisterEmpresa;
use App\Models\Empresa;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->tenant(Empresa::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterEmpresa::class)
            ->tenantProfile(EditEmpresaProfile::class)
            // Paleta cálida crema/ámbar (ver docs/DECISIONES.md) — un solo
            // hex por color semántico, Filament genera automáticamente toda
            // la escala 50-950 y elige los tonos correctos para claro/oscuro
            // por su cuenta (mecanismo nativo, no hay forma soportada de
            // fijar un hex distinto por modo sin pelear contra su sistema
            // interno de variables CSS — ver el docblock de
            // resources/css/filament/admin/theme.css). 'gray' es la que más
            // rinde: maneja fondo, bordes y texto silenciado de TODO el
            // panel (sidebar, tablas, tarjetas, modales), así que sembrarla
            // con un neutro cálido calienta la app entera de un solo cambio.
            ->colors([
                'primary' => Color::hex('#DF9436'),
                'gray' => Color::hex('#78716C'),
                'danger' => Color::hex('#C0392B'),
                'warning' => Color::hex('#B8791A'),
                'success' => Color::hex('#2F7A4D'),
            ])
            // Sincroniza el selector de tema nativo de Filament con
            // `users.tema` — ver resources/views/filament/tema-sync.blade.php.
            ->renderHook(PanelsRenderHook::HEAD_START, fn () => view('filament.tema-sync'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // App\Filament\Pages\Dashboard (ver docs/DECISIONES.md DEC-065,
            // reemplaza el Dashboard base de Filament) NO se lista aparte a
            // propósito — discoverPages() ya lo registra por vivir en
            // app/Filament/Pages/, mismo motivo que los widgets de abajo.
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // VentasResumenWidget/ComparativoSedesWidget/InventarioBajoWidget
            // (ver docs/DECISIONES.md DEC-065) NO se listan aquí a propósito
            // — discoverWidgets() ya los registra solo por vivir en
            // app/Filament/Widgets/. Sin AccountWidget/FilamentInfoWidget
            // (widgets de bienvenida/versión de Filament por defecto,
            // quitados a pedido del usuario — DEC-065): el Dashboard ahora
            // solo muestra los 3 widgets propios del negocio.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
