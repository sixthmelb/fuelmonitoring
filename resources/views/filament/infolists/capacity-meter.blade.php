{{-- 
File: resources/views/filament/infolists/capacity-meter.blade.php

Command untuk membuat view ini:
mkdir -p resources/views/filament/infolists
touch resources/views/filament/infolists/capacity-meter.blade.php

Interactive capacity meter untuk detail view
--}}

@php
    $record = $getRecord();
    $percentage = $record->capacity_percentage;
    $current = $record->current_capacity;
    $max = $record->max_capacity;
    $threshold = $record->min_threshold;
    $thresholdPercentage = $max > 0 ? ($threshold / $max) * 100 : 0;
    $isLow = $record->isBelowThreshold();
    
    $statusColor = match(true) {
        $percentage >= 80 => 'success',
        $percentage >= 50 => 'warning', 
        $percentage >= 20 => 'danger',
        default => 'gray'
    };
    
    $statusText = match(true) {
        $percentage >= 80 => 'Optimal Level',
        $percentage >= 50 => 'Good Level',
        $percentage >= 20 => 'Low Level', 
        default => 'Critical Level'
    };
    
    $barColor = match(true) {
        $percentage >= 80 => 'bg-green-500',
        $percentage >= 50 => 'bg-yellow-500',
        $percentage >= 20 => 'bg-orange-500',
        default => 'bg-red-500'
    };
@endphp

<div class="p-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            Capacity Status
        </h3>
        <div class="flex items-center space-x-2">
            @if($statusColor === 'success')
                <x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5 text-green-500" />
            @elseif($statusColor === 'warning')
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 text-yellow-500" />
            @elseif($statusColor === 'danger')
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 text-orange-500" />
            @else
                <x-filament::icon icon="heroicon-m-x-circle" class="h-5 w-5 text-red-500" />
            @endif
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $statusText }}
            </span>
        </div>
    </div>
    
    <!-- Large Capacity Display -->
    <div class="text-center mb-6">
        <div class="text-4xl font-bold text-gray-900 dark:text-white mb-2">
            {{ number_format($percentage, 1) }}%
        </div>
        <div class="text-lg text-gray-600 dark:text-gray-400">
            {{ number_format($current, 0) }} L of {{ number_format($max, 0) }} L
        </div>
    </div>
    
    <!-- Visual Progress Bar -->
    <div class="relative mb-6">
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-8">
            <!-- Main capacity bar -->
            <div 
                class="{{ $barColor }} h-8 rounded-full transition-all duration-500 ease-in-out relative overflow-hidden" 
                style="width: {{ min($percentage, 100) }}%"
            >
                <!-- Animated gradient overlay -->
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent transform translate-x-[-100%] animate-pulse"></div>
            </div>
            
            <!-- Threshold indicator -->
            @if($thresholdPercentage > 0 && $thresholdPercentage < 100)
                <div 
                    class="absolute top-0 w-1 h-8 bg-red-600 dark:bg-red-400" 
                    style="left: {{ $thresholdPercentage }}%"
                    title="Minimum threshold: {{ number_format($threshold, 0) }}L"
                >
                    <div class="absolute -top-2 left-1/2 transform -translate-x-1/2">
                        <x-filament::icon icon="heroicon-m-flag" class="h-4 w-4 text-red-600 dark:text-red-400" />
                    </div>
                </div>
            @endif
        </div>
        
        <!-- Scale markers -->
        <div class="flex justify-between mt-2 text-xs text-gray-500 dark:text-gray-400">
            <span>0%</span>
            <span>25%</span>
            <span>50%</span>
            <span>75%</span>
            <span>100%</span>
        </div>
    </div>
    
    <!-- Capacity Details Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Current</div>
            <div class="text-lg font-semibold text-green-600 dark:text-green-400">
                {{ number_format($current, 0) }}L
            </div>
        </div>
        
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Available</div>
            <div class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                {{ number_format($record->available_capacity, 0) }}L
            </div>
        </div>
        
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Threshold</div>
            <div class="text-lg font-semibold text-yellow-600 dark:text-yellow-400">
                {{ number_format($threshold, 0) }}L
            </div>
        </div>
        
        <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Maximum</div>
            <div class="text-lg font-semibold text-gray-600 dark:text-gray-400">
                {{ number_format($max, 0) }}L
            </div>
        </div>
    </div>
    
    @if($isLow)
        <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="flex items-center">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 text-red-500 mr-2" />
                <div class="text-red-700 dark:text-red-400 font-medium">
                    Alert: Storage is below minimum threshold!
                </div>
            </div>
            <div class="text-red-600 dark:text-red-500 text-sm mt-1">
                Consider refilling soon to avoid operational disruption.
            </div>
        </div>
    @endif
</div>