<?php

namespace App\Filament\Resources\CustomerPriceTierResource\Pages;

use App\Filament\Resources\CustomerPriceTierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerPriceTier extends EditRecord
{
    protected static string $resource = CustomerPriceTierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
