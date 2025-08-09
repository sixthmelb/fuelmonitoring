{{-- 
File: resources/views/filament/columns/truck-capacity-bar.blade.php

Command untuk membuat view ini:
touch resources/views/filament/columns/truck-capacity-bar.blade.php

Truck capacity bar untuk table display
--}}

@php
    $record = $getRecord();
    $percentage = $record->capacity_percentage;
    $current = $record->current_capacity;
    $max = $record->max_capacity;
    
    $colorClass = match(true) {
        $percentage >= 80 => 'bg-blue-500',
        $percentage >= 50 => 'bg-green-500',
        $percentage >= 20 => 'bg-yellow-500',
        $percentage > 0 => 'bg-orange-500',
        default => 'bg-gray-300'
    };
    
    $textColor = match(true) {
        $percentage >= 80 => 'text-blue-700',
        $percentage >= 50 => 'text-green-700',
        $percentage >= 20 => 'text-yellow-700',
        $percentage > 0 => 'text-orange-700',
        default => 'text-gray-700'
    };
    
    $statusIcon = match(true) {
        $percentage >= 80 => '🚛', // Full truck
        $percentage >= 50 => '🚚', // Half full
        $percentage >= 20 => '🚐', // Low
        $percentage > 0 => '🚌',   // Very low
        default => '🚐'           // Empty
    };
@endphp

<div class="w-full">
    <div class="flex items-center justify-between mb-1">
        <span class="text-xs font-medium {{ $textColor }} flex items-center">
            <span class="mr-1">{{ $statusIcon }}</span>
            {{ number_format($percentage, 0) }}%
        </span>
        <div class="flex items-center space-x-1">
            @if($record->status === \App\Models\FuelTruck::STATUS_IN_USE)
                <x-filament::icon 
                    icon="heroicon-m-play-circle" 
                    class="h-3 w-3 text-blue-500"
                    title="In Use"
                />
            @elseif($record->status === \App\Models\FuelTruck::STATUS_MAINTENANCE)
                <x-filament::icon 
                    icon="heroicon-m-wrench-screwdriver" 
                    class="h-3 w-3 text-yellow-500"
                    title="Under Maintenance"
                />
            @elseif($record->status === \App\Models\FuelTruck::STATUS_AVAILABLE)
                <x-filament::icon 
                    icon="heroicon-m-check-circle" 
                    class="h-3 w-3 text-green-500"
                    title="Available"
                />
            @endif
        </div>
    </div>
    
    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
        <div 
            class="{{ $colorClass }} h-2 rounded-full transition-all duration-300 ease-in-out relative overflow-hidden" 
            style="width: {{ min($percentage, 100) }}%"
            title="{{ number_format($current, 0) }}L / {{ number_format($max, 0) }}L"
        >
            <!-- Animated fuel flow effect -->
            @if($percentage > 0)
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent transform -translate-x-full animate-pulse"></div>
            @endif
        </div>
    </div>
    
    <div class="flex justify-between mt-1">
        <span class="text-xs text-gray-500">
            {{ number_format($current, 0) }}L
        </span>
        <span class="text-xs text-gray-500">
            {{ number_format($max, 0) }}L
        </span>
    </div>
    
    <!-- Maintenance warning if overdue -->
    @if($record->last_maintenance && $record->last_maintenance->diffInDays(now()) > 60)
        <div class="mt-1">
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-3 w-3 mr-1" />
                Service Due
            </span>
        </div>
    @endif
</div>