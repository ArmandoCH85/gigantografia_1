<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetail extends Model
{
    /**
     * OPTIMIZACIÓN: Relaciones que se cargan por defecto para evitar N+1
     */
    protected $with = ['product'];

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
        'notes',
        'status'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtiene la orden a la que pertenece este detalle.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Obtiene el producto asociado a este detalle.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Obtiene la configuración específica del producto si existe.
     */
    public function configuration()
    {
        return $this->morphOne(ProductConfiguration::class, 'reference');
    }

    /**
     * Verifica si el detalle está pendiente de preparación.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica si el detalle está en preparación.
     */
    public function isInPreparation(): bool
    {
        return $this->status === 'in_preparation';
    }

    /**
     * Verifica si el detalle está listo para servir.
     */
    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    /**
     * Verifica si el detalle ya fue entregado.
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Verifica si el detalle fue cancelado.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
