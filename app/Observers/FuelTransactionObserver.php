<?php

namespace App\Observers;

use App\Models\FuelTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Command untuk membuat observer ini:
 * php artisan make:observer FuelTransactionObserver --model=FuelTransaction
 * 
 * Jangan lupa register di AppServiceProvider:
 * FuelTransaction::observe(FuelTransactionObserver::class);
 * 
 * Observer ini menangani:
 * - Auto update capacity storage/truck dan unit mileage
 * - Generate transaction code
 * - Validation business rules
 */
class FuelTransactionObserver
{
    /**
     * Handle the FuelTransaction "creating" event.
     * Validasi dan generate code sebelum create
     */
    public function creating(FuelTransaction $fuelTransaction): bool
    {
        try {
            // Generate transaction code jika belum ada
            if (empty($fuelTransaction->transaction_code)) {
                $fuelTransaction->transaction_code = FuelTransaction::generateTransactionCode();
            }

            // Set transaction date jika belum ada
            if (empty($fuelTransaction->transaction_date)) {
                $fuelTransaction->transaction_date = now();
            }

            // Validasi transaction
            $errors = $fuelTransaction->validateTransaction();
            if (!empty($errors)) {
                Log::error('Fuel transaction validation failed', [
                    'errors' => $errors,
                    'transaction' => $fuelTransaction->toArray()
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error in FuelTransactionObserver creating', [
                'error' => $e->getMessage(),
                'transaction' => $fuelTransaction->toArray()
            ]);
            return false;
        }
    }

    /**
     * Handle the FuelTransaction "created" event.
     * Update capacity dan mileage setelah transaction berhasil dibuat
     */
    public function created(FuelTransaction $fuelTransaction): void
    {
        try {
            $this->updateCapacities($fuelTransaction, 'created');
            $this->updateUnitMileage($fuelTransaction);
            
            Log::info('Fuel transaction created successfully', [
                'transaction_code' => $fuelTransaction->transaction_code,
                'type' => $fuelTransaction->transaction_type,
                'amount' => $fuelTransaction->fuel_amount
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating capacities after transaction creation', [
                'error' => $e->getMessage(),
                'transaction_id' => $fuelTransaction->id
            ]);
        }
    }

    /**
     * Handle the FuelTransaction "updating" event.
     * Validasi sebelum update
     */
    public function updating(FuelTransaction $fuelTransaction): bool
    {
        try {
            // Jika capacity-related fields berubah, validasi ulang
            if ($fuelTransaction->isDirty(['fuel_amount', 'source_storage_id', 'source_truck_id'])) {
                $errors = $fuelTransaction->validateTransaction();
                if (!empty($errors)) {
                    Log::error('Fuel transaction update validation failed', [
                        'errors' => $errors,
                        'transaction_id' => $fuelTransaction->id
                    ]);
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error in FuelTransactionObserver updating', [
                'error' => $e->getMessage(),
                'transaction_id' => $fuelTransaction->id
            ]);
            return false;
        }
    }

    /**
     * Handle the FuelTransaction "updated" event.
     * Adjust capacity jika ada perubahan amount
     */
    public function updated(FuelTransaction $fuelTransaction): void
    {
        try {
            // Jika fuel_amount berubah, adjust capacity
            if ($fuelTransaction->wasChanged('fuel_amount')) {
                $this->adjustCapacityForUpdate($fuelTransaction);
            }

            // Update unit mileage jika berubah
            if ($fuelTransaction->wasChanged(['unit_km', 'unit_hm'])) {
                $this->updateUnitMileage($fuelTransaction);
            }

            Log::info('Fuel transaction updated successfully', [
                'transaction_id' => $fuelTransaction->id,
                'changes' => $fuelTransaction->getChanges()
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating capacities after transaction update', [
                'error' => $e->getMessage(),
                'transaction_id' => $fuelTransaction->id
            ]);
        }
    }

    /**
     * Handle the FuelTransaction "deleting" event.
     * Revert capacity changes sebelum delete
     */
    public function deleting(FuelTransaction $fuelTransaction): void
    {
        try {
            $this->updateCapacities($fuelTransaction, 'deleted');
            
            Log::info('Fuel transaction deleted, capacities reverted', [
                'transaction_id' => $fuelTransaction->id,
                'transaction_code' => $fuelTransaction->transaction_code
            ]);
        } catch (\Exception $e) {
            Log::error('Error reverting capacities after transaction deletion', [
                'error' => $e->getMessage(),
                'transaction_id' => $fuelTransaction->id
            ]);
        }
    }

    /**
     * Update capacities berdasarkan transaction type
     */
    private function updateCapacities(FuelTransaction $transaction, string $action): void
    {
        $amount = $transaction->fuel_amount;
        $isReverting = $action === 'deleted';

        switch ($transaction->transaction_type) {
            case FuelTransaction::TYPE_VENDOR_TO_STORAGE:
                // Vendor ke Storage: tambah capacity storage
                if ($transaction->destinationStorage) {
                    $type = $isReverting ? 'subtract' : 'add';
                    $transaction->destinationStorage->updateCapacity($amount, $type);
                }
                break;

            case FuelTransaction::TYPE_STORAGE_TO_TRUCK:
                // Storage ke Truck: kurangi storage, tambah truck
                if ($transaction->sourceStorage) {
                    $type = $isReverting ? 'add' : 'subtract';
                    $transaction->sourceStorage->updateCapacity($amount, $type);
                }
                if ($transaction->destinationTruck) {
                    $type = $isReverting ? 'subtract' : 'add';
                    $transaction->destinationTruck->updateCapacity($amount, $type);
                }
                break;

            case FuelTransaction::TYPE_STORAGE_TO_UNIT:
                // Storage ke Unit: kurangi storage saja (unit tidak punya capacity tracking)
                if ($transaction->sourceStorage) {
                    $type = $isReverting ? 'add' : 'subtract';
                    $transaction->sourceStorage->updateCapacity($amount, $type);
                }
                break;

            case FuelTransaction::TYPE_TRUCK_TO_UNIT:
                // Truck ke Unit: kurangi truck saja
                if ($transaction->sourceTruck) {
                    $type = $isReverting ? 'add' : 'subtract';
                    $transaction->sourceTruck->updateCapacity($amount, $type);
                }
                break;
        }
    }

    /**
     * Adjust capacity untuk update transaction
     */
    private function adjustCapacityForUpdate(FuelTransaction $transaction): void
    {
        $originalAmount = $transaction->getOriginal('fuel_amount');
        $newAmount = $transaction->fuel_amount;
        $difference = $newAmount - $originalAmount;

        if ($difference == 0) return;

        switch ($transaction->transaction_type) {
            case FuelTransaction::TYPE_VENDOR_TO_STORAGE:
                if ($transaction->destinationStorage) {
                    $type = $difference > 0 ? 'add' : 'subtract';
                    $transaction->destinationStorage->updateCapacity(abs($difference), $type);
                }
                break;

            case FuelTransaction::TYPE_STORAGE_TO_TRUCK:
                if ($transaction->sourceStorage) {
                    $type = $difference > 0 ? 'subtract' : 'add';
                    $transaction->sourceStorage->updateCapacity(abs($difference), $type);
                }
                if ($transaction->destinationTruck) {
                    $type = $difference > 0 ? 'add' : 'subtract';
                    $transaction->destinationTruck->updateCapacity(abs($difference), $type);
                }
                break;

            case FuelTransaction::TYPE_STORAGE_TO_UNIT:
                if ($transaction->sourceStorage) {
                    $type = $difference > 0 ? 'subtract' : 'add';
                    $transaction->sourceStorage->updateCapacity(abs($difference), $type);
                }
                break;

            case FuelTransaction::TYPE_TRUCK_TO_UNIT:
                if ($transaction->sourceTruck) {
                    $type = $difference > 0 ? 'subtract' : 'add';
                    $transaction->sourceTruck->updateCapacity(abs($difference), $type);
                }
                break;
        }
    }

    /**
     * Update unit mileage berdasarkan transaksi
     */
    private function updateUnitMileage(FuelTransaction $transaction): void
    {
        if (!$transaction->unit) return;

        $updates = [];

        // Update KM jika ada dan lebih besar dari current
        if ($transaction->unit_km && $transaction->unit_km > $transaction->unit->current_km) {
            $updates['current_km'] = $transaction->unit_km;
        }

        // Update HM jika ada dan lebih besar dari current
        if ($transaction->unit_hm && $transaction->unit_hm > $transaction->unit->current_hm) {
            $updates['current_hm'] = $transaction->unit_hm;
        }

        if (!empty($updates)) {
            $transaction->unit->update($updates);
            
            Log::info('Unit mileage updated', [
                'unit_code' => $transaction->unit->code,
                'updates' => $updates
            ]);
        }
    }

    /**
     * Handle the FuelTransaction "restored" event.
     * Restore capacity changes jika transaction di-restore dari soft delete
     */
    public function restored(FuelTransaction $fuelTransaction): void
    {
        try {
            $this->updateCapacities($fuelTransaction, 'created');
            $this->updateUnitMileage($fuelTransaction);
            
            Log::info('Fuel transaction restored, capacities updated', [
                'transaction_id' => $fuelTransaction->id,
                'transaction_code' => $fuelTransaction->transaction_code
            ]);
        } catch (\Exception $e) {
            Log::error('Error restoring capacities after transaction restoration', [
                'error' => $e->getMessage(),
                'transaction_id' => $fuelTransaction->id
            ]);
        }
    }

    /**
     * Handle the FuelTransaction "forceDeleted" event.
     * Final cleanup when transaction permanently deleted
     */
    public function forceDeleted(FuelTransaction $fuelTransaction): void
    {
        Log::info('Fuel transaction permanently deleted', [
            'transaction_id' => $fuelTransaction->id,
            'transaction_code' => $fuelTransaction->transaction_code
        ]);
    }
}