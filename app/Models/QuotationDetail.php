<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationDetail extends Model
{
    use HasFactory;

    /**
     * Los eventos del modelo.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => \App\Events\QuotationDetailCreated::class,
        'updated' => \App\Events\QuotationDetailUpdated::class,
        'deleted' => \App\Events\QuotationDetailDeleted::class,
    ];

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'quotation_id',
        'product_id',
        'quantity',
        'width',
        'height',
        'material_id',
        'finishes',
        'unit_price',
        'subtotal',
        'notes'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'quantity' => 'integer',
        'finishes' => 'array',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Obtiene la cotización a la que pertenece este detalle.
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Obtiene el producto asociado a este detalle.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Obtiene el material asociado a este detalle.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(ProductMaterial::class);
    }

    /**
     * Obtiene la configuración específica del producto si existe.
     */
    public function configuration()
    {
        return $this->morphOne(ProductConfiguration::class, 'reference');
    }
}
