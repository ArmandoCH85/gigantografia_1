<x-filament-panels::page>
    <div class="flex flex-col items-center justify-center space-y-6">

        @if(!$hasOpenRegister)
            {{-- VISTA DE CAJA CERRADA --}}
            <div
                class="w-full max-w-2xl text-center p-10 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                <div class="mb-6 flex justify-center">
                    <div class="p-4 bg-gray-100 rounded-full dark:bg-gray-700">
                        <x-heroicon-o-lock-closed class="w-16 h-16 text-gray-400" />
                    </div>
                </div>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    La Caja está Cerrada
                </h2>
                <p class="text-gray-500 dark:text-gray-400 mb-8">
                    No hay registro de ventas activo en este momento.
                    <br>Inicia el día aperturando la caja.
                </p>

                <div class="flex justify-center">
                    {{ $this->openRegisterAction }}
                </div>
            </div>

        @else
            {{-- VISTA DE CAJA ABIERTA --}}
            <div class="w-full grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Tarjetas de Resumen -->
                <div
                    class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 flex flex-col items-center">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Monto Inicial
                    </p>
                    <p class="text-3xl font-bold text-blue-600 mt-2">S/ {{ number_format($openingAmount, 2) }}</p>
                </div>

                <div
                    class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 flex flex-col items-center relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-2 opacity-10">
                        <x-heroicon-o-banknotes class="w-16 h-16" />
                    </div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ventas del Día
                    </p>
                    <p class="text-3xl font-bold text-green-600 mt-2">S/ {{ number_format($dailySales, 2) }}</p>
                    <p class="text-xs text-gray-400 mt-1">Calculado automáticamente</p>
                </div>

                <div
                    class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 flex flex-col items-center ring-2 ring-indigo-50 dark:ring-indigo-900">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total en Caja
                    </p>
                    <p class="text-4xl font-extrabold text-indigo-600 mt-2">S/ {{ number_format($totalCash, 2) }}</p>
                    <p class="text-xs text-gray-400 mt-1">Esperado</p>
                </div>
            </div>

            <!-- Panel de Control / Cierre -->
            <div
                class="w-full max-w-4xl p-8 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-green-100 rounded-full dark:bg-green-900/30">
                        <x-heroicon-o-check-circle class="w-8 h-8 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Caja Activa</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Abierta el
                            {{ $openRegister->opening_datetime ? \Carbon\Carbon::parse($openRegister->opening_datetime)->format('d/m/Y h:i A') : '--' }}
                        </p>
                    </div>
                </div>

                <div>
                    {{ $this->closeRegisterAction }}
                </div>
            </div>

            <!-- Tabla de resumen simple (opcional, visual) -->
            <div class="w-full max-w-4xl mt-4">
                <div
                    class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800 flex items-start gap-3">
                    <x-heroicon-o-information-circle class="w-5 h-5 text-blue-500 mt-0.5" />
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Las <strong>Ventas del Día</strong> se calculan sumando todos los pagos registrados desde la hora de
                        apertura.
                        No incluyen pagos anulados o pendientes.
                    </p>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>