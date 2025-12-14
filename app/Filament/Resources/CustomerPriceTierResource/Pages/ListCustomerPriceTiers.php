<?php

namespace App\Filament\Resources\CustomerPriceTierResource\Pages;

use App\Filament\Resources\CustomerPriceTierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerPriceTiers extends ListRecords
{
    protected static string $resource = CustomerPriceTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
