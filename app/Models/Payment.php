<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'reference_number',
        'payment_datetime',
        'received_by',
        'installment_number', // Número de adelanto (1-5)
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'payment_datetime' => 'datetime',
    ];

    /**
     * Métodos de pago disponibles
     */
    const METHOD_CASH = 'cash';
    const METHOD_CARD = 'card';
    const METHOD_CREDIT_CARD = 'credit_card';
    const METHOD_DEBIT_CARD = 'debit_card';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_DIGITAL_WALLET = 'digital_wallet';
    const METHOD_RAPPI = 'rappi';
    const METHOD_BITA_EXPRESS = 'bita_express';

    /**
     * Obtiene la orden asociada al pago.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Obtiene el usuario que recibió el pago.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Devuelve el nombre legible del método de pago.
     */
    public function getPaymentMethodNameAttribute(): string
    {
        // Primero verificar si es una billetera digital y tiene un tipo específico
        if ($this->payment_method === self::METHOD_DIGITAL_WALLET && $this->reference_number) {
            if (strpos($this->reference_number, 'Tipo: yape') !== false) {
                return 'Yape';
            } elseif (strpos($this->reference_number, 'Tipo: plin') !== false) {
                return 'Plin';
            }
        }

        // Si no es un caso especial, usar el match normal
        return match ($this->payment_method) {
            self::METHOD_CASH => 'Efectivo',
            self::METHOD_CARD => 'Tarjeta',
            self::METHOD_BANK_TRANSFER => 'Transferencia Bancaria',
            self::METHOD_DIGITAL_WALLET => 'Billetera Digital',
            self::METHOD_RAPPI => 'Rappi',
            self::METHOD_BITA_EXPRESS => 'Bita Express',
            default => $this->payment_method,
        };
    }
}
