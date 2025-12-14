<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTracking extends Model
{
    use HasFactory;

    protected $table = 'production_tracking';

    protected $fillable = [
        'order_id',
        'production_number',
        'responsible_employee_id',
        'supervisor_employee_id',
        'material_code',
        'material_description',
        'started_at',
        'completed_at',
        'material_used',
        'material_waste',
        'material_missing',
        'detail',
        'production_cost',
        'notes'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'material_used' => 'decimal:2',
        'material_waste' => 'decimal:2',
        'material_missing' => 'decimal:2',
        'production_cost' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
    }
}
