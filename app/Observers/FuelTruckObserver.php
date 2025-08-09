<?php

namespace App\Observers;

use App\Models\FuelTruck;
use Illuminate\Support\Facades\Log;

/**
 * Command untuk membuat observer ini:
 * php artisan make:observer FuelTruckObserver --model=FuelTruck
 * 
 * Jangan lupa register di AppServiceProvider:
 * FuelTruck::observe(FuelTruckObserver::class);
 * 
 * Observer ini menangani:
 * - Validasi capacity limits
 * - Auto update status berdasarkan capacity
 * - Logging perubahan capacity
 */
class FuelTruckObserver
{
    /**
     * Handle the FuelTruck "creating" event.
     */
    public function creating(FuelTruck $fuelTruck): bool
    {
        // Validasi capacity tidak melebihi max
        if ($fuelTruck->current_capacity > $fuelTruck->max_capacity) {
            Log::error('Fuel truck current capacity exceeds maximum', [
                'truck' => $fuelTruck->toArray()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle the FuelTruck "created" event.
     */
    public function created(FuelTruck $fuelTruck): void
    {
        Log::info('New fuel truck created', [
            'truck_code' => $fuelTruck->code,
            'truck_name' => $fuelTruck->name,
            'license_plate' => $fuelTruck->license_plate,
            'max_capacity' => $fuelTruck->max_capacity
        ]);
    }

    /**
     * Handle the FuelTruck "updating" event.
     */
    public function updating(FuelTruck $fuelTruck): bool
    {
        // Validasi capacity tidak melebihi max atau di bawah 0
        if ($fuelTruck->current_capacity > $fuelTruck->max_capacity) {
            Log::error('Fuel truck update failed: current capacity exceeds maximum', [
                'truck_id' => $fuelTruck->id,
                'current' => $fuelTruck->current_capacity,
                'max' => $fuelTruck->max_capacity
            ]);
            return false;
        }

        if ($fuelTruck->current_capacity < 0) {
            Log::error('Fuel truck update failed: current capacity cannot be negative', [
                'truck_id' => $fuelTruck->id,
                'current' => $fuelTruck->current_capacity
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle the FuelTruck "updated" event.
     */
    public function updated(FuelTruck $fuelTruck): void
    {
        // Log perubahan capacity
        if ($fuelTruck->wasChanged('current_capacity')) {
            $oldCapacity = $fuelTruck->getOriginal('current_capacity');
            $newCapacity = $fuelTruck->current_capacity;
            $difference = $newCapacity - $oldCapacity;

            Log::info('Fuel truck capacity updated', [
                'truck_code' => $fuelTruck->code,
                'license_plate' => $fuelTruck->license_plate,
                'old_capacity' => $oldCapacity,
                'new_capacity' => $newCapacity,
                'difference' => $difference,
                'percentage' => $fuelTruck->capacity_percentage
            ]);

            // Auto update status berdasarkan capacity
            $this->updateStatusBasedOnCapacity($fuelTruck);
        }

        // Log perubahan status
        if ($fuelTruck->wasChanged('status')) {
            $oldStatus = $fuelTruck->getOriginal('status');
            $newStatus = $fuelTruck->status;

            Log::info('Fuel truck status changed', [
                'truck_code' => $fuelTruck->code,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);
        }
    }

    /**
     * Update status truck berdasarkan capacity
     */
    private function updateStatusBasedOnCapacity(FuelTruck $fuelTruck): void
    {
        // Jangan auto-update jika sedang maintenance atau out of service
        if (in_array($fuelTruck->status, [FuelTruck::STATUS_MAINTENANCE, FuelTruck::STATUS_OUT_OF_SERVICE])) {
            return;
        }

        $capacityPercentage = $fuelTruck->capacity_percentage;

        // Jika capacity 0%, set ke available (empty truck ready to be filled)
        // Jika capacity > 0%, bisa set ke in_use jika sedang ada transaksi aktif
        if ($capacityPercentage == 0 && $fuelTruck->status === FuelTruck::STATUS_IN_USE) {
            $fuelTruck->updateQuietly(['status' => FuelTruck::STATUS_AVAILABLE]);
            
            Log::info('Fuel truck status auto-updated to available (empty)', [
                'truck_code' => $fuelTruck->code,
                'capacity_percentage' => $capacityPercentage
            ]);
        }
    }

    /**
     * Handle the FuelTruck "deleting" event.
     */
    public function deleting(FuelTruck $fuelTruck): bool
    {
        // Prevent deletion jika masih ada fuel
        if ($fuelTruck->current_capacity > 0) {
            Log::error('Cannot delete fuel truck with remaining fuel', [
                'truck_id' => $fuelTruck->id,
                'current_capacity' => $fuelTruck->current_capacity
            ]);
            return false;
        }

        // Prevent deletion jika sedang in use
        if ($fuelTruck->status === FuelTruck::STATUS_IN_USE) {
            Log::error('Cannot delete fuel truck that is currently in use', [
                'truck_id' => $fuelTruck->id,
                'status' => $fuelTruck->status
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle the FuelTruck "deleted" event.
     */
    public function deleted(FuelTruck $fuelTruck): void
    {
        Log::info('Fuel truck deleted', [
            'truck_code' => $fuelTruck->code,
            'truck_name' => $fuelTruck->name
        ]);
    }
}