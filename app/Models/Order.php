<?php

namespace App\Models;

use App\Traits\CalculatesIgv;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use App\Models\DocumentSeries;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use CalculatesIgv;

    /**
     * OPTIMIZACIÓN: Relaciones que se cargan por defecto para evitar N+1
     * Solo cargamos las más críticas para no sobrecargar
     */
    protected $with = ['customer'];

    /**
     * Estados disponibles para las órdenes.
     */
    const STATUS_OPEN = 'open';
    const STATUS_IN_PREPARATION = 'in_preparation';
    const STATUS_READY = 'ready';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',
        'employee_id',
        'designer_id', // Nuevo campo
        'production_number', // Nuevo campo
        'order_datetime',
        'status',
        'subtotal',
        'tax',
        'discount',
        'total',
        'notes',
        'billed',
        'payment_method',
        'payment_amount',
        'delivery_date', // Nuevo campo
        'delivery_time', // Nuevo campo
        'delivery_type', // Nuevo campo
        'production_start_time', // Nuevo campo
        'production_end_time', // Nuevo campo
        'delivery_province', // Nuevo campo
        'delivery_recipient_name', // Nuevo campo
        'delivery_recipient_phone', // Nuevo campo
        'delivery_recipient_dni', // Nuevo campo
        'delivery_destination', // Nuevo campo
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'order_datetime' => 'datetime',
        'delivery_date' => 'date', // Nuevo cast
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'billed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtiene el cliente asociado a la orden.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Obtiene el empleado que registró la orden.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Obtiene el diseñador asignado a la orden.
     */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'designer_id');
    }

    /**
     * Obtiene el usuario que registró la orden.
     * Esta es una relación alternativa que apunta a la misma columna que employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Obtiene el nombre del usuario/mesero que registró la orden.
     */
    public function getWaiterNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        // Si no hay user, intentar buscar directamente
        if ($this->employee_id) {
            $user = User::find($this->employee_id);
            if ($user) return $user->name;
        }

        return 'Sin mesero asignado';
    }

    /**
     * Obtiene los detalles de la orden.
     */
    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * Devuelve si la orden está abierta.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Devuelve si la orden está facturada.
     */
    public function isBilled(): bool
    {
        return $this->billed;
    }

    /**
     * Verifica si la orden es de tipo delivery.
     */
    public function isDelivery(): bool
    {
        return $this->delivery_type === 'tercero' || $this->delivery_type === 'instalacion'; // Ajustado a los nuevos tipos si aplica, o mantener lógica anterior
    }

    /**
     * Verifica si la orden es para consumo en el local.
     */
    public function isLocalPickup(): bool
    {
        return $this->delivery_type === 'local';
    }

    public function completeOrder(): bool
    {
        if (!$this->billed) {
            return false; // No se puede completar si no está facturada
        }

        $this->status = self::STATUS_COMPLETED;
        $this->save();

        return true;
    }

    /**
     * Cancela la orden, cambiando su estado a 'cancelled'.
     *
     * @param string|null $reason Motivo de la cancelación
     * @return bool
     */
    public function cancelOrder(?string $reason = null): bool
    {
        // No se puede cancelar una orden facturada
        if ($this->billed) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->notes = $reason ? ($this->notes ? $this->notes . "\n" . $reason : $reason) : $this->notes;
        $this->save();

        // Actualizar estado de los detalles de la orden
        $this->orderDetails()->update(['status' => 'cancelled']);

        return true;
    }

    /**
     * Añade un producto a la orden.
     *
     * @param int $productId ID del producto
     * @param int $quantity Cantidad
     * @param float|null $unitPrice Precio unitario (opcional, si no se proporciona se usa el precio del producto)
     * @param string|null $notes Notas adicionales
     * @return OrderDetail
     */
    public function addProduct(int $productId, int $quantity, ?float $unitPrice = null, ?string $notes = null): OrderDetail
    {
        $product = Product::findOrFail($productId);

        // Si no se proporciona un precio unitario, usar el precio del producto
        if ($unitPrice === null) {
            $unitPrice = $product->sale_price;
        }

        $subtotal = $unitPrice * $quantity;

        $orderDetail = new OrderDetail([
            'order_id' => $this->id,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'notes' => $notes,
            'status' => 'pending'
        ]);

        $orderDetail->save();

        // Recalcular totales de la orden
        $this->recalculateTotals();

        // Refrescar el modelo para asegurar que los cambios se reflejen
        $this->refresh();

        return $orderDetail;
    }

    /**
     * Actualiza la cantidad de un producto en la orden.
     *
     * @param int $orderDetailId ID del detalle de orden
     * @param int $quantity Nueva cantidad
     * @return bool
     */
    public function updateProductQuantity(int $orderDetailId, int $quantity): bool
    {
        $orderDetail = $this->orderDetails()->findOrFail($orderDetailId);

        if ($quantity <= 0) {
            // Si la cantidad es 0 o negativa, eliminar el producto
            $orderDetail->delete();
        } else {
            // Actualizar cantidad y subtotal
            $orderDetail->quantity = $quantity;
            $orderDetail->subtotal = $orderDetail->unit_price * $quantity;
            $orderDetail->save();
        }

        // Recalcular totales de la orden
        $this->recalculateTotals();

        return true;
    }

    /**
     * Actualiza el precio unitario de un producto en la orden.
     *
     * @param int $orderDetailId ID del detalle de orden
     * @param float $unitPrice Nuevo precio unitario
     * @return bool
     */
    public function updateProductPrice(int $orderDetailId, float $unitPrice): bool
    {
        $orderDetail = $this->orderDetails()->findOrFail($orderDetailId);

        // Actualizar precio y subtotal
        $orderDetail->unit_price = $unitPrice;
        $orderDetail->subtotal = $unitPrice * $orderDetail->quantity;
        $orderDetail->save();

        // Recalcular totales de la orden
        $this->recalculateTotals();

        return true;
    }

    /**
     * Elimina un producto de la orden.
     *
     * @param int $orderDetailId ID del detalle de orden
     * @return bool
     */
    public function removeProduct(int $orderDetailId): bool
    {
        $orderDetail = $this->orderDetails()->findOrFail($orderDetailId);
        $orderDetail->delete();

        // Recalcular totales de la orden
        $this->recalculateTotals();

        return true;
    }

    /**
     * Recalcula los totales de la orden basándose en los detalles.
     *
     * IMPORTANTE: Los precios en orderDetails YA INCLUYEN IGV
     * Este método calcula cuánto IGV está incluido en esos precios
     */
    public function recalculateTotals(): void
    {
        // Recargar la relación para asegurar datos actualizados
        $this->load('orderDetails');

        // El subtotal de orderDetails ya incluye IGV
        $totalWithIgv = $this->orderDetails->sum('subtotal');

        // Aplicar descuento al total con IGV
        $totalWithIgvAfterDiscount = $totalWithIgv - ($this->discount ?? 0);

        // Calcular el subtotal sin IGV y el IGV incluido
        $subtotalWithoutIgv = $this->calculateSubtotalFromPriceWithIgv($totalWithIgvAfterDiscount);
        $includedIgv = $this->calculateIncludedIgv($totalWithIgvAfterDiscount);

        // Actualizar los valores
        $this->subtotal = $subtotalWithoutIgv;
        $this->tax = $includedIgv;
        $this->total = $totalWithIgvAfterDiscount;

        // Guardar los cambios
        $this->save();

        // Log para depuración
        \Illuminate\Support\Facades\Log::info('Totales recalculados para orden #' . $this->id, [
            'total_with_igv_before_discount' => $totalWithIgv,
            'discount' => $this->discount,
            'total_with_igv_after_discount' => $totalWithIgvAfterDiscount,
            'subtotal_without_igv' => $subtotalWithoutIgv,
            'included_igv' => $includedIgv,
            'final_total' => $this->total,
            'details_count' => $this->orderDetails->count()
        ]);
    }

    /**
     * Aplica un descuento a la orden.
     *
     * @param float $amount Monto del descuento
     * @param string $type Tipo de descuento ('fixed' o 'percentage')
     * @return void
     */
    public function applyDiscount(float $amount, string $type = 'fixed'): void
    {
        if ($type === 'percentage') {
            $this->discount = ($this->subtotal * $amount) / 100;
        } else {
            $this->discount = $amount;
        }

        $this->recalculateTotals();
    }

    /**
     * Obtiene los pagos asociados a la orden.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Obtiene el seguimiento de producción asociado a la orden.
     */
    public function productionTracking(): HasOne
    {
        return $this->hasOne(ProductionTracking::class);
    }

    /**
     * Registra un nuevo pago para la orden.
     *
     * @param string $paymentMethod Método de pago
     * @param float $amount Monto del pago
     * @param string|null $reference Referencia del pago (número de transacción, etc.)
     * @return Payment
     * @throws \Exception Si no hay una caja abierta para pagos en efectivo
     */
    public function registerPayment(string $paymentMethod, float $amount, ?string $reference = null): Payment
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($paymentMethod, $amount, $reference) {

            // Crear el registro de pago
            $payment = new Payment([
                'order_id' => $this->id,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'reference_number' => $reference,
                'payment_datetime' => now(),
                'received_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);

            $payment->save();

            // Emitir evento para que los listeners puedan actualizar otros componentes
            event(new \App\Events\PaymentRegistered($payment));

            return $payment;
        });
    }



    /**
     * Obtiene el total pagado de la orden.
     *
     * @return float
     */
    public function getTotalPaid(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Obtiene el saldo pendiente de la orden.
     *
     * @return float
     */
    public function getRemainingBalance(): float
    {
        return $this->total - $this->getTotalPaid();
    }

    /**
     * Verifica si la orden está completamente pagada.
     *
     * @return bool
     */
    public function isFullyPaid(): bool
    {
        return abs($this->getRemainingBalance()) < 0.01;
    }

    /**
     * Obtiene las facturas asociadas a la orden.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Obtiene la cotización original si esta orden fue convertida desde una cotización.
     */
    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class);
    }

    /**
     * Verifica si esta orden proviene de una cotización con anticipo.
     *
     * @return bool
     */
    public function hasQuotationWithAdvance(): bool
    {
        $quotation = $this->quotation;
        return $quotation && $quotation->hasAdvancePayment();
    }

    /**
     * Obtiene el anticipo de la cotización original si existe.
     *
     * @return float
     */
    public function getQuotationAdvancePayment(): float
    {
        $quotation = $this->quotation;
        return $quotation ? $quotation->advance_payment : 0;
    }

    /**
     * Obtiene las notas del anticipo de la cotización original si existe.
     *
     * @return string|null
     */
    public function getQuotationAdvanceNotes(): ?string
    {
        $quotation = $this->quotation;
        return $quotation ? $quotation->advance_payment_notes : null;
    }

    /**
     * Genera una factura para la orden.
     *
     * @param string $invoiceType Tipo de factura ('receipt', 'invoice', etc.)
     * @param string $series Serie del comprobante
     * @param int $customerId ID del cliente
     * @return Invoice|null
     */
    public function generateInvoice(string $invoiceType, string $series, int $customerId): ?Invoice
    {
        try {
            return DB::transaction(function () use ($invoiceType, $series, $customerId) {
                // 1. Obtener el siguiente número de factura de forma segura
                $lastInvoice = Invoice::where('series', $series)->lockForUpdate()->latest('number')->first();
                $nextNumber = $lastInvoice ? ((int) $lastInvoice->number) + 1 : 1;

                // 2. Formatear el número con ceros a la izquierda
                $formattedNumber = str_pad($nextNumber, 8, '0', STR_PAD_LEFT);

                // 3. Obtener el cliente
                $customer = Customer::find($customerId);

                // Verificar que el cliente existe
                if (!$customer) {
                    \Illuminate\Support\Facades\Log::error('❌ Cliente no encontrado', ['customer_id' => $customerId]);
                    throw new \Exception("No se encontró el cliente con ID: {$customerId}");
                }

                \Illuminate\Support\Facades\Log::info('📋 Generando factura', [
                    'order_id' => $this->id,
                    'invoice_type' => $invoiceType,
                    'series' => $series,
                    'customer_id' => $customerId,
                    'payment_method' => $this->payment_method,
                    'payment_amount' => $this->payment_amount
                ]);

                // 4. Calcular correctamente subtotal e IGV desde el total
                $correctSubtotal = $this->total / 1.18; // Subtotal sin IGV
                $correctIgv = $this->total - $correctSubtotal; // IGV incluido

                // 5. Guardar el tipo de documento tal como se seleccionó (sin mapear)
                $invoiceTypeForDb = $invoiceType;

                // 6. Establecer estado SUNAT según el tipo de comprobante
                $sunatStatus = in_array($invoiceType, ['invoice', 'receipt']) ? 'PENDIENTE' : null;

                // 7. REFRESCAR relación de pagos para asegurar datos actualizados
                $this->load('payments'); // ✅ FORZAR REFRESH DE PAGOS

                // Calcular información de pagos para el comprobante
                $totalPaid = $this->getTotalPaid();
                $cashPayments = $this->payments()->where('payment_method', 'cash')->get();
                $hasCashPayment = $cashPayments->isNotEmpty();
                $cashAmount = $cashPayments->sum('amount');

                // Determinar método de pago principal para mostrar en el comprobante
                $primaryPaymentMethod = 'cash'; // Por defecto efectivo

                // ✅ LOG PARA DEBUG
                \Illuminate\Support\Facades\Log::info('🔍 Analizando pagos para factura', [
                    'order_id' => $this->id,
                    'payments_count' => $this->payments()->count(),
                    'payments_data' => $this->payments->map(function ($p) {
                        return ['method' => $p->payment_method, 'amount' => $p->amount];
                    })->toArray()
                ]);

                if ($this->payments()->count() === 1) {
                    // Si hay un solo pago, usar ese método
                    $primaryPaymentMethod = $this->payments()->first()->payment_method;
                    \Illuminate\Support\Facades\Log::info('✅ Un solo pago detectado', [
                        'payment_method' => $primaryPaymentMethod
                    ]);
                } elseif ($this->payments()->count() > 1) {
                    // Si hay múltiples pagos, mostrar como "mixto"
                    $primaryPaymentMethod = 'mixto';
                    \Illuminate\Support\Facades\Log::info('✅ Múltiples pagos detectados', [
                        'payment_method' => 'mixto',
                        'payments_count' => $this->payments()->count()
                    ]);
                } else {
                    \Illuminate\Support\Facades\Log::warning('❌ No se encontraron pagos', [
                        'payment_method' => 'cash (default)',
                        'order_id' => $this->id
                    ]);
                }

                // Calcular vuelto solo si hay pago en efectivo y exceso
                $changeAmount = 0;
                if ($hasCashPayment && $totalPaid > $this->total) {
                    $changeAmount = $totalPaid - $this->total;
                }

                // 8. Crear la factura
                $invoice = Invoice::create([
                    'order_id' => $this->id,
                    'invoice_type' => $invoiceTypeForDb,
                    'series' => $series,
                    'number' => $formattedNumber,
                    'issue_date' => now(),
                    'customer_id' => $customer->id,
                    'employee_id' => $this->employee_id, // ✅ AGREGAR: Asignar el mesero que registró la orden
                    'client_name' => $customer->name,
                    'client_document' => $customer->document_number,
                    'client_address' => $customer->address,
                    'taxable_amount' => round($correctSubtotal, 2),
                    'tax' => round($correctIgv, 2),
                    'total' => $this->total,
                    'payment_method' => $primaryPaymentMethod,
                    'payment_amount' => $totalPaid,
                    'change_amount' => $changeAmount,
                    'status' => 'issued',
                    'sunat_status' => $sunatStatus,
                ]);

                // 8. Agregar detalles de la factura
                foreach ($this->orderDetails as $detail) {
                    $invoice->details()->create([
                        'product_id' => $detail->product_id,
                        'quantity' => $detail->quantity,
                        'unit_price' => $detail->unit_price,
                        'subtotal' => $detail->subtotal,
                        'description' => $detail->product->name,
                    ]);
                }

                // 9. Actualizar el correlativo en la serie del documento
                $documentSeries = DocumentSeries::where('series', $series)->first();
                if ($documentSeries) {
                    $documentSeries->increment('current_number');
                }

                \Illuminate\Support\Facades\Log::info('✅ Factura generada exitosamente', [
                    'invoice_id' => $invoice->id,
                    'series' => $invoice->series,
                    'number' => $invoice->number
                ]);

                return $invoice;
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('❌ Error generando factura', [
                'order_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Imprime la factura de la orden.
     *
     * @param int $invoiceId ID de la factura a imprimir
     * @return string|null URL del PDF de la factura
     */
    public function printInvoice(int $invoiceId): ?string
    {
        $invoice = $this->invoices()->findOrFail($invoiceId);

        // En una implementación real, aquí se generaría el PDF de la factura
        // y se enviaría a la impresora o se devolvería la URL del PDF

        // Por ahora, devolvemos una URL de ejemplo
        return route('invoices.print', $invoice->id);
    }

    /**
     * Eventos del modelo.
     */
    protected static function booted()
    {
        // Cuando se actualiza una orden
        static::updating(function ($order) {
            // Si el estado cambió a 'in_preparation', procesar las recetas
            if ($order->isDirty('status') && $order->status === self::STATUS_IN_PREPARATION) {
                $result = $order->processRecipes();

                // Registrar el resultado del procesamiento
                \Illuminate\Support\Facades\Log::info('Procesamiento de recetas para orden #' . $order->id, $result);
            }
        });
    }
}
