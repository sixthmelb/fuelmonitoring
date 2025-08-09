<?php

namespace App\Filament\Resources\FuelTransactionResource\Pages;

use App\Filament\Resources\FuelTransactionResource;
use App\Models\FuelTransaction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * File: app/Filament/Resources/FuelTransactionResource/Pages/EditFuelTransaction.php
 * 
 * Command untuk update file ini:
 * php artisan make:filament-page EditFuelTransaction --resource=FuelTransactionResource --type=EditRecord
 * 
 * PERBAIKAN:
 * 1. Tambah authorization check
 * 2. Redirect staff yang tidak punya akses
 * 3. Block edit jika ada pending approval
 */
class EditFuelTransaction extends EditRecord
{
    protected static string $resource = FuelTransactionResource::class;

    /**
     * Mount method untuk cek authorization
     */
    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        
        // Cek authorization
        if (!$this->canEditTransaction()) {
            // Redirect ke index dengan error message
            $this->redirect(static::getResource()::getUrl('index'));
            
            // Show notification
            \Filament\Notifications\Notification::make()
                ->title('Access Denied')
                ->body('You do not have permission to edit this transaction. Please submit an edit request instead.')
                ->danger()
                ->send();
            
            return;
        }
        
        parent::mount($record);
    }

    /**
     * Check if current user can edit this transaction
     */
    private function canEditTransaction(): bool
    {
        // Staff tidak bisa edit langsung
        if (auth()->user()->hasRole('staff')) {
            return false;
        }
        
        // Manager/Superadmin harus cek kondisi lain
        if (!auth()->user()->hasAnyRole(['manager', 'superadmin'])) {
            return false;
        }
        
        // Transaksi harus bisa diedit
        if (!$this->record->canBeEdited()) {
            return false;
        }
        
        // Tidak boleh ada pending approval request
        if ($this->record->approvalRequests()->where('status', \App\Models\ApprovalRequest::STATUS_PENDING)->exists()) {
            return false;
        }
        
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => 
                    auth()->user()->hasAnyRole(['superadmin', 'manager']) &&
                    !$this->record->approvalRequests()->where('status', \App\Models\ApprovalRequest::STATUS_PENDING)->exists()
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
        ];
    }

    /**
     * Mutate form data before save
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Log the edit action
        \Log::info('Fuel transaction edited directly', [
            'transaction_id' => $this->record->id,
            'transaction_code' => $this->record->transaction_code,
            'edited_by' => auth()->id(),
            'old_data' => $this->record->toArray(),
            'new_data' => $data
        ]);
        
        return $data;
    }

    /**
     * After save actions
     */
    protected function afterSave(): void
    {
        // Send notification about direct edit
        \Filament\Notifications\Notification::make()
            ->title('Transaction Updated Successfully')
            ->body('The fuel transaction has been updated directly.')
            ->success()
            ->send();
    }

    /**
     * Get redirect URL after save
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}