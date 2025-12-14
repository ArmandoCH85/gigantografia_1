<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\Order;
use Carbon\Carbon;
use App\Filament\Widgets\Concerns\DateRangeFilterTrait;

class SalesChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use DateRangeFilterTrait;

    protected static ?string $heading = '📈 Tendencia de Ventas';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '400px';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected $listeners = [
        'filtersFormUpdated' => '$refresh',
        'updateCharts' => '$refresh',
    ];

    protected function getData(): array
    {
        $data = $this->getSalesData();

        return [
            'datasets' => [
                [
                    'label' => 'Ventas Totales',
                    'data' => $data['values'],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)', // Azul
                    'borderColor' => 'rgb(59, 130, 246)',
                    'pointBackgroundColor' => 'rgb(59, 130, 246)',
                    'tension' => 0.4,
                    'fill' => true,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function getSalesData(): array
    {
        $labels = [];
        $values = [];

        $dates = $this->expandDailyDates();

        foreach ($dates as $date) {
            $labels[] = $date['label'];

            $sales = Order::whereDate('created_at', $date['date'])
                ->where('billed', true)
                ->where('status', '!=', 'cancelled')
                ->sum('total');

            $values[] = (float) $sales;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function expandDailyDates(): array
    {
        [$start, $end] = $this->resolveDateRange($this->filters ?? []);
        $dates = [];

        // Clonar para no modificar la referencia original si se usa
        $current = $start->copy();

        while ($current->lte($end)) {
            $dates[] = [
                'date' => $current->copy(),
                'label' => $start->equalTo($end) ? ($current->isToday() ? 'Hoy' : $current->format('d/m')) : ($start->diffInDays($end) <= 7 ? $current->format('d/m') : $current->format('d')),
            ];
            $current->addDay();
        }
        return $dates;
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'callbacks' => [
                        'label' => 'function(context) {
                            return context.dataset.label + ": S/ " + context.parsed.y.toFixed(2);
                        }'
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Período',
                    ],
                ],
                'y' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Ventas (S/)',
                    ],
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) {
                            return "S/ " + value.toFixed(0);
                        }'
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
