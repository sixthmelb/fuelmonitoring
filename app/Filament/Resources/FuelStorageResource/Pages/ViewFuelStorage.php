<?php

namespace App\Filament\Resources\FuelStorageResource\Pages;

use App\Filament\Resources\FuelStorageResource;
use App\Models\FuelTransaction;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

/**
 * Command untuk membuat page ini:
 * php artisan make:filament-page ViewFuelStorage --resource=FuelStorageResource --type=ViewRecord
 * 
 * Atau tambahkan manual ke getPages() di Resource
 */
class ViewFuelStorage extends ViewRecord
{
    protected static string $resource = FuelStorageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square'),
                
            Actions\Action::make('refill')
                ->label('Refill Storage')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->is_active)
                ->form([
                    Infolists\Components\TextInput::make('refill_amount')
                        ->label('Refill Amount (Liters)')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(fn (): float => $this->record->available_capacity)
                        ->helperText(fn (): string => 
                            'Available space: ' . number_format($this->record->available_capacity, 0) . ' L'
                        ),
                    Infolists\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->placeholder('Refill reason or vendor information'),
                ])
                ->action(function (array $data): void {
                    FuelTransaction::create([
                        'transaction_type' => FuelTransaction::TYPE_VENDOR_TO_STORAGE,
                        'destination_storage_id' => $this->record->id,
                        'fuel_amount' => $data['refill_amount'],
                        'transaction_date' => now(),
                        'notes' => $data['notes'] ?? 'Manual refill via admin panel',
                        'created_by' => auth()->id(),
                        'is_approved' => true,
                    ]);
                })
                ->successNotificationTitle('Storage refilled successfully')
                ->after(fn () => $this->refreshFormData(['record'])),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Storage Overview')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->badge()
                                    ->copyable()
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('name')
                                    ->weight('bold')
                                    ->color(fn ($state) => match(true) {
                                        $state >= 80 => 'success',
                                        $state >= 50 => 'warning',
                                        $state >= 20 => 'danger',
                                        default => 'gray'
                                    }),
                                    
                                Infolists\Components\TextEntry::make('threshold_status')
                                    ->label('Threshold Status')
                                    ->getStateUsing(function ($record): string {
                                        if ($record->isBelowThreshold()) {
                                            return 'Below Threshold ⚠️';
                                        }
                                        return 'Above Threshold ✅';
                                    })
                                    ->badge()
                                    ->color(function ($record): string {
                                        return $record->isBelowThreshold() ? 'danger' : 'success';
                                    }),
                            ]),
                    ])
                    ->icon('heroicon-o-chart-bar'),
                    
                Infolists\Components\Section::make('Recent Transactions')
                    ->schema([
                        Infolists\Components\ViewEntry::make('recent_transactions')
                            ->label('')
                            ->view('filament.infolists.recent-transactions')
                            ->columnSpanFull(),
                    ])
                    ->icon('heroicon-o-clock')
                    ->collapsible(),
                    
                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_incoming')
                                    ->label('Total Incoming (This Month)')
                                    ->getStateUsing(function ($record): string {
                                        $total = $record->incomingTransactions()
                                            ->whereMonth('created_at', now()->month)
                                            ->sum('fuel_amount');
                                        return number_format($total, 0) . ' L';
                                    })
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-arrow-down-circle'),
                                    
                                Infolists\Components\TextEntry::make('total_outgoing')
                                    ->label('Total Outgoing (This Month)')
                                    ->getStateUsing(function ($record): string {
                                        $total = $record->outgoingTransactions()
                                            ->whereMonth('created_at', now()->month)
                                            ->sum('fuel_amount');
                                        return number_format($total, 0) . ' L';
                                    })
                                    ->badge()
                                    ->color('danger')
                                    ->icon('heroicon-o-arrow-up-circle'),
                                    
                                Infolists\Components\TextEntry::make('transaction_count')
                                    ->label('Total Transactions')
                                    ->getStateUsing(function ($record): string {
                                        $incoming = $record->incomingTransactions()->count();
                                        $outgoing = $record->outgoingTransactions()->count();
                                        return number_format($incoming + $outgoing);
                                    })
                                    ->badge()
                                    ->color('info')
                                    ->icon('heroicon-o-list-bullet'),
                            ]),
                    ])
                    ->icon('heroicon-o-chart-pie')
                    ->collapsible(),
                    
                Infolists\Components\Section::make('System Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime('F j, Y \a\t g:i A')
                                    ->icon('heroicon-o-calendar-days'),
                                    
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('F j, Y \a\t g:i A')
                                    ->icon('heroicon-o-clock'),
                            ]),
                    ])
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}