<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Support\Colors\Color;

use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationGroup;
use Illuminate\Support\Facades\Auth;
use App\Filament\Pages\InventarioPorAlmacen;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('web')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem') // Ancho optimizado - balance perfecto
            ->collapsedSidebarWidth('4rem') // Ancho colapsado elegante
            ->homeUrl('/admin')
            ->maxContentWidth('full')
            ->sidebarFullyCollapsibleOnDesktop()
            ->brandName('')
            ->brandLogo(asset('images/logoWayna.svg'))
            ->brandLogoHeight('6rem')
            ->colors([
                'primary' => Color::Indigo, // Profesional / principal
                'info' => Color::Cyan, // Info/accent secundario
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'gray' => Color::Gray,
            ])
            ->font('Manrope') // Fuente profesional moderna
            ->darkMode(false)
            ->globalSearch(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            // Registrar páginas explícitamente en lugar de descubrirlas automáticamente
            ->pages([

                \App\Filament\Pages\Dashboard::class, // ✅ Dashboard personalizado por roles
                \App\Filament\Pages\ReportesPage::class,
                \App\Filament\Pages\ReportViewerPage::class,

            ])
            // COMENTADO: Auto-descubrimiento de widgets deshabilitado para control granular
            // ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,

                \App\Filament\Widgets\PaymentMethodsWidget::class,
                \App\Filament\Widgets\SalesStatsWidget::class,
                \App\Filament\Widgets\SalesChartWidget::class,

                \App\Filament\Widgets\TopProductsWidget::class,



            ])
            // Habilitar descubrimiento automático de widgets como alternativa
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Ventas')
                    ->icon('heroicon-o-shopping-cart')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Facturación Electrónica')
                    ->icon('heroicon-o-document-text')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Productos y Catálogo')
                    ->icon('heroicon-o-cube')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Inventario y Compras')
                    ->icon('heroicon-o-archive-box')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Recursos Humanos')
                    ->icon('heroicon-o-user-group')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Reportes')
                    ->icon('heroicon-o-chart-bar')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Configuración')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsed(true),
            ])
            // Eliminar grupos personalizados para que funcione con los recursos automáticos
            // Usar navegación automática de Filament
            ->middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, AuthenticateSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class, SubstituteBindings::class, DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
            ->authMiddleware([Authenticate::class])
            // Render Hooks para personalización del login POS
            ->renderHook(PanelsRenderHook::HEAD_END, fn(): string => '<link rel="preconnect" href="https://fonts.googleapis.com">' . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">' . '<link rel="stylesheet" href="' . asset('css/login-daisyui-compiled.css') . '">' . '<style id="admin-panel-typography-scale">.fi-body{font-size:17.5px;line-height:1.55;font-weight:400;font-family:"Manrope",Inter,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}</style>')
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn(): string => view('filament.auth.login-header')->render())



            ->plugins([\BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(), \TomatoPHP\FilamentUsers\FilamentUsersPlugin::make()]);
    }
}
//comentario
