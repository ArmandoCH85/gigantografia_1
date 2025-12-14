<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'width',
        'height',
        'material_id',
        'finishes', // Nuevo campo JSON
        'calculated_price'
    ];

    protected $casts = [
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'calculated_price' => 'decimal:2',
        'finishes' => 'array', // Cast automático a array/json
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(ProductMaterial::class);
    }
}
