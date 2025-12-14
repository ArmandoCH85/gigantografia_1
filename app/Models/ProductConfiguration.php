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
        'finish_1_id',
        'finish_1_quantity',
        'finish_2_id',
        'finish_2_quantity',
        'finish_3_id',
        'finish_3_quantity',
        'calculated_price'
    ];

    protected $casts = [
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'calculated_price' => 'decimal:2',
        'finish_1_quantity' => 'integer',
        'finish_2_quantity' => 'integer',
        'finish_3_quantity' => 'integer',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(ProductMaterial::class);
    }

    public function finish1(): BelongsTo
    {
        return $this->belongsTo(ProductFinish::class, 'finish_1_id');
    }

    public function finish2(): BelongsTo
    {
        return $this->belongsTo(ProductFinish::class, 'finish_2_id');
    }

    public function finish3(): BelongsTo
    {
        return $this->belongsTo(ProductFinish::class, 'finish_3_id');
    }
}
