<?php

namespace App\Filament\Resources\ProductFinishResource\Pages;

use App\Filament\Resources\ProductFinishResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductFinish extends EditRecord
{
    protected static string $resource = ProductFinishResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
