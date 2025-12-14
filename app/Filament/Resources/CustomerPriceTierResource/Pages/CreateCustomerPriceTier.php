<?php

namespace App\Filament\Resources\CustomerPriceTierResource\Pages;

use App\Filament\Resources\CustomerPriceTierResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerPriceTier extends CreateRecord
{
    protected static string $resource = CustomerPriceTierResource::class;
}
