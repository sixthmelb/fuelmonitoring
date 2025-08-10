<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\FuelTransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

/**
 * Command untuk membuat model ini:
 * php artisan make:model FuelTransaction -m
 * 
 * Observer akan otomatis update capacity storage/truck dan unit mileage
 * 
 * PERBAIKAN: Logic untuk allow edit setelah approval request disetujui
 */
#[ObservedBy([FuelTransactionObserver::class])]
class FuelTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_code',
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
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'is_approved',
    ];

    protected $casts = [
        'fuel_amount' => 'decimal:2',
        'unit_km' => 'decimal:2',
        'unit_hm' => 'decimal:2',
        'transaction_date' => 'datetime',
        'approved_at' => 'datetime',
        'is_approved' => 'boolean',
    ];

    // Transaction Types
    const TYPE_STORAGE_TO_UNIT = 'storage_to_unit';           // Storage langsung ke Unit
    const TYPE_STORAGE_TO_TRUCK = 'storage_to_truck';         // Storage ke Fuel Truck
    const TYPE_TRUCK_TO_UNIT = 'truck_to_unit';               // Fuel Truck ke Unit
    const TYPE_VENDOR_TO_STORAGE = 'vendor_to_storage';       // Vendor ke Storage (supply)

    /**
     * Get all transaction types
     */
    public static function getTransactionTypes(): array
    {
        return [
            self::TYPE_STORAGE_TO_UNIT => 'Storage to Unit',
            self::TYPE_STORAGE_TO_TRUCK => 'Storage to Fuel Truck',
            self::TYPE_TRUCK_TO_UNIT => 'Fuel Truck to Unit',
            self::TYPE_VENDOR_TO_STORAGE => 'Vendor to Storage',
        ];
    }

    /**
     * Generate unique transaction code
     */
    public static function generateTransactionCode(): string
    {
        $date = now()->format('Ymd');
        $lastTransaction = self::whereDate('created_at', now())
                              ->orderBy('id', 'desc')
                              ->first();
        
        $sequence = $lastTransaction ? 
            intval(substr($lastTransaction->transaction_code, -4)) + 1 : 1;
        
        return 'TRX' . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Relationships
     */

    /**
     * Source storage relationship
     */
    public function sourceStorage()
    {
        return $this->belongsTo(FuelStorage::class, 'source_storage_id');
    }

    /**
     * Destination storage relationship
     */
    public function destinationStorage()
    {
        return $this->belongsTo(FuelStorage::class, 'destination_storage_id');
    }

    /**
     * Source truck relationship
     */
    public function sourceTruck()
    {
        return $this->belongsTo(FuelTruck::class, 'source_truck_id');
    }

    /**
     * Destination truck relationship
     */
    public function destinationTruck()
    {
        return $this->belongsTo(FuelTruck::class, 'destination_truck_id');
    }

    /**
     * Unit relationship
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Created by user relationship
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Approved by user relationship
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Approval requests for this transaction
     */
    public function approvalRequests()
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    /**
     * Business Logic Methods
     */

    /**
     * Get source name (storage or truck)
     */
    public function getSourceNameAttribute(): string
    {
        if ($this->sourceStorage) {
            return $this->sourceStorage->name;
        }
        
        if ($this->sourceTruck) {
            return $this->sourceTruck->name;
        }

        return 'Vendor';
    }

    /**
     * Get destination name (storage, truck, or unit)
     */
    public function getDestinationNameAttribute(): string
    {
        if ($this->destinationStorage) {
            return $this->destinationStorage->name;
        }
        
        if ($this->destinationTruck) {
            return $this->destinationTruck->name;
        }

        if ($this->unit) {
            return $this->unit->name . ' (' . $this->unit->code . ')';
        }

        return 'Unknown';
    }

    /**
     * Calculate fuel consumption based on previous transaction
     */
    public function getFuelConsumptionAttribute(): ?array
    {
        if (!$this->unit) return null;

        $previousTransaction = self::where('unit_id', $this->unit_id)
                                  ->where('id', '<', $this->id)
                                  ->orderBy('id', 'desc')
                                  ->first();

        if (!$previousTransaction) return null;

        $result = [];

        // Calculate consumption per KM
        if ($this->unit_km && $previousTransaction->unit_km) {
            $kmDiff = $this->unit_km - $previousTransaction->unit_km;
            if ($kmDiff > 0) {
                $result['per_km'] = round($this->fuel_amount / $kmDiff, 2);
                $result['km_diff'] = $kmDiff;
            }
        }

        // Calculate consumption per HM
        if ($this->unit_hm && $previousTransaction->unit_hm) {
            $hmDiff = $this->unit_hm - $previousTransaction->unit_hm;
            if ($hmDiff > 0) {
                $result['per_hm'] = round($this->fuel_amount / $hmDiff, 2);
                $result['hm_diff'] = $hmDiff;
            }
        }

        return !empty($result) ? $result : null;
    }

    /**
     * PERBAIKAN: Check if transaction can be edited
     * Logic baru untuk mengakomodasi approval workflow yang benar
     */
    public function canBeEdited(): bool
    {
        $currentUser = auth()->user();
        
        // Check apakah ada pending approval request
        $hasPendingRequest = $this->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->exists();
            
        if ($hasPendingRequest) {
            return false; // Tidak bisa edit jika masih ada pending request
        }

        // UNTUK MANAGER/SUPERADMIN:
        if ($currentUser->hasAnyRole(['manager', 'superadmin'])) {
            // Manager/superadmin bisa edit transaksi kapan saja (asalkan tidak ada pending request)
            return true;
        }
        
        // UNTUK STAFF:
        if ($currentUser->hasRole('staff')) {
            // Staff hanya bisa edit jika:
            // 1. Transaksi belum approved DAN dia yang membuat DAN dalam 24 jam, ATAU
            // 2. Ada approved edit request untuk transaksi ini oleh staff yang sama DAN belum digunakan
            
            // Case 1: Transaksi baru yang belum approved
            if (!$this->is_approved && 
                $this->created_by === $currentUser->id && 
                $this->created_at->diffInHours(now()) <= 24) {
                return true;
            }
            
            // Case 2: Ada approved edit request dari staff ini yang belum digunakan
            $hasUnusedApprovedEditRequest = $this->approvalRequests()
                ->where('request_type', ApprovalRequest::TYPE_EDIT)
                ->where('requested_by', $currentUser->id)
                ->where('status', ApprovalRequest::STATUS_APPROVED)
                ->whereNull('used_at') // PERBAIKAN: Check belum digunakan
                ->exists();
                
            if ($hasUnusedApprovedEditRequest) {
                return true;
            }
            
            return false;
        }
        
        return false;
    }

    /**
     * PERBAIKAN: Check if user can request edit for this transaction
     */
    public function canRequestEdit(): bool
    {
        $currentUser = auth()->user();
        
        // Hanya staff yang bisa request edit
        if (!$currentUser->hasRole('staff')) {
            return false;
        }
        
        // Transaksi harus sudah approved
        if (!$this->is_approved) {
            return false;
        }
        
        // Tidak boleh ada pending approval request
        $hasPendingRequest = $this->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->exists();
            
        if ($hasPendingRequest) {
            return false;
        }
        
        // Staff tidak bisa request edit jika sudah ada approved edit request yang belum digunakan
        $hasUnusedApprovedEditRequest = $this->approvalRequests()
            ->where('request_type', ApprovalRequest::TYPE_EDIT)
            ->where('requested_by', $currentUser->id)
            ->where('status', ApprovalRequest::STATUS_APPROVED)
            ->whereNull('used_at') // PERBAIKAN: Check belum digunakan
            ->exists();
            
        if ($hasUnusedApprovedEditRequest) {
            return false; // Sudah bisa edit langsung, tidak perlu request lagi
        }
        
        return true;
    }

    /**
     * PERBAIKAN: Check if user can request delete for this transaction
     */
    public function canRequestDelete(): bool
    {
        $currentUser = auth()->user();
        
        // Hanya staff yang bisa request delete
        if (!$currentUser->hasRole('staff')) {
            return false;
        }
        
        // Transaksi harus sudah approved
        if (!$this->is_approved) {
            return false;
        }
        
        // Tidak boleh ada pending approval request
        $hasPendingRequest = $this->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->exists();
            
        if ($hasPendingRequest) {
            return false;
        }
        
        // Staff tidak bisa request delete jika sudah ada approved delete request
        $hasApprovedDeleteRequest = $this->approvalRequests()
            ->where('request_type', ApprovalRequest::TYPE_DELETE)
            ->where('requested_by', $currentUser->id)
            ->where('status', ApprovalRequest::STATUS_APPROVED)
            ->exists();
            
        if ($hasApprovedDeleteRequest) {
            return false; // Request delete sudah approved, seharusnya transaksi sudah di-delete
        }
        
        return true;
    }

    /**
     * BARU: Get edit permission status untuk current user
     * Memberikan informasi lebih detail tentang status edit permission
     */
    public function getEditStatusForCurrentUser(): array
    {
        $currentUser = auth()->user();
        
        if (!$currentUser) {
            return [
                'can_edit' => false,
                'can_request_edit' => false,
                'status' => 'not_authenticated',
                'message' => 'User not authenticated'
            ];
        }
        
        $hasPendingRequest = $this->approvalRequests()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->exists();
            
        if ($hasPendingRequest) {
            return [
                'can_edit' => false,
                'can_request_edit' => false,
                'status' => 'pending_approval',
                'message' => 'Transaction has pending approval request'
            ];
        }
        
        if ($currentUser->hasAnyRole(['manager', 'superadmin'])) {
            return [
                'can_edit' => true,
                'can_request_edit' => false,
                'status' => 'manager_access',
                'message' => 'Manager can edit directly'
            ];
        }
        
        if ($currentUser->hasRole('staff')) {
            // Check for unused approved edit request
            $hasUnusedApprovedEditRequest = $this->approvalRequests()
                ->where('request_type', ApprovalRequest::TYPE_EDIT)
                ->where('requested_by', $currentUser->id)
                ->where('status', ApprovalRequest::STATUS_APPROVED)
                ->whereNull('used_at') // PERBAIKAN: Check belum digunakan
                ->exists();
                
            if ($hasUnusedApprovedEditRequest) {
                return [
                    'can_edit' => true,
                    'can_request_edit' => false,
                    'status' => 'approved_edit',
                    'message' => 'Edit request has been approved and ready to use'
                ];
            }
            
            // Check for used approved edit request
            $hasUsedApprovedEditRequest = $this->approvalRequests()
                ->where('request_type', ApprovalRequest::TYPE_EDIT)
                ->where('requested_by', $currentUser->id)
                ->where('status', ApprovalRequest::STATUS_APPROVED)
                ->whereNotNull('used_at')
                ->exists();
                
            if ($hasUsedApprovedEditRequest) {
                return [
                    'can_edit' => false,
                    'can_request_edit' => true,
                    'status' => 'edit_used',
                    'message' => 'Previous edit permission has been used. Can request new edit approval.'
                ];
            }
            
            // Check if new transaction (not approved yet)
            if (!$this->is_approved && 
                $this->created_by === $currentUser->id && 
                $this->created_at->diffInHours(now()) <= 24) {
                return [
                    'can_edit' => true,
                    'can_request_edit' => false,
                    'status' => 'new_transaction',
                    'message' => 'Can edit new transaction within 24 hours'
                ];
            }
            
            // Check if can request edit
            if ($this->is_approved) {
                return [
                    'can_edit' => false,
                    'can_request_edit' => true,
                    'status' => 'can_request',
                    'message' => 'Can request edit approval'
                ];
            }
            
            return [
                'can_edit' => false,
                'can_request_edit' => false,
                'status' => 'no_access',
                'message' => 'No edit access for this transaction'
            ];
        }
        
        return [
            'can_edit' => false,
            'can_request_edit' => false,
            'status' => 'no_role',
            'message' => 'User has no appropriate role'
        ];
    }

    /**
     * Validate transaction before save
     */
    public function validateTransaction(): array
    {
        $errors = [];

        // Validate transaction type and related fields
        switch ($this->transaction_type) {
            case self::TYPE_STORAGE_TO_UNIT:
                if (!$this->source_storage_id || !$this->unit_id) {
                    $errors[] = 'Source storage and unit are required for this transaction type.';
                }
                break;

            case self::TYPE_STORAGE_TO_TRUCK:
                if (!$this->source_storage_id || !$this->destination_truck_id) {
                    $errors[] = 'Source storage and destination truck are required for this transaction type.';
                }
                break;

            case self::TYPE_TRUCK_TO_UNIT:
                if (!$this->source_truck_id || !$this->unit_id) {
                    $errors[] = 'Source truck and unit are required for this transaction type.';
                }
                break;

            case self::TYPE_VENDOR_TO_STORAGE:
                if (!$this->destination_storage_id) {
                    $errors[] = 'Destination storage is required for this transaction type.';
                }
                break;
        }

        // Validate capacity
        if ($this->transaction_type === self::TYPE_STORAGE_TO_UNIT || 
            $this->transaction_type === self::TYPE_STORAGE_TO_TRUCK) {
            if ($this->sourceStorage && $this->sourceStorage->current_capacity < $this->fuel_amount) {
                $errors[] = 'Insufficient fuel in source storage.';
            }
        }

        if ($this->transaction_type === self::TYPE_TRUCK_TO_UNIT) {
            if ($this->sourceTruck && $this->sourceTruck->current_capacity < $this->fuel_amount) {
                $errors[] = 'Insufficient fuel in source truck.';
            }
        }

        // Validate unit mileage progression
        if ($this->unit && ($this->unit_km || $this->unit_hm)) {
            if ($this->unit_km && $this->unit_km < $this->unit->current_km) {
                $errors[] = 'Unit KM cannot be less than current KM.';
            }
            if ($this->unit_hm && $this->unit_hm < $this->unit->current_hm) {
                $errors[] = 'Unit HM cannot be less than current HM.';
            }
        }

        return $errors;
    }

    /**
     * Scopes
     */

    /**
     * Scope for approved transactions
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope for pending approval
     */
    public function scopePendingApproval($query)
    {
        return $query->where('is_approved', false);
    }

    /**
     * Scope by transaction type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope by unit
     */
    public function scopeByUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }

    /**
     * Scope by storage
     */
    public function scopeByStorage($query, $storageId)
    {
        return $query->where(function($q) use ($storageId) {
            $q->where('source_storage_id', $storageId)
              ->orWhere('destination_storage_id', $storageId);
        });
    }

    /**
     * Scope by truck
     */
    public function scopeByTruck($query, $truckId)
    {
        return $query->where(function($q) use ($truckId) {
            $q->where('source_truck_id', $truckId)
              ->orWhere('destination_truck_id', $truckId);
        });
    }
}