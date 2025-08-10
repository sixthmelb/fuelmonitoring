<?php

namespace App\Observers;

use App\Models\ApprovalRequest;
use App\Models\FuelTransaction;
use Illuminate\Support\Facades\Log;

/**
 * Command untuk membuat observer ini:
 * php artisan make:observer ApprovalRequestObserver --model=ApprovalRequest
 * 
 * Jangan lupa register di AppServiceProvider:
 * ApprovalRequest::observe(ApprovalRequestObserver::class);
 * 
 * Observer ini menangani:
 * - Auto apply changes ketika edit request diapprove
 * - Auto delete transaction ketika delete request diapprove
 * - Logging approval activities
 */
class ApprovalRequestObserver
{
    /**
     * Handle the ApprovalRequest "updating" event.
     * Ketika approval request status berubah ke approved
     */
    public function updating(ApprovalRequest $approvalRequest): void
    {
        // Check jika status berubah menjadi approved
        if ($approvalRequest->isDirty('status') && 
            $approvalRequest->status === ApprovalRequest::STATUS_APPROVED &&
            $approvalRequest->getOriginal('status') === ApprovalRequest::STATUS_PENDING) {
            
            $this->handleApprovedRequest($approvalRequest);
        }
    }

    /**
     * Handle approved request berdasarkan type
     */
    private function handleApprovedRequest(ApprovalRequest $approvalRequest): void
    {
        try {
            switch ($approvalRequest->request_type) {
                case ApprovalRequest::TYPE_EDIT:
                    $this->handleApprovedEditRequest($approvalRequest);
                    break;
                    
                case ApprovalRequest::TYPE_DELETE:
                    $this->handleApprovedDeleteRequest($approvalRequest);
                    break;
            }
            
            Log::info('Approval request processed successfully', [
                'approval_request_id' => $approvalRequest->id,
                'request_type' => $approvalRequest->request_type,
                'fuel_transaction_id' => $approvalRequest->fuel_transaction_id,
                'approved_by' => $approvalRequest->approved_by
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error processing approved request', [
                'approval_request_id' => $approvalRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle approved edit request
     * Apply the changes yang diminta ke fuel transaction
     */
    private function handleApprovedEditRequest(ApprovalRequest $approvalRequest): void
    {
        if (!$approvalRequest->new_data || !$approvalRequest->fuelTransaction) {
            Log::warning('Cannot apply edit - missing data', [
                'approval_request_id' => $approvalRequest->id,
                'has_new_data' => !empty($approvalRequest->new_data),
                'has_transaction' => !empty($approvalRequest->fuelTransaction)
            ]);
            return;
        }

        $transaction = $approvalRequest->fuelTransaction;
        $newData = $approvalRequest->new_data;
        
        // Backup original data for logging
        $originalData = $transaction->toArray();
        
        // Update transaction dengan new data
        // Hati-hati dengan fields yang tidak boleh diubah
        $allowedFields = [
            'transaction_type',
            'source_storage_id',
            'source_truck_id', 
            'destination_storage_id',
            'destination_truck_id',
            'unit_id',
            'fuel_amount',
            'unit_km',
            'unit_hm',
            'transaction_date',
            'notes'
        ];
        
        $filteredData = array_intersect_key($newData, array_flip($allowedFields));
        
        // Update transaction
        $transaction->update($filteredData);
        
        Log::info('Edit request applied to transaction', [
            'approval_request_id' => $approvalRequest->id,
            'transaction_code' => $transaction->transaction_code,
            'changes_applied' => $filteredData,
            'original_data' => $originalData
        ]);
    }

    /**
     * Handle approved delete request
     * Delete the fuel transaction
     */
    private function handleApprovedDeleteRequest(ApprovalRequest $approvalRequest): void
    {
        if (!$approvalRequest->fuelTransaction) {
            Log::warning('Cannot delete - transaction not found', [
                'approval_request_id' => $approvalRequest->id,
                'fuel_transaction_id' => $approvalRequest->fuel_transaction_id
            ]);
            return;
        }

        $transaction = $approvalRequest->fuelTransaction;
        $transactionCode = $transaction->transaction_code;
        
        // Delete the transaction (soft delete)
        $transaction->delete();
        
        Log::info('Delete request applied - transaction deleted', [
            'approval_request_id' => $approvalRequest->id,
            'transaction_code' => $transactionCode,
            'fuel_transaction_id' => $approvalRequest->fuel_transaction_id
        ]);
    }

    /**
     * Handle the ApprovalRequest "updated" event.
     */
    public function updated(ApprovalRequest $approvalRequest): void
    {
        // Log status changes
        if ($approvalRequest->wasChanged('status')) {
            $oldStatus = $approvalRequest->getOriginal('status');
            $newStatus = $approvalRequest->status;
            
            Log::info('Approval request status changed', [
                'approval_request_id' => $approvalRequest->id,
                'fuel_transaction_code' => $approvalRequest->fuelTransaction?->transaction_code,
                'request_type' => $approvalRequest->request_type,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'approved_by' => $approvalRequest->approved_by,
                'requested_by' => $approvalRequest->requestedBy?->name
            ]);
        }
    }

    /**
     * Handle the ApprovalRequest "created" event.
     */
    public function created(ApprovalRequest $approvalRequest): void
    {
        Log::info('New approval request created', [
            'approval_request_id' => $approvalRequest->id,
            'fuel_transaction_code' => $approvalRequest->fuelTransaction?->transaction_code,
            'request_type' => $approvalRequest->request_type,
            'requested_by' => $approvalRequest->requestedBy?->name,
            'reason' => $approvalRequest->reason
        ]);
    }
}