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
                \App\Filament\Pages\PosInterface::class,
                \App\Filament\Pages\Dashboard::class, // ✅ Dashboard personalizado por roles
                \App\Filament\Pages\ReportesPage::class,
                \App\Filament\Pages\ReportViewerPage::class,
                InventarioPorAlmacen::class, // ✅ Página de inventario por almacén
            ])
            // COMENTADO: Auto-descubrimiento de widgets deshabilitado para control granular
            // ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,

                \App\Filament\Widgets\PaymentMethodsWidget::class,
                \App\Filament\Widgets\SalesStatsWidget::class,
                \App\Filament\Widgets\SalesChartWidget::class,

                \App\Filament\Widgets\TopProductsWidget::class,
                \App\Filament\Widgets\SalesHoursWidget::class,

                \App\Filament\Widgets\SuppliersCountWidget::class,
                \App\Filament\Widgets\SunatConfigurationOverview::class,
                \App\Filament\Widgets\ProfitChartWidget::class,

                \App\Filament\Widgets\PaymentMethodsChart::class,

                \App\Filament\Widgets\PurchaseStatsWidget::class, // ✅ Widget de estadísticas de compras agregado
            ])
            // Habilitar descubrimiento automático de widgets como alternativa
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            // Agregar enlace personalizado para Resúmenes de Boletas
            ->navigationItems([
                NavigationItem::make('Resúmenes de Boletas')
                    ->url('/admin/summaries')
                    ->icon('heroicon-o-document-text')
                    ->group('📄 Facturación y Ventas')
                    ->sort(5)
            ])
            // Eliminar grupos personalizados para que funcione con los recursos automáticos
            // Usar navegación automática de Filament
            ->middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, AuthenticateSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class, SubstituteBindings::class, DisableBladeIconComponents::class, DispatchServingFilamentEvent::class])
            ->authMiddleware([Authenticate::class])
            // Render Hooks para personalización del login POS
            ->renderHook(PanelsRenderHook::HEAD_END, fn(): string => '<link rel="preconnect" href="https://fonts.googleapis.com">' . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">' . '<link rel="stylesheet" href="' . asset('css/login-daisyui-compiled.css') . '">' . '<link rel="stylesheet" href="' . asset('css/custom-navigation.css') . '">' . '<script src="' . asset('js/custom-navigation.js') . '"></script>' . '<style id="admin-panel-typography-scale">.fi-body{font-size:17.5px;line-height:1.55;font-weight:400;font-family:"Manrope",Inter,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}</style>')
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn(): string => view('filament.auth.login-header')->render())

            // 🔗 Accesos rápidos en el HEADER (topbar)
            ->renderHook(PanelsRenderHook::TOPBAR_START, fn(): string => view('filament.topbar.quick-links')->render())
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn(): string => '<style>
                    /* 🎨 TAILADMIN DESIGN SYSTEM - FONDO BLANCO */
                    :root {
                        --tailadmin-sidebar-bg: #FFFFFF;
                        --tailadmin-sidebar-hover: #EEF2FF;
                        --tailadmin-accent: #3C50E0;
                        --tailadmin-accent-hover: #5570F1;
                        --tailadmin-text: #1F2937;
                        --tailadmin-text-muted: #6B7280;
                        --tailadmin-border: #E5E7EB;

                        /* 🎨 COLORES CORPORATIVOS PARA ICONOS */
                        --corporate-primary: #2563EB;
                        --corporate-secondary: #059669;
                        --corporate-accent: #DC2626;
                        --corporate-warning: #D97706;
                        --corporate-info: #0891B2;
                        --corporate-neutral: #6B7280;
                    }

                    /* 🏗️ SIDEBAR BASE - TAILADMIN FONDO BLANCO */
                    .fi-sidebar {
                        background: var(--tailadmin-sidebar-bg) !important;
                        border-right: 1px solid var(--tailadmin-border) !important;
                    }

                    /* 🎭 HEADER CON LOGO - ESTILO TAILADMIN */


                    /* OCULTAR LOGO DE MODO OSCURO */
                    header.fi-sidebar-header .fi-logo.hidden.dark\\:flex {
                        display: none !important;
                    }

                    /* MOSTRAR SOLO LOGO DE MODO CLARO */
                    header.fi-sidebar-header .fi-logo.flex.dark\\:hidden {
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        width: 100% !important;
                    }

                    /* LOGO STYLING - FORZAR VISIBILIDAD */
                    header.fi-sidebar-header .fi-logo,
                    header.fi-sidebar-header .fi-logo img,
                    header.fi-sidebar-header img,
                    header.fi-sidebar-header svg {
                        display: block !important;
                        max-height: 96px !important;  /* Aumentado de 64px a 96px (6rem = 96px) */
                        height: 96px !important;     /* Aumentado de 64px a 96px (6rem = 96px) */
                        width: auto !important;
                        object-fit: contain !important;
                        filter: brightness(1) !important;
                        opacity: 1 !important;
                        visibility: visible !important;
                    }

                    /* CONTENEDOR DEL LOGO */
                    header.fi-sidebar-header a {
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        text-decoration: none !important;
                        padding: 1rem 0.5rem !important; /* Espaciado adicional para el logo más grande */
                    }

                    /* TEXTO DEL LOGO */
                    header.fi-sidebar-header .fi-logo-text,
                    header.fi-sidebar-header h1,
                    header.fi-sidebar-header .text-xl {
                        color: #1F2937 !important;
                        font-size: 1.5rem !important;
                        font-weight: 700 !important;
                        letter-spacing: -0.025em !important;
                        margin: 0 !important;
                    }

                    /* 🧭 NAVEGACIÓN PRINCIPAL - OPTIMIZADA PARA 16REM */
                    nav.fi-sidebar-nav {
                        background: #FFFFFF !important;
                        padding: 1rem 1.25rem !important;
                        border-radius: 0 !important;
                        box-shadow: none !important;
                        margin: 0 !important;
                        display: flex !important;
                        flex-direction: column !important;
                        gap: 0.375rem !important;
                        height: 100% !important;
                        overflow-y: auto !important;
                        scrollbar-width: thin !important;
                        scrollbar-color: #E5E7EB #FFFFFF !important;
                    }

                    /* SCROLLBAR TAILADMIN STYLE */
                    nav.fi-sidebar-nav::-webkit-scrollbar {
                        width: 6px !important;
                    }

                    nav.fi-sidebar-nav::-webkit-scrollbar-track {
                        background: #F9FAFB !important;
                        border-radius: 3px !important;
                    }

                    nav.fi-sidebar-nav::-webkit-scrollbar-thumb {
                        background: #D1D5DB !important;
                        border-radius: 3px !important;
                    }

                    nav.fi-sidebar-nav::-webkit-scrollbar-thumb:hover {
                        background: #9CA3AF !important;
                    }

                    /* 📁 NAVIGATION GROUPS - TAILADMIN STYLE */


 .fi-sidebar-group-label {
                        background: var(--group-bg) !important;
                        color: #2563EB !important;
                        font-size: 0.9rem !important;
                        font-weight: 600 !important;
                        text-transform: uppercase !important;

                    }



                    /* HOVER STATE */
                    .fi-sidebar-item:hover {
                        background: var(--tailadmin-sidebar-hover) !important;
                        background-color: #EEF2FF !important;
                    }

                    .fi-sidebar-item:hover .fi-sidebar-item-button {
                        color: var(--tailadmin-text) !important;
                    }

                    .fi-sidebar-item:hover .fi-sidebar-item-label {
                        color: var(--tailadmin-text) !important;
                    }

                    .fi-sidebar-item:hover .fi-sidebar-item-icon {
                        color: var(--tailadmin-text) !important;
                    }

                    /* ACTIVE STATE - FONDO AZUL CLARO CON TEXTO OSCURO */
                    .fi-sidebar-item.fi-active {
                        background: #EEF2FF !important;
                        background-color: #EEF2FF !important;
                        border-radius: 0.5rem !important;
                        border-left: 3px solid var(--tailadmin-accent) !important;
                    }

                    .fi-sidebar-item.fi-active > .fi-sidebar-item-button {
                        color: var(--tailadmin-accent) !important;
                        font-weight: 600 !important;
                        background: transparent !important;
                    }

                    .fi-sidebar-item.fi-active .fi-sidebar-item-button .fi-sidebar-item-label {
                        color: var(--tailadmin-accent) !important;
                        font-weight: 600 !important;
                    }

                    .fi-sidebar-item.fi-active .fi-sidebar-item-button .fi-sidebar-item-icon {
                        color: var(--tailadmin-accent) !important;
                    }

                    /* 🎨 ICONS CON COLORES CORPORATIVOS */
                    .fi-sidebar-item-icon {
                        width: 2rem !important;      /* íconos aún más grandes */
                        height: 2rem !important;     /* íconos aún más grandes */
                        flex-shrink: 0 !important;
                        color: var(--corporate-neutral) !important;
                        transition: color 0.3s ease !important;
                    }

                    /* MÉTODO ALTERNATIVO: Aplicar colores por posición en el DOM */
                    .fi-sidebar-nav .fi-sidebar-item:nth-child(1) .fi-sidebar-item-icon {
                        color: var(--corporate-primary) !important; /* Dashboard */
                    }

                    .fi-sidebar-nav .fi-sidebar-item:nth-child(2) .fi-sidebar-item-icon {
                        color: var(--corporate-secondary) !important; /* POS */
                    }

                    .fi-sidebar-nav .fi-sidebar-item:nth-child(3) .fi-sidebar-item-icon {
                        color: var(--corporate-secondary) !important; /* Mapa Mesas */
                    }

                    /* ICONOS ESPECÍFICOS CON COLORES CORPORATIVOS - RUTAS REALES DE FILAMENT */

                    /* 🔵 AZUL CORPORATIVO - Dashboard y Admin */
                    .fi-sidebar-item[href*="/admin/dashboard"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="/admin"] .fi-sidebar-item-icon:first-child {
                        color: var(--corporate-primary) !important;
                    }

                    /* 🟢 VERDE CORPORATIVO - POS y Operaciones de Caja */
                    .fi-sidebar-item[href*="pos-interface"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="operaciones-caja"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="mapa-mesas"] .fi-sidebar-item-icon {
                        color: var(--corporate-secondary) !important;
                    }

                    /* 🟠 NARANJA CORPORATIVO - Reportes */
                    .fi-sidebar-item[href*="reportes"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="reports"] .fi-sidebar-item-icon {
                        color: var(--corporate-warning) !important;
                    }

                    /* 🔷 CIAN CORPORATIVO - Configuración y Usuarios */
                    .fi-sidebar-item[href*="configuracion"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="users"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="shield/roles"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="company-config"] .fi-sidebar-item-icon {
                        color: var(--corporate-info) !important;
                    }

                    /* 🔴 ROJO CORPORATIVO - Inventario y Productos */
                    .fi-sidebar-item[href*="products"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="product-categories"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="ingredients"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="inventario"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="warehouse"] .fi-sidebar-item-icon {
                        color: var(--corporate-accent) !important;
                    }

                    /* 🟣 PÚRPURA CORPORATIVO - Facturación y Ventas */
                    .fi-sidebar-item[href*="document-series"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="invoices"] .fi-sidebar-item-icon,
                    .fi-sidebar-item[href*="customers"] .fi-sidebar-item-icon {
                        color: #7C3AED !important;
                    }

                    /* 🏷️ LABELS OPTIMIZADOS */
                    .fi-sidebar-item-label {
                        color: inherit !important;
                        font-size: 0.875rem !important; /* acompaña el tamaño del ícono */
                        font-weight: inherit !important;
                        line-height: 1.25 !important;
                        overflow: hidden !important;
                        text-overflow: ellipsis !important;
                        white-space: nowrap !important;
                    }

                    /* 🎨 DESTACAR "APERTURA Y CIERRE DE CAJA" */
                    .fi-sidebar-item[href*="operaciones-caja"] .fi-sidebar-item-label {
                        background-color: #EEF2FF !important;
                        color: #2563EB !important;
                        font-weight: 600 !important;
                        padding: 0.375rem 0.625rem !important;
                        border-radius: 0.375rem !important;
                        display: inline-block !important;
                    }

                    /* Mantener el color en hover */
                    .fi-sidebar-item[href*="operaciones-caja"]:hover .fi-sidebar-item-label {
                        background-color: #DBEAFE !important;
                        color: #1D4ED8 !important;
                    }

                    /* 🌙 DARK MODE - MANTENER FONDO BLANCO */
                    .dark .fi-sidebar {
                        --tailadmin-sidebar-bg: #FFFFFF;
                        --tailadmin-sidebar-hover: #EEF2FF;
                        --tailadmin-text: #1F2937;
                        --tailadmin-text-muted: #6B7280;
                        --tailadmin-border: #E5E7EB;
                    }

                    /* =============================
                       TOOLTIP (Tippy) DEL SIDEBAR
                       Más grande y visual
                       ============================= */
                    .fi .tippy-box {
                        background-color: #374151 !important; /* gris oscuro elegante */
                        color: #FFFFFF !important;
                        border-radius: 0.5rem !important;
                        box-shadow: 0 10px 25px rgba(0,0,0,0.18), 0 6px 10px rgba(0,0,0,0.12) !important;
                        font-size: 0.95rem !important; /* texto más grande */
                        font-weight: 600 !important;
                        z-index: 10050 !important;
                    }

                    .fi .tippy-box .tippy-content {
                        padding: 0.5rem 0.75rem !important; /* más padding */
                    }

                    /* Flecha del tooltip acorde al fondo */
                    .fi .tippy-box[data-placement^="right"] > .tippy-arrow::before,
                    .fi .tippy-box[data-placement^="left"] > .tippy-arrow::before,
                    .fi .tippy-box[data-placement^="top"] > .tippy-arrow::before,
                    .fi .tippy-box[data-placement^="bottom"] > .tippy-arrow::before {
                        color: #374151 !important;
                    }

                    /* Tamaño de flecha ligeramente mayor */
                    .fi .tippy-box .tippy-arrow {
                        width: 12px !important;
                        height: 12px !important;
                    }
                </style>
                
                <style>
                /* Maximizar uso del espacio disponible */
                .fi-content {
                    max-width: 100% !important;
                    padding: 1rem !important;
                    width: 100% !important;
                }

                /* Tablas responsivas que usan todo el ancho */
                .fi-table {
                    width: 100% !important;
                    max-width: 100% !important;
                }

                /* Formularios de ancho completo */
                .fi-form {
                    max-width: 100% !important;
                    width: 100% !important;
                }

                /* Grid responsive para formularios - más columnas en pantallas grandes */
                @media (min-width: 1536px) {
                    .fi-grid-cols-2 {
                        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                    }
                }

                @media (min-width: 1920px) {
                    .fi-grid-cols-2 {
                        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                    }
                }

                /* Repeater de detalles con más espacio */
                .fi-repeater .fi-repeater-item {
                    width: 100% !important;
                }

                /* Secciones de formulario más anchas */
                .fi-section {
                    max-width: 100% !important;
                }

                /* Contenedor principal sin límites de ancho */
                .fi-panel-content {
                    max-width: none !important;
                }
                </style>
                
                <style>
                /* Empty state optimizado para usar todo el espacio */
                .fi-empty-state,
                .filament-empty-state {
                    width: 100% !important;
                    max-width: 100% !important;
                    padding: 3rem 2rem !important;
                    min-height: 60vh !important;
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: center !important;
                    align-items: center !important;
                }

                .fi-empty-state-content,
                .filament-empty-state-content {
                    max-width: 800px !important;
                    text-align: center !important;
                }

                .fi-empty-state-heading,
                .filament-empty-state-heading {
                    font-size: 1.5rem !important;
                    font-weight: 600 !important;
                    margin-bottom: 1rem !important;
                    color: #374151 !important;
                }

                .fi-empty-state-description,
                .filament-empty-state-description {
                    font-size: 1.1rem !important;
                    color: #6b7280 !important;
                    margin-bottom: 2rem !important;
                    line-height: 1.6 !important;
                }

                .fi-empty-state-actions,
                .filament-empty-state-actions {
                    display: flex !important;
                    gap: 1rem !important;
                    justify-content: center !important;
                    flex-wrap: wrap !important;
                }

                /* Responsive para empty state */
                @media (max-width: 768px) {
                    .fi-empty-state,
                    .filament-empty-state {
                        padding: 2rem 1rem !important;
                        min-height: 50vh !important;
                    }
                    
                    .fi-empty-state-heading,
                    .filament-empty-state-heading {
                        font-size: 1.25rem !important;
                    }
                    
                    .fi-empty-state-description,
                    .filament-empty-state-description {
                        font-size: 1rem !important;
                    }
                }

                @media (min-width: 1920px) {
                    .fi-empty-state,
                    .filament-empty-state {
                        min-height: 70vh !important;
                        padding: 4rem 2rem !important;
                    }
                    
                    .fi-empty-state-content,
                    .filament-empty-state-content {
                        max-width: 1000px !important;
                    }
                    
                    .fi-empty-state-heading,
                    .filament-empty-state-heading {
                        font-size: 1.75rem !important;
                    }
                    
                    .fi-empty-state-description,
                    .filament-empty-state-description {
                        font-size: 1.25rem !important;
                    }
                }
                </style>
                
                <style>
                /* Resaltar contornos de listas desplegables (Selects) - SELECTORES FIAMENT 3 */
                
                /* Selects principales - Selectores más específicos */
                div[data-type="select"] > div > button,
                .fi-ta-select-trigger,
                .filament-forms-select-trigger,
                button[role="combobox"],
                .fi-select-trigger {
                    border: 2px solid #e5e7eb !important;
                    border-radius: 0.5rem !important;
                    transition: all 0.2s ease-in-out !important;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
                    background-color: #ffffff !important;
                }

                div[data-type="select"] > div > button:hover,
                .fi-ta-select-trigger:hover,
                .filament-forms-select-trigger:hover,
                button[role="combobox"]:hover,
                .fi-select-trigger:hover {
                    border-color: #3b82f6 !important;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
                    background-color: #f8fafc !important;
                }

                div[data-type="select"] > div > button:focus,
                .fi-ta-select-trigger:focus,
                .filament-forms-select-trigger:focus,
                button[role="combobox"]:focus,
                .fi-select-trigger:focus {
                    border-color: #3b82f6 !important;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
                    outline: none !important;
                    background-color: #ffffff !important;
                }

                /* Selects dentro de repeaters */
                .fi-repeater div[data-type="select"] > div > button,
                .fi-repeater .fi-ta-select-trigger,
                .fi-repeater .filament-forms-select-trigger,
                .fi-repeater button[role="combobox"],
                .fi-repeater .fi-select-trigger {
                    border-color: #d1d5db !important;
                    background-color: #ffffff !important;
                }

                .fi-repeater div[data-type="select"] > div > button:hover,
                .fi-repeater .fi-ta-select-trigger:hover,
                .fi-repeater .filament-forms-select-trigger:hover,
                .fi-repeater button[role="combobox"]:hover,
                .fi-repeater .fi-select-trigger:hover {
                    border-color: #60a5fa !important;
                    background-color: #f0f9ff !important;
                }

                /* Selects con estado de error */
                div[data-type="select"].error > div > button,
                .fi-ta-select-trigger.error,
                .filament-forms-select-trigger.error,
                button[role="combobox"].error,
                .fi-select-trigger.error {
                    border-color: #ef4444 !important;
                    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
                }

                /* Selects deshabilitados */
                div[data-type="select"] > div > button:disabled,
                .fi-ta-select-trigger:disabled,
                .filament-forms-select-trigger:disabled,
                button[role="combobox"]:disabled,
                .fi-select-trigger:disabled {
                    border-color: #e5e7eb !important;
                    background-color: #f9fafb !important;
                    opacity: 0.7 !important;
                    cursor: not-allowed !important;
                }

                /* Dropdown menu styling */
                div[data-type="select"] + div[role="listbox"],
                .fi-ta-select-dropdown,
                .filament-forms-select-dropdown,
                .fi-select-dropdown {
                    border: 2px solid #e5e7eb !important;
                    border-radius: 0.5rem !important;
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
                    margin-top: 0.25rem !important;
                    background-color: #ffffff !important;
                    z-index: 50 !important;
                }

                div[data-type="select"] + div[role="listbox"] > div,
                .fi-ta-select-dropdown-item,
                .filament-forms-select-dropdown-item,
                .fi-select-dropdown-item {
                    border-radius: 0.375rem !important;
                    margin: 0.125rem !important;
                    transition: all 0.15s ease-in-out !important;
                    padding: 0.5rem 0.75rem !important;
                }

                div[data-type="select"] + div[role="listbox"] > div:hover,
                .fi-ta-select-dropdown-item:hover,
                .filament-forms-select-dropdown-item:hover,
                .fi-select-dropdown-item:hover {
                    background-color: #eff6ff !important;
                    color: #1d4ed8 !important;
                }

                div[data-type="select"] + div[role="listbox"] > div.selected,
                .fi-ta-select-dropdown-item.selected,
                .filament-forms-select-dropdown-item.selected,
                .fi-select-dropdown-item.selected {
                    background-color: #dbeafe !important;
                    color: #1e40af !important;
                    font-weight: 500 !important;
                }

                /* Selects con estado abierto */
                div[data-type="select"][data-state="open"] > div > button,
                .fi-ta-select[data-state="open"] .fi-ta-select-trigger,
                .filament-forms-select[data-state="open"] .filament-forms-select-trigger,
                .fi-select[data-state="open"] .fi-select-trigger {
                    border-color: #3b82f6 !important;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
                }

                /* Iconos de selects */
                div[data-type="select"] > div > button svg,
                .fi-ta-select-trigger svg,
                .filament-forms-select-trigger svg,
                .fi-select-trigger svg {
                    color: #6b7280 !important;
                    transition: color 0.2s ease-in-out !important;
                }

                div[data-type="select"] > div > button:hover svg,
                .fi-ta-select-trigger:hover svg,
                .filament-forms-select-trigger:hover svg,
                .fi-select-trigger:hover svg {
                    color: #3b82f6 !important;
                }

                div[data-type="select"] > div > button:focus svg,
                .fi-ta-select-trigger:focus svg,
                .filament-forms-select-trigger:focus svg,
                .fi-select-trigger:focus svg {
                    color: #3b82f6 !important;
                }

                /* Selectors universales para todos los selects */
                select {
                    border: 2px solid #e5e7eb !important;
                    border-radius: 0.5rem !important;
                    transition: all 0.2s ease-in-out !important;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
                    background-color: #ffffff !important;
                }

                select:hover {
                    border-color: #3b82f6 !important;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
                    background-color: #f8fafc !important;
                }

                select:focus {
                    border-color: #3b82f6 !important;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
                    outline: none !important;
                    background-color: #ffffff !important;
                }

                /* Responsive para selects */
                @media (max-width: 768px) {
                    div[data-type="select"] > div > button,
                    .fi-ta-select-trigger,
                    .filament-forms-select-trigger,
                    button[role="combobox"],
                    .fi-select-trigger,
                    select {
                        border-width: 1px !important;
                        font-size: 0.875rem !important;
                    }
                    
                    div[data-type="select"] + div[role="listbox"] > div,
                    .fi-ta-select-dropdown-item,
                    .filament-forms-select-dropdown-item,
                    .fi-select-dropdown-item {
                        padding: 0.375rem 0.5rem !important;
                        font-size: 0.875rem !important;
                    }
                }
                </style>

                <script>
                    /* 📱 SIDEBAR COLAPSADO POR DEFECTO - SEGÚN DOCUMENTACIÓN FILAMENT */
                    document.addEventListener("DOMContentLoaded", function() {
                        // Verificar si Alpine.js está disponible
                        if (typeof Alpine !== "undefined" && Alpine.store) {
                            // Usar el store de Alpine.js para colapsar el sidebar
                            const sidebarStore = Alpine.store("sidebar");
                            if (sidebarStore && typeof sidebarStore.collapse === "function") {
                                // Colapsar sidebar al cargar la página
                                sidebarStore.collapse();
                            }
                        }

                        // Método alternativo usando clases CSS
                        setTimeout(() => {
                            const sidebar = document.querySelector(".fi-sidebar");
                            const body = document.body;

                            if (sidebar && body) {
                                // Agregar clase de sidebar colapsado
                                body.classList.add("fi-sidebar-collapsed");
                                sidebar.classList.add("fi-collapsed");

                                // Trigger evento para notificar el cambio
                                window.dispatchEvent(new CustomEvent("sidebar-collapsed", {
                                    detail: {
                                        collapsed: true
                                    }
                                }));
                            }

                            // 🎨 APLICAR COLORES CORPORATIVOS A ICONOS
                            applyIconColors();
                        }, 100);
                    });

                    /* 🎨 FUNCIÓN PARA APLICAR COLORES CORPORATIVOS */
                    function applyIconColors() {
                        const colorMap = {
                            "dashboard": "#2563EB", // Azul corporativo
                            "pos-interface": "#059669", // Verde corporativo

                            "operaciones-caja": "#059669", // Verde corporativo
                            "caja": "#059669", // Verde corporativo para el nuevo módulo
                            "cash-register-report": "#059669", // Reportes de caja
                            "egresos": "#DC2626", // Rojo corporativo para egresos
                            "reportes": "#D97706", // Naranja corporativo
                            "products": "#DC2626", // Rojo corporativo
                            "product-categories": "#DC2626", // Rojo corporativo

                            "users": "#0891B2", // Cian corporativo
                            "shield/roles": "#0891B2", // Cian corporativo
                            "configuracion": "#0891B2", // Cian corporativo
                            "document-series": "#7C3AED", // Púrpura corporativo
                            "customers": "#7C3AED" // Púrpura corporativo
                        };

                        // Aplicar colores a los iconos del sidebar
                        document.querySelectorAll(".fi-sidebar-item").forEach(item => {
                            const href = item.getAttribute("href") || "";
                            const icon = item.querySelector(".fi-sidebar-item-icon");

                            if (icon) {
                                // Buscar coincidencia en el mapa de colores
                                for (const [route, color] of Object.entries(colorMap)) {
                                    if (href.includes(route)) {
                                        icon.style.color = color + " !important";
                                        break;
                                    }
                                }
                            }
                        });
                    }

                    /* 🔄 REAPLICA COLORES CUANDO CAMBIA LA NAVEGACIÓN */
                    document.addEventListener("livewire:navigated", applyIconColors);
                    document.addEventListener("turbo:load", applyIconColors);
                </script>',
            )

            ->plugins([\BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(), \TomatoPHP\FilamentUsers\FilamentUsersPlugin::make()]);
    }
}
//comentario
