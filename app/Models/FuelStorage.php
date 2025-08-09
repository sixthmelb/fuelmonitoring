<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\FuelStorageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

/**
 * Command untuk membuat model ini:
 * php artisan make:model FuelStorage -m
 * 
 * Observer akan otomatis update real-time capacity
 */
#[ObservedBy([FuelStorageObserver::class])]
class FuelStorage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'location',
        'max_capacity',
        'current_capacity',
        'min_threshold',
        'description',
        'is_active',
    ];

    protected $casts = [
        'max_capacity' => 'decimal:2',
        'current_capacity' => 'decimal:2',
        'min_threshold' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get fuel transactions from this storage
     */
    public function outgoingTransactions()
    {
        return $this->hasMany(FuelTransaction::class, 'source_storage_id');
    }

    /**
     * Get fuel transactions to this storage (from vendor)
     */
    public function incomingTransactions()
    {
        return $this->hasMany(FuelTransaction::class, 'destination_storage_id');
    }

    /**
     * Check if storage is below minimum threshold
     */
    public function isBelowThreshold(): bool
    {
        return $this->current_capacity <= $this->min_threshold;
    }

    /**
     * Get available capacity
     */
    public function getAvailableCapacityAttribute(): float
    {
        return $this->max_capacity - $this->current_capacity;
    }

    /**
     * Get capacity percentage
     */
    public function getCapacityPercentageAttribute(): float
    {
        if ($this->max_capacity == 0) return 0;
        return ($this->current_capacity / $this->max_capacity) * 100;
    }

    /**
     * Update current capacity
     */
    public function updateCapacity(float $amount, string $type = 'subtract'): bool
    {
        $newCapacity = $type === 'add' 
            ? $this->current_capacity + $amount 
            : $this->current_capacity - $amount;

        if ($newCapacity < 0 || $newCapacity > $this->max_capacity) {
            return false;
        }

        $this->update(['current_capacity' => $newCapacity]);
        return true;
    }

    /**
     * Scope for active storages
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for storages below threshold
     */
    public function scopeBelowThreshold($query)
    {
        return $query->whereRaw('current_capacity <= min_threshold');
    }
}