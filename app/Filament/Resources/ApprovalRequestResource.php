<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalRequestResource\Pages;
use App\Models\ApprovalRequest;
use App\Models\FuelTransaction;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\TrashedFilter;

/**
 * Filament Resource untuk Approval Request Management
 * 
 * Features:
 * - View pending approval requests
 * - Approve/reject requests dengan reason
 * - History tracking
 * - Changes comparison
 */
class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    
    protected static ?string $navigationLabel = 'Approval Requests';
    
    protected static ?string $modelLabel = 'Approval Request';
    
    protected static ?string $pluralModelLabel = 'Approval Requests';
    
    protected static ?string $navigationGroup = 'System Management';
    
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Information')
                    ->schema([
                        Forms\Components\Select::make('fuel_transaction_id')
                            ->label('Fuel Transaction')
                            ->relationship('fuelTransaction', 'transaction_code')
                            ->required()
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Select::make('request_type')
                            ->label('Request Type')
                            ->options(ApprovalRequest::getRequestTypes())
                            ->required(),
                            
                        Forms\Components\Select::make('requested_by')
                            ->label('Requested By')
                            ->relationship('requestedBy', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3),
                    ]),
                    
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ApprovalRequest::getStatuses())
                            ->default(ApprovalRequest::STATUS_PENDING)
                            ->required(),
                            
                        Forms\Components\Select::make('approved_by')
                            ->label('Approved By')
                            ->relationship('approvedBy', 'name')
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->label('Approved At'),
                            
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fuelTransaction.transaction_code')
                    ->label('Transaction')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                    
                Tables\Columns\BadgeColumn::make('request_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => 
                        ApprovalRequest::getRequestTypes()[$state] ?? $state
                    )
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
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => 
                        ApprovalRequest::getStatuses()[$state] ?? $state
                    )
                    ->color(fn (string $state): string => match($state) {
                        ApprovalRequest::STATUS_PENDING => 'warning',
                        ApprovalRequest::STATUS_APPROVED => 'success',
                        ApprovalRequest::STATUS_REJECTED => 'danger',
                        default => 'gray'
                    }),
                    
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                    
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->placeholder('N/A')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Processed')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('Pending')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options(ApprovalRequest::getStatuses())
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('request_type')
                    ->options(ApprovalRequest::getRequestTypes())
                    ->multiple(),
                    
                Tables\Filters\SelectFilter::make('requested_by')
                    ->relationship('requestedBy', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('approved_by')
                    ->relationship('approvedBy', 'name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (ApprovalRequest $record): bool => 
                            $record->status === ApprovalRequest::STATUS_PENDING &&
                            auth()->user()->hasAnyRole(['manager', 'superadmin'])
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Approve Request')
                        ->modalDescription(fn (ApprovalRequest $record): string => 
                            "Are you sure you want to approve this {$record->request_type} request for transaction {$record->fuelTransaction->transaction_code}?"
                        )
                        ->action(function (ApprovalRequest $record): void {
                            $record->approve(auth()->user());
                        })
                        ->successNotificationTitle('Request approved successfully'),
                        
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (ApprovalRequest $record): bool => 
                            $record->status === ApprovalRequest::STATUS_PENDING &&
                            auth()->user()->hasAnyRole(['manager', 'superadmin'])
                        )
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Rejection Reason')
                                ->required()
                                ->placeholder('Please provide a reason for rejecting this request')
                                ->rows(3),
                        ])
                        ->action(function (ApprovalRequest $record, array $data): void {
                            $record->reject(auth()->user(), $data['rejection_reason']);
                        })
                        ->successNotificationTitle('Request rejected'),
                        
                    Tables\Actions\Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->visible(fn (ApprovalRequest $record): bool => 
                            $record->status === ApprovalRequest::STATUS_PENDING &&
                            $record->canBeCancelled() &&
                            auth()->id() === $record->requested_by
                        )
                        ->requiresConfirmation()
                        ->action(function (ApprovalRequest $record): void {
                            $record->cancel();
                        })
                        ->successNotificationTitle('Request cancelled'),
                ])
                ->button()
                ->outlined(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasRole('superadmin')),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasRole('superadmin')),
                    Tables\Actions\RestoreBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->hasRole('superadmin')),
                        
                    Tables\Actions\BulkAction::make('bulk_approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => auth()->user()->hasAnyRole(['manager', 'superadmin']))
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $records->each(function (ApprovalRequest $record) {
                                if ($record->status === ApprovalRequest::STATUS_PENDING) {
                                    $record->approve(auth()->user());
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request Overview')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('fuelTransaction.transaction_code')
                                    ->label('Transaction Code')
                                    ->badge()
                                    ->copyable()
                                    ->color('primary'),
                                    
                                Infolists\Components\TextEntry::make('request_type')
                                    ->label('Request Type')
                                    ->formatStateUsing(fn (string $state): string => 
                                        ApprovalRequest::getRequestTypes()[$state] ?? $state
                                    )
                                    ->badge()
                                    ->color(fn (string $state): string => match($state) {
                                        ApprovalRequest::TYPE_EDIT => 'warning',
                                        ApprovalRequest::TYPE_DELETE => 'danger',
                                        default => 'gray'
                                    }),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn (string $state): string => 
                                        ApprovalRequest::getStatuses()[$state] ?? $state
                                    )
                                    ->badge()
                                    ->color(fn (string $state): string => match($state) {
                                        ApprovalRequest::STATUS_PENDING => 'warning',
                                        ApprovalRequest::STATUS_APPROVED => 'success',
                                        ApprovalRequest::STATUS_REJECTED => 'danger',
                                        default => 'gray'
                                    }),
                            ]),
                            
                        Infolists\Components\TextEntry::make('reason')
                            ->label('Request Reason')
                            ->columnSpanFull(),
                    ])
                    ->icon('heroicon-o-document-check'),
                    
                Infolists\Components\Section::make('Transaction Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('fuelTransaction.fuel_amount')
                                    ->label('Fuel Amount')
                                    ->formatStateUsing(fn ($state) => number_format($state, 0) . ' L')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('fuelTransaction.transaction_date')
                                    ->label('Transaction Date')
                                    ->dateTime('M d, Y H:i'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('fuelTransaction.notes')
                            ->label('Transaction Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ])
                    ->icon('heroicon-o-arrow-right-circle')
                    ->collapsible(),
                    
                Infolists\Components\Section::make('Changes Comparison')
                    ->schema([
                        Infolists\Components\ViewEntry::make('changes_comparison')
                            ->label('')
                            ->view('filament.infolists.changes-comparison')
                            ->columnSpanFull(),
                    ])
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (ApprovalRequest $record): bool => 
                        $record->request_type === ApprovalRequest::TYPE_EDIT
                    )
                    ->collapsible(),
                    
                Infolists\Components\Section::make('People & Timeline')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('requestedBy.name')
                                    ->label('Requested By')
                                    ->icon('heroicon-o-user'),
                                    
                                Infolists\Components\TextEntry::make('approvedBy.name')
                                    ->label('Approved By')
                                    ->placeholder('Pending approval')
                                    ->icon('heroicon-o-users'),
                            ]),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Requested At')
                                    ->dateTime('F j, Y \a\t g:i A')
                                    ->icon('heroicon-o-calendar-days'),
                                    
                                Infolists\Components\TextEntry::make('approved_at')
                                    ->label('Processed At')
                                    ->dateTime('F j, Y \a\t g:i A')
                                    ->placeholder('Pending')
                                    ->icon('heroicon-o-check-circle'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->visible(fn (ApprovalRequest $record): bool => 
                                $record->status === ApprovalRequest::STATUS_REJECTED
                            )
                            ->columnSpanFull(),
                    ])
                    ->icon('heroicon-o-users')
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalRequests::route('/'),
            'view' => Pages\ViewApprovalRequest::route('/{record}'),
        ];
    }
    
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['fuelTransaction', 'requestedBy', 'approvedBy']);
    }
    
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::pending()->count();
        return $pending > 0 ? (string) $pending : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        $pending = static::getModel()::pending()->count();
        return $pending > 0 ? 'warning' : null;
    }
    
    // Hanya manager dan superadmin yang bisa akses resource ini
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['manager', 'superadmin']) ?? false;
    }
}