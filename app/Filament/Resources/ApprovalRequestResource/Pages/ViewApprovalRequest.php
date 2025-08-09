<?php

namespace App\Filament\Resources\ApprovalRequestResource\Pages;

use App\Filament\Resources\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;

/**
 * Command untuk membuat page ini:
 * php artisan make:filament-page ViewApprovalRequest --resource=ApprovalRequestResource --type=ViewRecord
 * 
 * Page untuk view detail approval request dengan actions approve/reject
 */
class ViewApprovalRequest extends ViewRecord
{
    protected static string $resource = ApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve Request')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => 
                    $this->record->status === ApprovalRequest::STATUS_PENDING &&
                    auth()->user()->hasAnyRole(['manager', 'superadmin'])
                )
                ->requiresConfirmation()
                ->modalHeading('Approve Request')
                ->modalDescription(fn (): string => 
                    "Are you sure you want to approve this {$this->record->request_type} request for transaction {$this->record->fuelTransaction->transaction_code}?"
                )
                ->modalSubmitActionLabel('Yes, Approve')
                ->action(function (): void {
                    $this->record->approve(auth()->user());
                    
                    $this->refreshFormData(['record']);
                })
                ->successNotificationTitle('Request approved successfully')
                ->after(fn () => $this->redirect(static::getResource()::getUrl('index'))),
                
            Actions\Action::make('reject')
                ->label('Reject Request')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => 
                    $this->record->status === ApprovalRequest::STATUS_PENDING &&
                    auth()->user()->hasAnyRole(['manager', 'superadmin'])
                )
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->placeholder('Please provide a detailed reason for rejecting this request')
                        ->rows(4)
                        ->helperText('This reason will be visible to the requester.'),
                ])
                ->modalHeading('Reject Request')
                ->modalDescription('Please provide a reason for rejecting this request.')
                ->modalSubmitActionLabel('Reject Request')
                ->action(function (array $data): void {
                    $this->record->reject(auth()->user(), $data['rejection_reason']);
                    
                    $this->refreshFormData(['record']);
                })
                ->successNotificationTitle('Request rejected')
                ->after(fn () => $this->redirect(static::getResource()::getUrl('index'))),
                
            Actions\Action::make('cancel')
                ->label('Cancel Request')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn (): bool => 
                    $this->record->status === ApprovalRequest::STATUS_PENDING &&
                    $this->record->canBeCancelled() &&
                    auth()->id() === $this->record->requested_by
                )
                ->requiresConfirmation()
                ->modalHeading('Cancel Request')
                ->modalDescription('Are you sure you want to cancel this approval request?')
                ->action(function (): void {
                    $this->record->cancel();
                    
                    $this->refreshFormData(['record']);
                })
                ->successNotificationTitle('Request cancelled')
                ->after(fn () => $this->redirect(static::getResource()::getUrl('index'))),
                
            Actions\Action::make('view_transaction')
                ->label('View Original Transaction')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('info')
                //->url(fn (): string => 
                //    \App\Filament\Resources\FuelTransactionResource::getUrl('edit', $this->record->fuelTransaction)
                //)
                ->openUrlInNewTab(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Bisa ditambahkan widget khusus untuk approval detail jika diperlukan
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Bisa ditambahkan widget untuk history approval jika diperlukan
        ];
    }
}