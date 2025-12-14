<?php

namespace App\Filament\Resources\ProductFinishResource\Pages;

use App\Filament\Resources\ProductFinishResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductFinishes extends ListRecords
{
    protected static string $resource = ProductFinishResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
