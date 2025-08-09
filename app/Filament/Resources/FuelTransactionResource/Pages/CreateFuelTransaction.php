<?php

namespace App\Filament\Resources\FuelTransactionResource\Pages;

use App\Filament\Resources\FuelTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

/**
 * Command untuk membuat page ini:
 * php artisan make:filament-page CreateFuelTransaction --resource=FuelTransactionResource --type=CreateRecord
 * 
 * Fixed untuk mengatasi error created_by field
 */
class CreateFuelTransaction extends CreateRecord
{
    protected static string $resource = FuelTransactionResource::class;

    /**
     * Mutate form data sebelum create
     * Pastikan created_by selalu diisi
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Pastikan created_by diisi dengan user yang sedang login
        $data['created_by'] = auth()->id();
        
        // Set default transaction_date jika kosong
        if (empty($data['transaction_date'])) {
            $data['transaction_date'] = now();
        }
        
        // Auto approve jika user adalah manager/superadmin
        if (auth()->user()->hasAnyRole(['manager', 'superadmin'])) {
            $data['is_approved'] = true;
            $data['approved_by'] = auth()->id();
            $data['approved_at'] = now();
        } else {
            $data['is_approved'] = false;
        }

        return $data;
    }

    /**
     * Handle setelah record dibuat
     */
    protected function afterCreate(): void
    {
        // Kirim notification jika transaction butuh approval
        if (!$this->record->is_approved) {
            $this->sendApprovalNotification();
        }
    }

    /**
     * Kirim notification ke manager untuk approval
     */
    private function sendApprovalNotification(): void
    {
        try {
            $managers = \App\Models\User::role('manager')->where('is_active', true)->get();
            
            foreach ($managers as $manager) {
                // Bisa ditambahkan notification system di sini
                // $manager->notify(new NewTransactionPendingApproval($this->record));
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send approval notification: ' . $e->getMessage());
        }
    }

    /**
     * Get redirect URL setelah create
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Custom success message
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        if ($this->record->is_approved) {
            return 'Transaction created and approved successfully';
        } else {
            return 'Transaction created successfully - pending approval';
        }
    }
}