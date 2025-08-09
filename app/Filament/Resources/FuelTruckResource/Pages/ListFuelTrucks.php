<?php

namespace App\Filament\Resources\FuelTruckResource\Pages;

use App\Filament\Resources\FuelTruckResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFuelTrucks extends ListRecords
{
    protected static string $resource = FuelTruckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
