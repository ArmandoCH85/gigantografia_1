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
        'additional_cost',
        'requires_quantity',
        'active'
    ];

    protected $casts = [
        'additional_cost' => 'decimal:2',
        'requires_quantity' => 'boolean',
        'active' => 'boolean'
    ];
}
