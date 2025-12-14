<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory;

    protected $guarded = [];

    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';

    public static function getOpenRegister()
    {
        return self::where('status', self::STATUS_OPEN)->first();
    }
}
