<?php

namespace App\Filament\Resources\FuelStorageResource\Pages;

use App\Filament\Resources\FuelStorageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFuelStorage extends EditRecord
{
    protected static string $resource = FuelStorageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
