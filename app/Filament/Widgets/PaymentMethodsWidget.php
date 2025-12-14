<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use App\Models\Payment;
use Carbon\Carbon;
use App\Filament\Widgets\Concerns\DateRangeFilterTrait;

class PaymentMethodsWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use DateRangeFilterTrait;

    protected static ?string $heading = '💳 Distribución de Métodos de Pago';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '350px';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'sm' => 2,
        'md' => 2,
        'xl' => 3,
        '2xl' => 4,
    ];

    protected static bool $isLazy = false;

    protected $listeners = [
        'filtersFormUpdated' => '$refresh',
        'updateCharts' => '$refresh',
    ];

    protected function getData(): array
    {
        $data = $this->getPaymentMethodsData();

        return [
            'datasets' => [
                [
                    'data' => array_values($data['amounts']),
                    'backgroundColor' => [
                        '#10B981', // 💚 Verde - Efectivo
                        '#3B82F6', // 💙 Azul - Tarjetas
                        '#F59E0B', // 🟡 Ámbar - Yape
                        '#8B5CF6', // 💜 Púrpura - Plin
                        '#6B7280', // ⚫ Gris - Transferencias
                    ],
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => array_keys($data['amounts']),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 15,
                        'font' => [
                            'size' => 12,
                            'weight' => '500',
                        ],
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                    'titleColor' => '#ffffff',
                    'bodyColor' => '#ffffff',
                    'borderColor' => '#374151',
                    'borderWidth' => 1,
                    'cornerRadius' => 8,
                    'displayColors' => true,
                    'callbacks' => [
                        'label' => "function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return [
                                label + ': S/ ' + value.toLocaleString('es-PE', { minimumFractionDigits: 2 }),
                                'Participación: ' + percentage + '%'
                            ];
                        }",
                    ],
                ],
            ],
            'cutout' => '60%',
            'animation' => [
                'animateRotate' => true,
                'animateScale' => true,
                'duration' => 1000,
            ],
        ];
    }

    private function getPaymentMethodsData(): array
    {
        $query = Payment::query()
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.billed', true);

        [$start, $end] = $this->resolveDateRange($this->filters ?? []);
        $query->whereBetween('payments.created_at', [$start, $end]);

        $payments = $query->get();

        $amounts = [
            '💵 Efectivo' => 0,
            '💳 Tarjetas' => 0,
            '📱 Yape' => 0,
            '💙 Plin' => 0,
            '🏦 Transferencias' => 0,
        ];

        foreach ($payments as $payment) {
            $amount = (float) $payment->amount;
            $method = $payment->payment_method;

            if ($method === 'cash') {
                $amounts['💵 Efectivo'] += $amount;
            } elseif (in_array($method, ['credit_card', 'debit_card', 'card'])) {
                $amounts['💳 Tarjetas'] += $amount;
            } elseif ($method === 'digital_wallet' || $method === 'yape' || $method === 'plin') {
                // Heurística simple para diferenciar si no es explícito
                if ($method === 'plin' || (strpos(strtolower($payment->reference_number ?? ''), 'plin') !== false)) {
                    $amounts['💙 Plin'] += $amount;
                } else {
                    $amounts['📱 Yape'] += $amount;
                }
            } elseif (in_array($method, ['bank_transfer', 'transfer'])) {
                $amounts['🏦 Transferencias'] += $amount;
            }
        }

        $amounts = array_filter($amounts, fn($amount) => $amount > 0);

        $totalAmount = array_sum($amounts);
        $totalTransactions = $payments->count();

        return [
            'amounts' => $amounts,
            'total_amount' => $totalAmount,
            'total_transactions' => $totalTransactions,
        ];
    }
}
