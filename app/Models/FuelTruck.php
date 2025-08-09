<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\FuelTruckObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

/**
 * Command untuk membuat model ini:
 * php artisan make:model FuelTruck -m
 * 
 * Observer akan otomatis update real-time capacity
 */
#[ObservedBy([FuelTruckObserver::class])]
class FuelTruck extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'license_plate',
        'driver_name',
        'driver_phone',
        'max_capacity',
        'current_capacity',
        'status',
        'last_maintenance',
        'is_active',
    ];

    protected $casts = [
        'max_capacity' => 'decimal:2',
        'current_capacity' => 'decimal:2',
        'last_maintenance' => 'date',
        'is_active' => 'boolean',
    ];

    const STATUS_AVAILABLE = 'available';
    const STATUS_IN_USE = 'in_use';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_OUT_OF_SERVICE = 'out_of_service';

    /**
     * Get all possible statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_IN_USE => 'In Use',
            self::STATUS_MAINTENANCE => 'Maintenance',
            self::STATUS_OUT_OF_SERVICE => 'Out of Service',
        ];
    }

    /**
     * Get fuel transactions from this truck
     */
    public function outgoingTransactions()
    {
        return $this->hasMany(FuelTransaction::class, 'source_truck_id');
    }

    /**
     * Get fuel transactions to this truck
     */
    public function incomingTransactions()
    {
        return $this->hasMany(FuelTransaction::class, 'destination_truck_id');
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
     * Check if truck is available for use
     */
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE && $this->is_active;
    }

    /**
     * Scope for active trucks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for available trucks
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE)
                    ->where('is_active', true);
    }
}