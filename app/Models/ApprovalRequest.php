<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\ApprovalRequestObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

/**
 * Command untuk membuat model ini:
 * php artisan make:model ApprovalRequest -m
 * 
 * PERBAIKAN: Tambah observer untuk handle approve workflow
 * Model untuk sistem approval edit/delete data
 */
#[ObservedBy([ApprovalRequestObserver::class])]
class ApprovalRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fuel_transaction_id',
        'request_type',
        'requested_by',
        'approved_by',
        'approved_at',
        'used_at', // BARU: Track kapan edit permission digunakan
        'status',
        'reason',
        'original_data',
        'new_data',
        'rejection_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'used_at' => 'datetime', // BARU
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
     * PERBAIKAN: Observer akan handle apply changes, jadi kita hanya update status
     */
    public function approve(User $approver): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        // Update status - observer akan handle apply changes
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

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
     * BARU: Check if this approval request allows staff to edit
     * Staff bisa edit langsung jika ada approved edit request untuk transaksi ini
     */
    public function allowsDirectEdit(): bool
    {
        return $this->request_type === self::TYPE_EDIT && 
               $this->status === self::STATUS_APPROVED;
    }

    /**
     * BARU: Check if fuel transaction is editable by specific user after this approval
     */
    public function allowsEditBy(User $user): bool
    {
        if (!$this->allowsDirectEdit()) {
            return false;
        }
        
        // Hanya user yang request yang bisa edit
        return $this->requested_by === $user->id;
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

    /**
     * BARU: Scope for approved edit requests by user
     */
    public function scopeApprovedEditByUser($query, $userId)
    {
        return $query->where('request_type', self::TYPE_EDIT)
                    ->where('status', self::STATUS_APPROVED)
                    ->where('requested_by', $userId);
    }
}