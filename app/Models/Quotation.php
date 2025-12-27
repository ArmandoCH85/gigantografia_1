<?php

namespace App\Models;

use App\Traits\CalculatesIgv;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Models\Table;
use App\Models\CashRegister;

class Quotation extends Model
{
    use HasFactory, SoftDeletes, CalculatesIgv;

    /**
     * Estados disponibles para las cotizaciones.
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CONVERTED = 'converted';

    /**
     * Términos de pago disponibles.
     */
    const PAYMENT_TERMS_CASH = 'cash';
    const PAYMENT_TERMS_CREDIT_15 = 'credit_15';
    const PAYMENT_TERMS_CREDIT_30 = 'credit_30';
    const PAYMENT_TERMS_CREDIT_60 = 'credit_60';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'quotation_number',
        'customer_id',
        'user_id',
        'issue_date',
        'valid_until',
        'status',
        'subtotal',
        'tax',
        'discount',
        'total',
        'advance_payment',
        'advance_payment_notes',
        'notes',
        'terms_and_conditions',
        'payment_terms',
        'order_id',
        'commercial_employee_id' // Comercial asignado
    ];

    /**
     * Los atributos que tienen valores predeterminados.
     *
     * @var array
     */
    protected $attributes = [
        'subtotal' => 0,
        'tax' => 0,
        'discount' => 0,
        'total' => 0,
        'advance_payment' => 0,
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'issue_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Obtiene el cliente asociado a la cotización.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Obtiene el usuario que creó la cotización.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene el empleado comercial asignado a la cotización.
     */
    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'commercial_employee_id');
    }

    /**
     * Obtiene los detalles de la cotización.
     */
    public function details(): HasMany
    {
        return $this->hasMany(QuotationDetail::class);
    }

    /**
     * Obtiene el pedido generado a partir de esta cotización.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Genera un número de cotización único.
     */
    public static function generateQuotationNumber(): string
    {
        $prefix = 'COT-';
        $year = date('Y');
        $month = date('m');

        // Obtener la última cotización del mes actual
        $lastQuotation = self::where('quotation_number', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastQuotation) {
            // Extraer el número secuencial y aumentarlo en 1
            $lastNumber = (int) substr($lastQuotation->quotation_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            // Si no hay cotizaciones previas en este mes, comenzar desde 1
            $newNumber = 1;
        }

        // Formatear el número con ceros a la izquierda (4 dígitos)
        $formattedNumber = str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        return "{$prefix}{$year}{$month}{$formattedNumber}";
    }

    /**
     * Método auxiliar para verificar si la cotización tiene un pedido asociado.
     */
    public function hasOrder(): bool
    {
        return $this->order_id !== null;
    }

    /**
     * Verifica si la cotización está en estado borrador.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Verifica si la cotización ha sido enviada.
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Verifica si la cotización ha sido aprobada.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Verifica si la cotización ha sido rechazada.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Verifica si la cotización ha expirado.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED ||
            ($this->valid_until < now() && !$this->isConverted());
    }

    /**
     * Verifica si la cotización ha sido convertida a pedido.
     */
    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTED;
    }

    /**
     * Recalcula los totales de la cotización basado en sus detalles.
     *
     * @return void
     */
    public function recalculateTotals(): void
    {
        // Recargar la relación para asegurarnos de tener los datos más recientes
        $this->load('details');

        // Calcular el subtotal sumando todos los subtotales de los detalles
        $subtotal = $this->details->sum('subtotal');

        // Si no hay subtotal pero hay detalles, calcular basado en cantidad y precio
        if ($subtotal == 0 && $this->details->count() > 0) {
            $subtotal = $this->details->sum(function ($detail) {
                return floatval($detail->quantity) * floatval($detail->unit_price);
            });

            // Actualizar los subtotales de los detalles
            foreach ($this->details as $detail) {
                if (floatval($detail->subtotal) == 0 && floatval($detail->quantity) > 0 && floatval($detail->unit_price) > 0) {
                    $detail->subtotal = floatval($detail->quantity) * floatval($detail->unit_price);
                    $detail->save();
                }
            }
        }

        // Los precios ya incluyen IGV, calcular IGV incluido correctamente
        $totalWithIgv = $subtotal;
        $totalWithIgvAfterDiscount = $totalWithIgv - floatval($this->discount ?? 0);

        // Calcular subtotal sin IGV e IGV incluido usando el trait
        $subtotalWithoutIgv = $this->calculateSubtotalFromPriceWithIgv($totalWithIgvAfterDiscount);
        $includedIgv = $this->calculateIncludedIgv($totalWithIgvAfterDiscount);

        // El total final es el precio con IGV después del descuento
        $total = $totalWithIgvAfterDiscount;

        // Actualizar los valores con cálculo correcto de IGV incluido
        $this->subtotal = $subtotalWithoutIgv;
        $this->tax = $includedIgv;
        $this->total = $total;

        // Guardar los cambios sin disparar eventos para evitar recursión
        $this->saveQuietly();

        // Registrar para depuración
        \Illuminate\Support\Facades\Log::info('Totales recalculados para cotización #' . $this->id, [
            'total_with_igv_before_discount' => $totalWithIgv,
            'discount' => $this->discount,
            'total_with_igv_after_discount' => $totalWithIgvAfterDiscount,
            'subtotal_without_igv' => $subtotalWithoutIgv,
            'included_igv' => $includedIgv,
            'final_total' => $total,
            'detalles_count' => $this->details->count()
        ]);
    }

    /**
     * Convierte la cotización en un pedido.
     *
     * @param string $serviceType Tipo de servicio ('dine_in', 'takeout', 'delivery', 'drive_thru')
     * @param int|null $tableId ID de la mesa (solo para 'dine_in')
     * @return Order|null El pedido creado o null si hubo un error
     */
    public function convertToOrder(string $serviceType = 'takeout', ?int $tableId = null): Order
    {
        // Verificar si la cotización ya fue convertida
        if ($this->isConverted() || $this->order_id) {
            return $this->order;
        }

        // Iniciar transacción para asegurar la integridad de los datos
        return \Illuminate\Support\Facades\DB::transaction(function () use ($serviceType, $tableId) {
            // Obtener la caja registradora activa
            $activeCashRegister = CashRegister::getOpenRegister();

            if (!$activeCashRegister) {
                // Verificar si hay alguna caja configurada
                $count = CashRegister::count();
                if ($count === 0) {
                    throw new \Exception('No hay ninguna Caja Registradora configurada en el sistema.');
                }
                throw new \Exception('No hay una caja registradora ABIERTA. Debe abrir caja desde el menú POS antes de facturar.');
            }

            // Crear el pedido
            $order = new Order([
                'service_type' => $serviceType,
                'table_id' => $tableId,
                'customer_id' => $this->customer_id,
                'employee_id' => $this->user_id, // Usar el usuario de la cotización como empleado
                'order_datetime' => now(),
                'status' => Order::STATUS_OPEN,
                'subtotal' => $this->subtotal,
                'tax' => $this->tax,
                'discount' => $this->discount,
                'total' => $this->total,
                'notes' => $this->buildOrderNotes(),
                'billed' => false,
                'cash_register_id' => $activeCashRegister->id
            ]);

            $order->save();

            // Crear los detalles del pedido a partir de los detalles de la cotización
            foreach ($this->details as $detail) {
                $orderDetail = new OrderDetail([
                    'order_id' => $order->id,
                    'product_id' => $detail->product_id,
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price,
                    'subtotal' => $detail->subtotal,
                    'notes' => $detail->notes,
                    'status' => 'pending'
                ]);

                $orderDetail->save();
            }

            // Actualizar la cotización
            $this->status = self::STATUS_CONVERTED;
            $this->order_id = $order->id;
            $this->save();

            // Si es para servicio en local, actualizar el estado de la mesa
            if ($serviceType === 'dine_in' && $tableId) {
                $table = Table::find($tableId);
                if ($table) {
                    $table->status = Table::STATUS_OCCUPIED;
                    $table->occupied_at = now();
                    $table->save();
                }
            }

            return $order;
        });
    }

    /**
     * Verifica si la cotización tiene un anticipo.
     *
     * @return bool
     */
    public function hasAdvancePayment(): bool
    {
        return $this->advance_payment > 0;
    }

    /**
     * Obtiene el saldo pendiente después del anticipo.
     *
     * @return float
     */
    public function getPendingBalance(): float
    {
        return $this->total - $this->advance_payment;
    }

    /**
     * Obtiene el porcentaje del anticipo respecto al total.
     *
     * @return float
     */
    public function getAdvancePaymentPercentage(): float
    {
        if ($this->total <= 0) {
            return 0;
        }

        return ($this->advance_payment / $this->total) * 100;
    }

    /**
     * Valida que el anticipo no sea mayor al total.
     *
     * @return bool
     */
    public function isAdvancePaymentValid(): bool
    {
        return $this->advance_payment <= $this->total;
    }

    /**
     * Construye las notas para la orden cuando se convierte desde cotización.
     *
     * @return string
     */
    private function buildOrderNotes(): string
    {
        $notes = "Convertido desde cotización #{$this->quotation_number}.";

        if ($this->hasAdvancePayment()) {
            $notes .= " ANTICIPO RECIBIDO: S/ " . number_format($this->advance_payment, 2);
            $notes .= " - SALDO PENDIENTE: S/ " . number_format($this->getPendingBalance(), 2);

            if ($this->advance_payment_notes) {
                $notes .= " - Notas del anticipo: " . $this->advance_payment_notes;
            }
        }

        if ($this->notes) {
            $notes .= " " . $this->notes;
        }

        return $notes;
    }
}
