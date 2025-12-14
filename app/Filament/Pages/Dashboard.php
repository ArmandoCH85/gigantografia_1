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

    public function getWidgets(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return [];
        }

        if ($user->hasRole('super_admin')) {
            return [
                \App\Filament\Widgets\SalesStatsWidget::class,
                \App\Filament\Widgets\SalesChartWidget::class,
                \App\Filament\Widgets\PaymentMethodsWidget::class,
                \App\Filament\Widgets\TopProductsWidget::class,
            ];
        }

        if ($user->hasRole('admin')) {
            return [
                \App\Filament\Widgets\SalesStatsWidget::class,
                \App\Filament\Widgets\SalesChartWidget::class,
                \App\Filament\Widgets\PaymentMethodsWidget::class,
                \App\Filament\Widgets\TopProductsWidget::class,
            ];
        }

        if ($user->hasRole('cashier')) {
            return [
                \App\Filament\Widgets\SalesStatsWidget::class,
                \App\Filament\Widgets\PaymentMethodsWidget::class,
            ];
        }

        return [
            \App\Filament\Widgets\SalesStatsWidget::class,
        ];
    }

    public static function canAccess(): bool
    {
        return true;
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'lg' => 2,
            'xl' => 2,
            '2xl' => 3,
        ];
    }

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
}
//comentario
