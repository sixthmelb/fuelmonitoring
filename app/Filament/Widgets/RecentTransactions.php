<?php

namespace App\Filament\Widgets;

use App\Models\FuelTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Command untuk membuat widget ini:
 * php artisan make:filament-widget RecentTransactions --table
 * 
 * Widget untuk menampilkan transaksi terbaru di dashboard
 */
class RecentTransactions extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    // Refresh setiap 60 detik
    protected static ?string $pollingInterval = '60s';
    
    protected static ?string $heading = 'Recent Fuel Transactions';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FuelTransaction::query()
                    ->with(['sourceStorage', 'sourceTruck', 'destinationStorage', 'destinationTruck', 'unit', 'createdBy'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('transaction_code')
                    ->label('Code')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('transaction_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => 
                        match($state) {
                            FuelTransaction::TYPE_VENDOR_TO_STORAGE => 'Supply',
                            FuelTransaction::TYPE_STORAGE_TO_TRUCK => 'To Truck',
                            FuelTransaction::TYPE_STORAGE_TO_UNIT => 'To Unit',
                            FuelTransaction::TYPE_TRUCK_TO_UNIT => 'Field Refuel',
                            default => ucfirst(str_replace('_', ' ', $state))
                        }
                    )
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        FuelTransaction::TYPE_VENDOR_TO_STORAGE => 'success',
                        FuelTransaction::TYPE_STORAGE_TO_TRUCK => 'info',
                        FuelTransaction::TYPE_STORAGE_TO_UNIT => 'warning',
                        FuelTransaction::TYPE_TRUCK_TO_UNIT => 'primary',
                        default => 'gray'
                    })
                    ->size('sm'),
                    
                Tables\Columns\TextColumn::make('source_destination')
                    ->label('From → To')
                    ->getStateUsing(function (FuelTransaction $record): string {
                        $from = $record->source_name;
                        $to = $record->destination_name;
                        return "{$from} → {$to}";
                    })
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),
                    
                Tables\Columns\TextColumn::make('fuel_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => number_format($state, 0) . ' L')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($state) => match(true) {
                        $state >= 1000 => 'success',
                        $state >= 500 => 'warning', 
                        default => 'gray'
                    }),
                    
                Tables\Columns\IconColumn::make('is_approved')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                    
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('By')
                    ->limit(15),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->since()
                    ->tooltip(fn ($state) => $state->format('M d, Y H:i:s')),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->size('sm')
                    ->url(fn (FuelTransaction $record): string => 
                        route('filament.admin.resources.fuel-transactions.edit', $record)
                    ),
                    
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('sm')
                    ->visible(fn (FuelTransaction $record): bool => 
                        !$record->is_approved && 
                        auth()->user()->hasAnyRole(['manager', 'superadmin'])
                    )
                    ->requiresConfirmation()
                    ->action(function (FuelTransaction $record): void {
                        $record->update([
                            'is_approved' => true,
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                    })
                    ->successNotificationTitle('Transaction approved'),
            ])
            ->striped()
            ->paginated(false)
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-arrow-right-circle')
            ->emptyStateHeading('No transactions yet')
            ->emptyStateDescription('Fuel transactions will appear here once they are recorded.');
    }
}