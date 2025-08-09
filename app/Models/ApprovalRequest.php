<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Command untuk membuat model ini:
 * php artisan make:model ApprovalRequest -m
 * 
 * Model untuk sistem approval edit/delete data
 */
class ApprovalRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fuel_transaction_id',
        'request_type',
        'requested_by',
        'approved_by',
        'approved_at',
        'status',
        'reason',
        'original_data',
        'new_data',
        'rejection_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'original_data' => 'array',
        'new_data' => 'array',
    ];

    // Request types
    const TYPE_EDIT = 'edit';
    const TYPE_DELETE = 'delete';

    // Status
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get all request types
     */
    public static function getRequestTypes(): array
    {
        return [
            self::TYPE_EDIT => 'Edit Data',
            self::TYPE_DELETE => 'Delete Data',
        ];
    }

    /**
     * Get all statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    /**
     * Relationships
     */

    /**
     * Fuel transaction relationship
     */
    public function fuelTransaction()
    {
        return $this->belongsTo(FuelTransaction::class);
    }

    /**
     * Requested by user relationship
     */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Approved by user relationship
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Business Logic Methods
     */

    /**
     * Generate approval code
     */
    public static function generateApprovalCode(): string
    {
        $date = now()->format('Ymd');
        $lastRequest = self::whereDate('created_at', now())
                          ->orderBy('id', 'desc')
                          ->first();
        
        $sequence = $lastRequest ? 
            intval(substr($lastRequest->approval_code ?? '', -4)) + 1 : 1;
        
        return 'APR' . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Approve the request
     */
    public function approve(User $approver): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        // Apply changes to fuel transaction if it's an edit request
        if ($this->request_type === self::TYPE_EDIT && $this->new_data) {
            $this->fuelTransaction->update($this->new_data);
        }

        // Delete fuel transaction if it's a delete request
        if ($this->request_type === self::TYPE_DELETE) {
            $this->fuelTransaction->delete();
        }

        return true;
    }

    /**
     * Reject the request
     */
    public function reject(User $approver, string $reason = ''): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return true;
    }

    /**
     * Get changes summary for display
     */
    public function getChangesSummaryAttribute(): array
    {
        if ($this->request_type === self::TYPE_DELETE) {
            return ['action' => 'Delete transaction'];
        }

        if (!$this->original_data || !$this->new_data) {
            return [];
        }

        $changes = [];
        foreach ($this->new_data as $field => $newValue) {
            $originalValue = $this->original_data[$field] ?? null;
            if ($originalValue != $newValue) {
                $changes[] = [
                    'field' => $field,
                    'from' => $originalValue,
                    'to' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if request can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               $this->created_at->diffInHours(now()) <= 24;
    }

    /**
     * Cancel pending request
     */
    public function cancel(): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => 'Cancelled by requester',
        ]);

        return true;
    }

    /**
     * Scopes
     */

    /**
     * Scope for pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for rejected requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope by request type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('request_type', $type);
    }

    /**
     * Scope by requester
     */
    public function scopeByRequester($query, $userId)
    {
        return $query->where('requested_by', $userId);
    }

    /**
     * Scope for requests awaiting specific approver
     */
    public function scopeAwaitingApprover($query, $approverId)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->whereNull('approved_by');
    }
}