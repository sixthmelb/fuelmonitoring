<?php

namespace App\Observers;

use App\Models\FuelStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\LowFuelStorageAlert;

/**
 * Command untuk membuat observer ini:
 * php artisan make:observer FuelStorageObserver --model=FuelStorage
 * 
 * Jangan lupa register di AppServiceProvider:
 * FuelStorage::observe(FuelStorageObserver::class);
 * 
 * Observer ini menangani:
 * - Alert ketika capacity di bawah threshold
 * - Logging perubahan capacity
 * - Validasi capacity limits
 */
class FuelStorageObserver
{
    /**
     * Handle the FuelStorage "creating" event.
     */
    public function creating(FuelStorage $fuelStorage): bool
    {
        // Validasi capacity tidak melebihi max
        if ($fuelStorage->current_capacity > $fuelStorage->max_capacity) {
            Log::error('Fuel storage current capacity exceeds maximum', [
                'storage' => $fuelStorage->toArray()
            ]);
            return false;
        }

        // Set default min_threshold jika tidak ada (10% dari max_capacity)
        if (empty($fuelStorage->min_threshold)) {
            $fuelStorage->min_threshold = $fuelStorage->max_capacity * 0.1;
        }

        return true;
    }

    /**
     * Handle the FuelStorage "created" event.
     */
    public function created(FuelStorage $fuelStorage): void
    {
        Log::info('New fuel storage created', [
            'storage_code' => $fuelStorage->code,
            'storage_name' => $fuelStorage->name,
            'max_capacity' => $fuelStorage->max_capacity
        ]);
    }

    /**
     * Handle the FuelStorage "updating" event.
     */
    public function updating(FuelStorage $fuelStorage): bool
    {
        // Validasi capacity tidak melebihi max atau di bawah 0
        if ($fuelStorage->current_capacity > $fuelStorage->max_capacity) {
            Log::error('Fuel storage update failed: current capacity exceeds maximum', [
                'storage_id' => $fuelStorage->id,
                'current' => $fuelStorage->current_capacity,
                'max' => $fuelStorage->max_capacity
            ]);
            return false;
        }

        if ($fuelStorage->current_capacity < 0) {
            Log::error('Fuel storage update failed: current capacity cannot be negative', [
                'storage_id' => $fuelStorage->id,
                'current' => $fuelStorage->current_capacity
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle the FuelStorage "updated" event.
     */
    public function updated(FuelStorage $fuelStorage): void
    {
        // Log perubahan capacity
        if ($fuelStorage->wasChanged('current_capacity')) {
            $oldCapacity = $fuelStorage->getOriginal('current_capacity');
            $newCapacity = $fuelStorage->current_capacity;
            $difference = $newCapacity - $oldCapacity;

            Log::info('Fuel storage capacity updated', [
                'storage_code' => $fuelStorage->code,
                'old_capacity' => $oldCapacity,
                'new_capacity' => $newCapacity,
                'difference' => $difference,
                'percentage' => $fuelStorage->capacity_percentage
            ]);

            // Check threshold dan kirim alert jika perlu
            $this->checkThresholdAlert($fuelStorage, $oldCapacity);
        }
    }

    /**
     * Check dan kirim alert jika capacity di bawah threshold
     */
    private function checkThresholdAlert(FuelStorage $fuelStorage, float $oldCapacity): void
    {
        // Jika sebelumnya di atas threshold, sekarang di bawah threshold
        if ($oldCapacity > $fuelStorage->min_threshold && $fuelStorage->current_capacity <= $fuelStorage->min_threshold) {
            
            Log::warning('Fuel storage below threshold', [
                'storage_code' => $fuelStorage->code,
                'storage_name' => $fuelStorage->name,
                'current_capacity' => $fuelStorage->current_capacity,
                'min_threshold' => $fuelStorage->min_threshold,
                'percentage' => $fuelStorage->capacity_percentage
            ]);

            // Kirim notifikasi ke manager
            $this->sendLowFuelAlert($fuelStorage);
        }

        // Alert kritis jika capacity sangat rendah (5% atau kurang)
        $criticalThreshold = $fuelStorage->max_capacity * 0.05;
        if ($oldCapacity > $criticalThreshold && $fuelStorage->current_capacity <= $criticalThreshold) {
            
            Log::critical('Fuel storage critically low', [
                'storage_code' => $fuelStorage->code,
                'storage_name' => $fuelStorage->name,
                'current_capacity' => $fuelStorage->current_capacity,
                'percentage' => $fuelStorage->capacity_percentage
            ]);

            // Kirim notifikasi kritis
            $this->sendCriticalFuelAlert($fuelStorage);
        }
    }

    /**
     * Kirim alert low fuel ke manager
     */
    private function sendLowFuelAlert(FuelStorage $fuelStorage): void
    {
        try {
            // Get all managers
            $managers = \App\Models\User::role('manager')->where('is_active', true)->get();
            
            foreach ($managers as $manager) {
                // Kirim notifikasi (bisa via email, database notification, dll)
                $manager->notify(new LowFuelStorageAlert($fuelStorage, 'low'));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send low fuel alert', [
                'error' => $e->getMessage(),
                'storage_id' => $fuelStorage->id
            ]);
        }
    }

    /**
     * Kirim alert critical fuel ke manager dan superadmin
     */
    private function sendCriticalFuelAlert(FuelStorage $fuelStorage): void
    {
        try {
            // Get managers and superadmins
            $users = \App\Models\User::whereHas('roles', function($query) {
                $query->whereIn('name', ['manager', 'superadmin']);
            })->where('is_active', true)->get();
            
            foreach ($users as $user) {
                $user->notify(new LowFuelStorageAlert($fuelStorage, 'critical'));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send critical fuel alert', [
                'error' => $e->getMessage(),
                'storage_id' => $fuelStorage->id
            ]);
        }
    }
}