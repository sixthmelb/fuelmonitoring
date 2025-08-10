<?php

namespace App\Filament\Resources\FuelTransactionResource\Pages;

use App\Filament\Resources\FuelTransactionResource;
use App\Models\FuelTransaction;
use App\Models\ApprovalRequest;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * File: app/Filament/Resources/FuelTransactionResource/Pages/EditFuelTransaction.php
 * 
 * PERBAIKAN: Mark approved edit permission as used after edit
 */
class EditFuelTransaction extends EditRecord
{
    protected static string $resource = FuelTransactionResource::class;

    /**
     * Mount method untuk cek authorization yang tepat
     */
    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        
        // PERBAIKAN: Gunakan method canBeEdited() yang sudah diperbaiki
        if (!$this->record->canBeEdited()) {
            // Redirect ke index dengan error message yang lebih informatif
            $editStatus = $this->record->getEditStatusForCurrentUser();
            
            $this->redirect(static::getResource()::getUrl('index'));
            
            // Show notification berdasarkan status
            $message = match($editStatus['status']) {
                'pending_approval' => 'This transaction has a pending approval request.',
                'can_request' => 'You need to submit an edit request for approval first.',
                'no_access' => 'You do not have permission to edit this transaction.',
                default => $editStatus['message']
            };
            
            \Filament\Notifications\Notification::make()
                ->title('Edit Access Denied')
                ->body($message)
                ->danger()
                ->send();
            
            return;
        }
        
        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => 
                    auth()->user()->hasAnyRole(['superadmin', 'manager']) &&
                    !$this->record->approvalRequests()->where('status', ApprovalRequest::STATUS_PENDING)->exists()
                ),
                
            Actions\Action::make('view_approval_requests')
                ->label('View Approval Requests')
                ->icon('heroicon-o-document-check')
                ->color('info')
                ->visible(fn (): bool => 
                    $this->record->approvalRequests()->exists()
                )
                ->url(fn (): string => 
                    \App\Filament\Resources\ApprovalRequestResource::getUrl('index', [
                        'tableFilters[fuel_transaction_id][value]' => $this->record->id
                    ])
                ),
                
            // Status indicator untuk menunjukkan mengapa user bisa edit
            Actions\Action::make('edit_permission_info')
                ->label(function (): string {
                    $editStatus = $this->record->getEditStatusForCurrentUser();
                    return match($editStatus['status']) {
                        'approved_edit' => 'Edit Approved',
                        'new_transaction' => 'New Transaction',
                        'manager_access' => 'Manager Access',
                        default => 'Edit Permission'
                    };
                })
                ->icon(function (): string {
                    $editStatus = $this->record->getEditStatusForCurrentUser();
                    return match($editStatus['status']) {
                        'approved_edit' => 'heroicon-o-check-circle',
                        'new_transaction' => 'heroicon-o-clock',
                        'manager_access' => 'heroicon-o-key',
                        default => 'heroicon-o-information-circle'
                    };
                })
                ->color(function (): string {
                    $editStatus = $this->record->getEditStatusForCurrentUser();
                    return match($editStatus['status']) {
                        'approved_edit' => 'success',
                        'new_transaction' => 'warning',
                        'manager_access' => 'primary',
                        default => 'info'
                    };
                })
                ->disabled()
                ->tooltip(function (): string {
                    $editStatus = $this->record->getEditStatusForCurrentUser();
                    return $editStatus['message'];
                }),
        ];
    }

    /**
     * Mutate form data before save
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Log the edit action untuk audit trail
        \Log::info('Fuel transaction edited', [
            'transaction_id' => $this->record->id,
            'transaction_code' => $this->record->transaction_code,
            'edited_by' => auth()->id(),
            'edit_type' => $this->getEditType(),
            'old_data' => $this->record->toArray(),
            'new_data' => $data
        ]);
        
        return $data;
    }

    /**
     * Determine edit type untuk logging
     */
    private function getEditType(): string
    {
        $editStatus = $this->record->getEditStatusForCurrentUser();
        
        return match($editStatus['status']) {
            'approved_edit' => 'approved_edit_request',
            'new_transaction' => 'new_transaction_edit',
            'manager_access' => 'manager_direct_edit',
            default => 'unknown_edit'
        };
    }

    /**
     * After save actions
     */
    protected function afterSave(): void
    {
        $editType = $this->getEditType();
        
        // PENTING: Jika ini adalah edit dari approved request, 
        // kita perlu menandai bahwa edit request sudah digunakan
        if ($editType === 'approved_edit_request') {
            $this->markApprovedEditRequestAsUsed();
        }
        
        // Different notification based on edit type
        $message = match($editType) {
            'approved_edit_request' => 'Transaction updated using approved edit request. Edit permission has been consumed.',
            'new_transaction_edit' => 'New transaction updated successfully.',
            'manager_direct_edit' => 'Transaction updated by manager.',
            default => 'Transaction updated successfully.'
        };
        
        \Filament\Notifications\Notification::make()
            ->title('Transaction Updated')
            ->body($message)
            ->success()
            ->send();
    }

    /**
     * Mark approved edit request as used
     * Supaya tidak bisa digunakan lagi untuk edit
     */
    private function markApprovedEditRequestAsUsed(): void
    {
        // Cari approved edit request untuk user ini dan transaksi ini yang belum digunakan
        $approvedEditRequest = $this->record->approvalRequests()
            ->where('request_type', ApprovalRequest::TYPE_EDIT)
            ->where('requested_by', auth()->id())
            ->where('status', ApprovalRequest::STATUS_APPROVED)
            ->whereNull('used_at') // PENTING: yang belum digunakan
            ->first();
            
        if ($approvedEditRequest) {
            // Update approval request dengan timestamp used
            $approvedEditRequest->update([
                'used_at' => now(),
            ]);
            
            \Log::info('Approved edit request marked as used', [
                'approval_request_id' => $approvedEditRequest->id,
                'transaction_id' => $this->record->id,
                'used_by' => auth()->id(),
                'used_at' => now()
            ]);
            
            // Refresh record untuk memastikan perubahan terdeteksi
            $this->record->refresh();
            
            \Filament\Notifications\Notification::make()
                ->title('Edit Permission Consumed')
                ->body('Your approved edit permission has been used. Submit a new request to edit again.')
                ->warning()
                ->send();
        }
    }

    /**
     * Get redirect URL after save
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Override authorization check
     */
    protected function authorizeAccess(): void
    {
        // Custom authorization menggunakan method canBeEdited()
        if (!$this->record->canBeEdited()) {
            $this->halt();
        }
    }
}