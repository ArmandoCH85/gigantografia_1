<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductFinish extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'cost_per_unit',
        'cost_formula',
        'formula_type',
        'requires_quantity',
        'active'
    ];

    protected $casts = [
        'cost_per_unit' => 'decimal:2',
        'requires_quantity' => 'boolean',
        'active' => 'boolean'
    ];
}
