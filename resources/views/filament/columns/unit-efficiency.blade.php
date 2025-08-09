{{-- 
File: resources/views/filament/columns/unit-efficiency.blade.php

Command untuk membuat view ini:
touch resources/views/filament/columns/unit-efficiency.blade.php

Unit efficiency display untuk table
--}}

@php
    $record = $getRecord();
    $fuelConsumptionPerKm = $record->fuel_consumption_per_km;
    $fuelConsumptionPerHm = $record->fuel_consumption_per_hm;
    
    // Get latest transactions count for this month
    $monthlyTransactions = $record->fuelTransactions()
        ->whereMonth('created_at', now()->month)
        ->count();
        
    $totalFuelThisMonth = $record->fuelTransactions()
        ->whereMonth('created_at', now()->month)
        ->sum('fuel_amount');
@endphp

<div class="space-y-1 text-sm">
    @if($fuelConsumptionPerKm)
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-1">
                <x-filament::icon icon="heroicon-m-map" class="h-3 w-3 text-blue-400" />
                <span class="text-xs text-gray-600 dark:text-gray-400">L/KM:</span>
            </div>
            <span class="font-medium {{ $fuelConsumptionPerKm > 8 ? 'text-red-600' : ($fuelConsumptionPerKm > 5 ? 'text-yellow-600' : 'text-green-600') }}">
                {{ number_format($fuelConsumptionPerKm, 2) }}
            </span>
        </div>
    @endif
    
    @if($fuelConsumptionPerHm)
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-1">
                <x-filament::icon icon="heroicon-m-clock" class="h-3 w-3 text-green-400" />
                <span class="text-xs text-gray-600 dark:text-gray-400">L/HM:</span>
            </div>
            <span class="font-medium {{ $fuelConsumptionPerHm > 50 ? 'text-red-600' : ($fuelConsumptionPerHm > 30 ? 'text-yellow-600' : 'text-green-600') }}">
                {{ number_format($fuelConsumptionPerHm, 2) }}
            </span>
        </div>
    @endif
    
    @if($monthlyTransactions > 0)
        <div class="pt-1 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-500 dark:text-gray-500">This month:</span>
                <div class="text-right">
                    <div class="text-xs font-medium text-gray-700 dark:text-gray-300">
                        {{ number_format($totalFuelThisMonth, 0) }}L
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-500">
                        {{ $monthlyTransactions }} refills
                    </div>
                </div>
            </div>
        </div>
    @endif
    
    @if(!$fuelConsumptionPerKm && !$fuelConsumptionPerHm)
        <div class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">
            No efficiency data
        </div>
    @endif
</div>