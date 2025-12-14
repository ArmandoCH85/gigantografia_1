<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Escritorio';
    protected static ?string $title = 'Panel';
    protected static ?int $navigationSort = -1;

    public function getMaxContentWidth(): ?string
    {
        return 'full'; // Margen izquierdo reducido en dashboard
    }

    /**
     * 🎯 WIDGETS ESPECÍFICOS POR ROL
     * Cada rol ve información relevante para sus funciones
     * OPTIMIZADO: Máximo 4-6 widgets por rol para mejor visualización
     */
    public function getWidgets(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        // 👑 SUPER ADMIN - Dashboard completo pero organizado (6 widgets máximo)
        if ($user->hasRole('super_admin')) {
            return [
                // 📊 ROW 1: ESTADÍSTICAS PRINCIPALES
                \App\Filament\Widgets\SalesStatsWidget::class,

                // 📈 ROW 2: ANÁLISIS DE TENDENCIAS
                \App\Filament\Widgets\SalesChartWidget::class,

                // 💳 ROW 3: PAGOS Y PRODUCTOS (lado a lado)
                \App\Filament\Widgets\PaymentMethodsWidget::class,
                \App\Filament\Widgets\TopProductsWidget::class,
            ];
        }

        // 🏢 ADMIN - Dashboard gerencial enfocado (5 widgets)
        if ($user->hasRole('admin')) {
            return [
                // 📊 ROW 1: ESTADÍSTICAS PRINCIPALES
                \App\Filament\Widgets\SalesStatsWidget::class,

                // 📈 ROW 2: TENDENCIAS DE VENTAS
                \App\Filament\Widgets\SalesChartWidget::class,

                // 💳 ROW 3: PAGOS Y PRODUCTOS (lado a lado)
                \App\Filament\Widgets\PaymentMethodsWidget::class,
                \App\Filament\Widgets\TopProductsWidget::class,
            ];
        }

        // 💰 CAJERO - Dashboard de caja enfocado (4 widgets)
        if ($user->hasRole('cashier')) {
            return [
                // 📊 ROW 1: ESTADÍSTICAS DE VENTAS
                \App\Filament\Widgets\SalesStatsWidget::class,

                // 💳 ROW 2: MÉTODOS DE PAGO Y HORAS (lado a lado)
                \App\Filament\Widgets\PaymentMethodsWidget::class,
            ];
        }



        // 📊 DEFAULT - Para roles no definidos (2 widgets)
        return [
            \App\Filament\Widgets\SalesStatsWidget::class,
        ];
    }

    /**
     * 🔐 CONTROL DE ACCESO
     * Waiters van directo al mapa de mesas
     */
    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();



        return true;
    }

    /**
     * 📐 CONFIGURACIÓN DE COLUMNAS RESPONSIVAS
     * Optimizado para mejor visualización de widgets
     */
    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,      // Móvil: 1 columna (stack vertical)
            'sm' => 1,           // Tablet pequeña: 1 columna
            'md' => 2,           // Tablet: 2 columnas (widgets lado a lado)
            'lg' => 2,           // Desktop: 2 columnas (más espacio)
            'xl' => 2,           // Desktop grande: 2 columnas
            '2xl' => 3,          // Desktop extra: máximo 3 columnas
        ];
    }

    /**
     * 🎨 TÍTULO DINÁMICO SEGÚN EL ROL
     */
    public function getTitle(): string
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return 'Escritorio';
        }

        if ($user->hasRole('super_admin')) {
            return 'Panel Ejecutivo';
        }

        if ($user->hasRole('admin')) {
            return 'Panel Gerencial';
        }

        if ($user->hasRole('cashier')) {
            return 'Panel de Caja';
        }



        return 'Escritorio';
    }

    /**
     * 🎯 FILTROS DE FECHA PARA LAS ESTADÍSTICAS
     */
    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make('Filtros de Fecha')
                ->schema([
                    Select::make('date_range')
                        ->label('Rango de Fecha')
                        ->options([
                            'today' => 'Hoy',
                            'yesterday' => 'Ayer',
                            'last_7_days' => 'Últimos 7 días',
                            'last_30_days' => 'Últimos 30 días',
                            'this_month' => 'Este mes',
                            'last_month' => 'Mes pasado',
                            'custom' => 'Personalizado',
                        ])
                        ->default('today')
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state !== 'custom') {
                                $set('start_date', null);
                                $set('end_date', null);
                            }
                        }),

                    DatePicker::make('start_date')
                        ->label('Fecha Inicio')
                        ->visible(fn(callable $get) => $get('date_range') === 'custom')
                        ->required(fn(callable $get) => $get('date_range') === 'custom'),

                    DatePicker::make('end_date')
                        ->label('Fecha Fin')
                        ->visible(fn(callable $get) => $get('date_range') === 'custom')
                        ->required(fn(callable $get) => $get('date_range') === 'custom'),
                ])
                ->columns(3),
        ]);
    }

    /**
     * 📝 SUBTÍTULO CON INFORMACIÓN CONTEXTUAL
     */
    public function getSubheading(): ?string
    {
        $user = Auth::user();
        $currentTime = now()->format('H:i');
        $currentDate = now()->format('d/m/Y');

        if (!$user) {
            return null;
        }

        $roleName = $user->roles->first()?->name ?? 'usuario';

        $tz = config('app.timezone', 'UTC');
        return "Rol: {$roleName} • {$currentDate} {$currentTime} ({$tz}) • Datos al momento de carga";
    }
}
//comentario
