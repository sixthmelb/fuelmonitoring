<?php

namespace App\Filament\Widgets;

use App\Models\FuelStorage;
use App\Models\FuelTruck;
use App\Models\FuelTransaction;
use App\Models\ApprovalRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Command untuk membuat widget ini:
 * php artisan make:filament-widget FuelStorageOverview --stats-overview
 * 
 * Widget untuk dashboard overview dengan real-time metrics
 */
class FuelStorageOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';
    
    // Refresh setiap 30 detik
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // Get real-time data
        $storages = FuelStorage::active()->get();
        $trucks = FuelTruck::active()->get();
        
        $totalStorageCapacity = $storages->sum('max_capacity');
        $currentStorageFuel = $storages->sum('current_capacity');
        $storagePercentage = $totalStorageCapacity > 0 ? ($currentStorageFuel / $totalStorageCapacity) * 100 : 0;
        
        $totalTruckCapacity = $trucks->sum('max_capacity');
        $currentTruckFuel = $trucks->sum('current_capacity');
        $truckPercentage = $totalTruckCapacity > 0 ? ($currentTruckFuel / $totalTruckCapacity) * 100 : 0;
        
        $belowThresholdCount = $storages->filter(fn($s) => $s->isBelowThreshold())->count();
        $criticalStorages = $storages->filter(fn($s) => $s->capacity_percentage <= 5)->count();
        
        $todayTransactions = FuelTransaction::whereDate('created_at', today())->count();
        $todayFuelDistributed = FuelTransaction::whereDate('created_at', today())->sum('fuel_amount');
        
        $pendingApprovals = ApprovalRequest::pending()->count();
        
        return [
            // Storage Overview
            Stat::make('Total Storage Fuel', Number::format($currentStorageFuel, 0) . ' L')
                ->description('of ' . Number::format($totalStorageCapacity, 0) . ' L capacity')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->chart($this->getStorageChart())
                ->color($this->getStorageColor($storagePercentage))
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                    'wire:click' => '$dispatch("openModal", { component: "storage-detail-modal" })'
                ]),
                
            // Truck Overview  
            Stat::make('Truck Fleet Fuel', Number::format($currentTruckFuel, 0) . ' L')
                ->description('of ' . Number::format($totalTruckCapacity, 0) . ' L capacity')
                ->descriptionIcon('heroicon-m-truck')
                ->chart($this->getTruckChart())
                ->color($this->getTruckColor($truckPercentage)),
                
            // Alert Status
            Stat::make('Storage Alerts', $belowThresholdCount + $criticalStorages)
                ->description($belowThresholdCount . ' below threshold, ' . $criticalStorages . ' critical')
                ->descriptionIcon($belowThresholdCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($belowThresholdCount > 0 ? 'danger' : 'success')
                ->chart($this->getAlertChart()),
                
            // Today's Activity
            Stat::make("Today's Transactions", $todayTransactions)
                ->description(Number::format($todayFuelDistributed, 0) . ' L distributed')
                ->descriptionIcon('heroicon-m-arrow-right-circle')
                ->chart($this->getDailyTransactionChart())
                ->color('info'),
                
            // Pending Items
            Stat::make('Pending Approvals', $pendingApprovals)
                ->description('Awaiting manager approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingApprovals > 0 ? 'warning' : 'success')
                ->url($pendingApprovals > 0 ? '/admin/approval-requests' : null),
                
            // Efficiency Metric
            Stat::make('Fleet Efficiency', $this->calculateFleetEfficiency())
                ->description('Average consumption today')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart($this->getEfficiencyChart())
                ->color('primary'),
        ];
    }
    
    private function getStorageColor(float $percentage): string
    {
        return match(true) {
            $percentage >= 70 => 'success',
            $percentage >= 40 => 'warning',
            $percentage >= 20 => 'danger',
            default => 'gray'
        };
    }
    
    private function getTruckColor(float $percentage): string
    {
        return match(true) {
            $percentage >= 60 => 'success',
            $percentage >= 30 => 'warning',
            default => 'danger'
        };
    }
    
    private function getStorageChart(): array
    {
        // Generate 7-day storage capacity trend
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            
            // Get total capacity at end of day (simplified calculation)
            $dailyTransactions = FuelTransaction::whereDate('created_at', $date)
                ->where('is_approved', true)
                ->get();
            
            $netChange = $dailyTransactions->sum(function ($transaction) {
                return match($transaction->transaction_type) {
                    FuelTransaction::TYPE_VENDOR_TO_STORAGE => $transaction->fuel_amount,
                    FuelTransaction::TYPE_STORAGE_TO_UNIT, 
                    FuelTransaction::TYPE_STORAGE_TO_TRUCK => -$transaction->fuel_amount,
                    default => 0
                };
            });
            
            $data[] = max(0, $netChange);
        }
        
        return $data;
    }
    
    private function getTruckChart(): array
    {
        // Generate truck utilization for last 7 days
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $activeCount = FuelTransaction::whereDate('created_at', $date)
                ->whereNotNull('source_truck_id')
                ->orWhereNotNull('destination_truck_id')
                ->distinct(['source_truck_id', 'destination_truck_id'])
                ->count();
            $data[] = $activeCount;
        }
        
        return $data;
    }
    
    private function getAlertChart(): array
    {
        // Alert trend for last 7 days
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            // Simplified: assume current threshold status
            $alertCount = FuelStorage::belowThreshold()->count();
            $data[] = $alertCount;
        }
        
        return $data;
    }
    
    private function getDailyTransactionChart(): array
    {
        // Daily transaction volume for last 7 days
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $count = FuelTransaction::whereDate('created_at', $date)->count();
            $data[] = $count;
        }
        
        return $data;
    }
    
    private function getEfficiencyChart(): array
    {
        // Weekly efficiency trend (simplified)
        return [15, 18, 16, 20, 17, 19, 16]; // Mock data for now
    }
    
    private function calculateFleetEfficiency(): string
    {
        $todayTransactions = FuelTransaction::whereDate('created_at', today())
            ->whereNotNull('unit_id')
            ->where('is_approved', true)
            ->get();
            
        if ($todayTransactions->isEmpty()) {
            return 'No data';
        }
        
        $totalConsumption = 0;
        $totalDistance = 0;
        $count = 0;
        
        foreach ($todayTransactions as $transaction) {
            $consumption = $transaction->fuel_consumption;
            if ($consumption && isset($consumption['per_km'])) {
                $totalConsumption += $consumption['per_km'];
                $count++;
            }
        }
        
        if ($count === 0) {
            return 'No data';
        }
        
        $avgConsumption = $totalConsumption / $count;
        return number_format($avgConsumption, 1) . ' L/KM';
    }
}