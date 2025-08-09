<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Command untuk membuat model ini:
 * php artisan make:model Unit -m
 * 
 * Model untuk unit kendaraan yang menerima fuel (DT-001, dll)
 */
class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'brand',
        'model',
        'year',
        'engine_capacity',
        'fuel_tank_capacity',
        'current_km',
        'current_hm',
        'last_service_km',
        'last_service_hm',
        'operator_name',
        'location',
        'status',
        'is_active',
    ];

    protected $casts = [
        'engine_capacity' => 'decimal:2',
        'fuel_tank_capacity' => 'decimal:2',
        'current_km' => 'decimal:2',
        'current_hm' => 'decimal:2',
        'last_service_km' => 'decimal:2',
        'last_service_hm' => 'decimal:2',
        'year' => 'integer',
        'is_active' => 'boolean',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_MAINTENANCE = 'maintenance';
    const STATUS_STANDBY = 'standby';
    const STATUS_OUT_OF_SERVICE = 'out_of_service';

    const TYPE_DUMP_TRUCK = 'dump_truck';
    const TYPE_EXCAVATOR = 'excavator'; 
    const TYPE_DOZER = 'dozer';
    const TYPE_LOADER = 'loader';
    const TYPE_GRADER = 'grader';
    const TYPE_COMPACTOR = 'compactor';
    const TYPE_OTHER = 'other';

    /**
     * Get all possible statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_MAINTENANCE => 'Maintenance',
            self::STATUS_STANDBY => 'Standby',
            self::STATUS_OUT_OF_SERVICE => 'Out of Service',
        ];
    }

    /**
     * Get all possible types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_DUMP_TRUCK => 'Dump Truck',
            self::TYPE_EXCAVATOR => 'Excavator',
            self::TYPE_DOZER => 'Dozer',
            self::TYPE_LOADER => 'Loader',
            self::TYPE_GRADER => 'Grader',
            self::TYPE_COMPACTOR => 'Compactor',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get fuel transactions for this unit
     */
    public function fuelTransactions()
    {
        return $this->hasMany(FuelTransaction::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get latest fuel transaction
     */
    public function latestFuelTransaction()
    {
        return $this->hasOne(FuelTransaction::class)->latest();
    }

    /**
     * Get previous fuel transaction for consumption calculation
     */
    public function previousFuelTransaction()
    {
        return $this->hasMany(FuelTransaction::class)
                   ->orderBy('created_at', 'desc')
                   ->skip(1)
                   ->first();
    }

    /**
     * Calculate fuel consumption per KM
     */
    public function getFuelConsumptionPerKmAttribute(): ?float
    {
        $latest = $this->latestFuelTransaction;
        $previous = $this->previousFuelTransaction();

        if (!$latest || !$previous) return null;

        $kmDiff = $latest->unit_km - $previous->unit_km;
        if ($kmDiff <= 0) return null;

        return $latest->fuel_amount / $kmDiff;
    }

    /**
     * Calculate fuel consumption per HM (Hour Meter)
     */
    public function getFuelConsumptionPerHmAttribute(): ?float
    {
        $latest = $this->latestFuelTransaction;
        $previous = $this->previousFuelTransaction();

        if (!$latest || !$previous) return null;

        $hmDiff = $latest->unit_hm - $previous->unit_hm;
        if ($hmDiff <= 0) return null;

        return $latest->fuel_amount / $hmDiff;
    }

    /**
     * Estimate fuel consumption based on KM/HM difference
     */
    public function estimateFuelConsumption(float $newKm = null, float $newHm = null): ?float
    {
        $latest = $this->latestFuelTransaction;
        if (!$latest) return null;

        // Calculate based on KM if provided
        if ($newKm && $this->fuel_consumption_per_km) {
            $kmDiff = $newKm - $latest->unit_km;
            if ($kmDiff > 0) {
                return $kmDiff * $this->fuel_consumption_per_km;
            }
        }

        // Calculate based on HM if provided
        if ($newHm && $this->fuel_consumption_per_hm) {
            $hmDiff = $newHm - $latest->unit_hm;
            if ($hmDiff > 0) {
                return $hmDiff * $this->fuel_consumption_per_hm;
            }
        }

        return null;
    }

    /**
     * Update current KM and HM
     */
    public function updateMileage(float $km = null, float $hm = null): bool
    {
        $updates = [];
        
        if ($km !== null && $km >= $this->current_km) {
            $updates['current_km'] = $km;
        }
        
        if ($hm !== null && $hm >= $this->current_hm) {
            $updates['current_hm'] = $hm;
        }

        if (!empty($updates)) {
            $this->update($updates);
            return true;
        }

        return false;
    }

    /**
     * Scope for active units
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('status', '!=', self::STATUS_OUT_OF_SERVICE);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}