<?php

namespace App\Filament\Resources\FuelTruckResource\Pages;

use App\Filament\Resources\FuelTruckResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFuelTruck extends EditRecord
{
    protected static string $resource = FuelTruckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
