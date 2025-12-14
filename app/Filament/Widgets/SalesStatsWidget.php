<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Order;
use Carbon\Carbon;
use App\Filament\Widgets\Concerns\DateRangeFilterTrait;

class SalesStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use DateRangeFilterTrait;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected $listeners = [
        'filtersFormUpdated' => '$refresh',
        'updateCharts' => '$refresh',
    ];

    public function updatedFilters(): void
    {
        $this->dispatch('updateCharts');
    }

    protected function getStats(): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($this->filters ?? []);

        return [
            $this->getTotalSalesStat($startDate, $endDate),
            $this->getOperationsCountStat($startDate, $endDate),
        ];
    }

    private function getOperationsCountStat(Carbon $startDate, Carbon $endDate): Stat
    {
        $count = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->where('billed', true)
            ->count();

        $dateRange = $this->humanRangeLabel($startDate, $endDate);

        return Stat::make('Órdenes Facturadas', number_format($count))
            ->description("Período {$dateRange}")
            ->descriptionIcon('heroicon-m-calculator')
            ->color('primary');
    }

    private function getTotalSalesStat(Carbon $startDate, Carbon $endDate): Stat
    {
        $total = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->where('billed', true)
            ->sum('total');

        $dateRange = $this->humanRangeLabel($startDate, $endDate);

        return Stat::make('Ventas Facturadas', 'S/ ' . number_format($total, 2))
            ->description("Período {$dateRange}")
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('success');
    }
}
