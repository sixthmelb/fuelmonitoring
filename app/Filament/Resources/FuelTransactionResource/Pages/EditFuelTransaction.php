<?php

namespace App\Filament\Resources\FuelTransactionResource\Pages;

use App\Filament\Resources\FuelTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFuelTransaction extends EditRecord
{
    protected static string $resource = FuelTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
