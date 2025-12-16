<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'code',
        'unit_price', // Mantener por compatibilidad si es necesario, aunque la migración lo eliminó
        'price_factor_standard',
        'price_factor_wholesale',
        'price_factor_campaign',
        'sheet_width',
        'max_width',
        'max_height',
        'active'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'price_factor_standard' => 'decimal:2',
        'price_factor_wholesale' => 'decimal:2',
        'price_factor_campaign' => 'decimal:2',
        'sheet_width' => 'decimal:2',
        'max_width' => 'decimal:2',
        'max_height' => 'decimal:2',
        'active' => 'boolean'
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
