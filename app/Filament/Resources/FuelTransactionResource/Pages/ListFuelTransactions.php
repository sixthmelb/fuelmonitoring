<?php

namespace App\Filament\Resources\FuelTransactionResource\Pages;

use App\Filament\Resources\FuelTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFuelTransactions extends ListRecords
{
    protected static string $resource = FuelTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
