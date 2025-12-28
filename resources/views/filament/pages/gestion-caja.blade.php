<x-filament-panels::page>
    <div class="space-y-8">
        {{-- HEADER / STATUS SECTION --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-6 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-5">
                <div class="relative">
                    <div class="p-4 {{ $hasOpenRegister ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-gray-100 dark:bg-gray-700' }} rounded-2xl">
                        @if($hasOpenRegister)
                            <x-heroicon-o-lock-open class="w-8 h-8 text-emerald-600 dark:text-emerald-400" />
                            <span class="absolute -top-1 -right-1 flex h-4 w-4">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-4 w-4 bg-emerald-500 border-2 border-white dark:border-gray-800"></span>
                            </span>
                        @else
                            <x-heroicon-o-lock-closed class="w-8 h-8 text-gray-500 dark:text-gray-400" />
                        @endif
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ $hasOpenRegister ? 'Caja Aperturada' : 'Caja Cerrada' }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @if($hasOpenRegister)
                            Iniciada el {{ $openRegister->opening_datetime ? \Carbon\Carbon::parse($openRegister->opening_datetime)->format('d/m/Y h:i A') : '--' }}
                        @else
                            No hay un registro de ventas activo.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                @if($hasOpenRegister)
                    <div class="hidden lg:block text-right mr-4">
                        <p class="text-xs text-gray-400 uppercase font-black">Dinero en Efectivo</p>
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 tracking-tighter">S/ {{ number_format($totalCash, 2) }}</p>
                    </div>
                    {{ $this->closeRegisterAction }}
                @else
                    {{ $this->openRegisterAction }}
                @endif
            </div>
        </div>

        @if($hasOpenRegister)
            {{-- STATS GRID --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Main Balance -->
                <div class="relative overflow-hidden group bg-gradient-to-br from-indigo-600 to-indigo-700 p-6 rounded-2xl shadow-lg shadow-indigo-200 dark:shadow-none">
                    <div class="absolute -right-4 -bottom-4 opacity-10 transition-transform group-hover:scale-110 duration-500">
                        <x-heroicon-o-banknotes class="w-24 h-24 text-white" />
                    </div>
                    <p class="text-sm font-medium text-indigo-100 uppercase tracking-wider">Apertura (Caja Inicial)</p>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-4xl font-black text-white leading-none">S/ {{ number_format($openingAmount, 2) }}</span>
                    </div>
                    <div class="mt-4 text-indigo-200 text-xs flex items-center gap-1">
                        <x-heroicon-m-calendar-days class="w-3 h-3" />
                        Monto reportado al iniciar
                    </div>
                </div>

                <!-- Total Sales -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <x-heroicon-m-shopping-cart class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <span class="text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-tight">Ventas Totales</span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Recaudado</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white mt-1">S/ {{ number_format($dailySales, 2) }}</p>
                    <div class="mt-2 h-1.5 w-full bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <!-- Cash Flow -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                            <x-heroicon-m-currency-dollar class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <span class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-tight">Efectivo</span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total en Efectivo</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white mt-1">S/ {{ number_format($cashPayments, 2) }}</p>
                    <div class="mt-2 flex items-center gap-1 text-[10px] text-gray-400">
                        <span class="font-bold text-emerald-600">{{ $dailySales > 0 ? round(($cashPayments / $dailySales) * 100) : 0 }}%</span> del total de ventas
                    </div>
                </div>

                <!-- Digital/Cards -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                            <x-heroicon-m-credit-card class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <span class="text-[10px] bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-bold uppercase tracking-tight">Otros Medios</span>
                    </div>
                    @php $otherTotal = $cardPayments + $transferPayments + $digitalWalletPayments + $otherPayments; @endphp
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tarjetas, Transf, Yape/Plin</p>
                    <p class="text-2xl font-black text-gray-900 dark:text-white mt-1">S/ {{ number_format($otherTotal, 2) }}</p>
                    <div class="mt-2 flex items-center gap-1 text-[10px] text-gray-400">
                        <span class="font-bold text-purple-600">{{ $dailySales > 0 ? round(($otherTotal / $dailySales) * 100) : 0 }}%</span> del total de ventas
                    </div>
                </div>
            </div>

            {{-- SECONDARY SECTION: BREAKDOWN AND RECENT --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- PAYMENT METHOD BREAKDOWN --}}
                <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 dark:border-gray-700">
                        <h3 class="font-bold text-gray-900 dark:text-white">Desglose de Pagos</h3>
                        <p class="text-xs text-gray-400">Distribución por método de recepción</p>
                    </div>
                    <div class="p-6 space-y-6">
                        @foreach([
                            ['label' => 'Efectivo', 'amount' => $cashPayments, 'color' => 'bg-emerald-500', 'icon' => 'heroicon-o-banknotes'],
                            ['label' => 'Tarjetas', 'amount' => $cardPayments, 'color' => 'bg-blue-500', 'icon' => 'heroicon-o-credit-card'],
                            ['label' => 'Transferencias', 'amount' => $transferPayments, 'color' => 'bg-indigo-500', 'icon' => 'heroicon-o-building-library'],
                            ['label' => 'Yape / Plin', 'amount' => $digitalWalletPayments, 'color' => 'bg-purple-500', 'icon' => 'heroicon-o-device-phone-mobile'],
                            ['label' => 'Otros', 'amount' => $otherPayments, 'color' => 'bg-gray-400', 'icon' => 'heroicon-o-ellipsis-horizontal-circle'],
                        ] as $method)
                        <div class="group">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="p-1.5 rounded-lg {{ $method['color'] }} bg-opacity-10 text-{{ str_replace('bg-', '', $method['color']) }}">
                                        <x-dynamic-component :component="$method['icon']" class="w-4 h-4" />
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $method['label'] }}</span>
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">S/ {{ number_format($method['amount'], 2) }}</span>
                            </div>
                            <div class="h-1.5 w-full bg-gray-50 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full {{ $method['color'] }} transition-all duration-1000" style="width: {{ $dailySales > 0 ? ($method['amount'] / $dailySales) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="p-4 bg-gray-50 dark:bg-gray-700/50 mt-4 mx-4 mb-4 rounded-xl border border-dashed border-gray-200 dark:border-gray-600">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">Monto Esperado en Caja</span>
                            <span class="text-lg font-black text-indigo-600 dark:text-indigo-400">S/ {{ number_format($totalCash, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- RECENT TRANSACTIONS --}}
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 dark:text-white">Últimos Pagos</h3>
                            <p class="text-xs text-gray-400">Los 10 movimientos más recientes</p>
                        </div>
                        <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <x-heroicon-o-clock class="w-5 h-5 text-gray-400" />
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 dark:bg-gray-700/50">
                                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Hora</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Cliente / Orden</th>
                                    <th class="px-6 py-4 text-[10px] font-black text-gray-400 uppercase tracking-widest">Método</th>
                                    <th class="px-6 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                @forelse($recentPayments as $payment)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $payment->payment_datetime->format('h:i A') }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-bold text-gray-900 dark:text-white truncate max-w-[200px]">
                                                {{ $payment->order->customer->name ?? 'Cliente General' }}
                                            </span>
                                            <span class="text-[10px] text-indigo-500 font-medium">Orden #{{ $payment->order_id }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $methodColor = match($payment->payment_method) {
                                                'cash' => 'emerald',
                                                'card', 'credit_card', 'debit_card' => 'blue',
                                                'bank_transfer' => 'indigo',
                                                'digital_wallet' => 'purple',
                                                default => 'gray'
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-{{ $methodColor }}-100 text-{{ $methodColor }}-700 dark:bg-{{ $methodColor }}-900/30 dark:text-{{ $methodColor }}-400 border border-{{ $methodColor }}-200 dark:border-{{ $methodColor }}-800">
                                            {{ $payment->payment_method_name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-black text-gray-900 dark:text-white">
                                        S/ {{ number_format($payment->amount, 2) }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <x-heroicon-o-no-symbol class="w-12 h-12 text-gray-300 mb-2" />
                                            <p class="text-sm text-gray-400">No se han registrado pagos hoy</p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            {{-- EMPTY STATE (CLOSED) --}}
            <div class="flex flex-col items-center justify-center py-20 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="relative mb-8">
                    <div class="p-8 bg-gray-50 dark:bg-gray-700/50 rounded-full">
                        <x-heroicon-o-lock-closed class="w-24 h-24 text-gray-300 dark:text-gray-600" />
                    </div>
                    <div class="absolute -bottom-2 -right-2 p-3 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-50 dark:border-gray-700">
                        <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-amber-500" />
                    </div>
                </div>

                <h2 class="text-3xl font-black text-gray-900 dark:text-white mb-4 tracking-tight">
                    Caja no iniciada
                </h2>
                <p class="text-gray-500 dark:text-gray-400 text-center max-w-sm mb-10 leading-relaxed">
                    Para comenzar a registrar transacciones y gestionar las ventas del día, es necesario realizar la apertura de caja.
                </p>

                <div class="flex flex-col items-center gap-4">
                    {{ $this->openRegisterAction }}
                    <p class="text-[10px] text-gray-400 uppercase font-bold tracking-widest">Procedimiento obligatorio diario</p>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>