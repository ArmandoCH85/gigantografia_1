<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPriceTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'discount_percentage',
        'active'
    ];

    protected $casts = [
        'discount_percentage' => 'decimal:2',
        'active' => 'boolean'
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'price_tier_id');
    }
}
