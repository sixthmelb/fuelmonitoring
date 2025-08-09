<?php

namespace App\Filament\Widgets;

use App\Models\ApprovalRequest;
use App\Models\FuelTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Forms\Components\Textarea;

/**
 * Command untuk membuat widget ini:
 * php artisan make:filament-widget PendingApprovals --table
 * 
 * Widget untuk menampilkan approval requests yang pending
 */
class PendingApprovals extends BaseWidget
{
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';
    
    // Refresh setiap 30 detik
    protected static ?string $pollingInterval = '30s';
    
    protected static ?string $heading = 'Pending Approval Requests';

    // Hanya tampilkan jika user adalah manager atau superadmin
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['manager', 'superadmin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ApprovalRequest::query()
                    ->with(['fuelTransaction', 'requestedBy'])
                    ->where('status', ApprovalRequest::STATUS_PENDING)
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('fuelTransaction.transaction_code')
                    ->label('Transaction')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('request_type')
                    ->label('Request Type')
                    ->formatStateUsing(fn (string $state): string => 
                        ApprovalRequest::getRequestTypes()[$state] ?? ucfirst($state)
                    )
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        ApprovalRequest::TYPE_EDIT => 'warning',
                        ApprovalRequest::TYPE_DELETE => 'danger',
                        default => 'gray'
                    })
                    ->icon(fn (string $state): string => match($state) {
                        ApprovalRequest::TYPE_EDIT => 'heroicon-o-pencil-square',
                        ApprovalRequest::TYPE_DELETE => 'heroicon-o-trash',
                        default => 'heroicon-o-question-mark-circle'
                    }),
                    
                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 40 ? $state : null;
                    }),
                    
                Tables\Columns\TextColumn::make('fuelTransaction.fuel_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 0) . ' L' : 'N/A')
                    ->alignEnd(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->since()
                    ->tooltip(fn ($state) => $state->format('M d, Y H:i:s')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve Request')
                        ->modalDescription(fn (ApprovalRequest $record): string => 
                            "Are you sure you want to approve this {$record->request_type} request?"
                        )
                        ->action(function (ApprovalRequest $record): void {
                            $record->approve(auth()->user());
                        })
                        ->successNotificationTitle('Request approved')
                        ->after(fn () => $this->dispatch('$refresh')),
                        
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Textarea::make('rejection_reason')
                                ->label('Rejection Reason')
                                ->required()
                                ->placeholder('Please provide a reason for rejecting this request')
                                ->rows(3),
                        ])
                        ->action(function (ApprovalRequest $record, array $data): void {
                            $record->reject(auth()->user(), $data['rejection_reason']);
                        })
                        ->successNotificationTitle('Request rejected')
                        ->after(fn () => $this->dispatch('$refresh')),
                        
                    Tables\Actions\Action::make('view_details')
                        ->label('View Details')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (ApprovalRequest $record): string => 
                            \App\Filament\Resources\ApprovalRequestResource::getUrl('view', $record)
                        )
                        ->openUrlInNewTab(),
                ])->button()
                ->size('sm'),
            ])
            ->striped()
            ->paginated(false)
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('No pending approvals')
            ->emptyStateDescription('All approval requests have been processed.');
    }
}