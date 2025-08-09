{{-- 
File: resources/views/filament/forms/truck-capacity-preview.blade.php

Command untuk membuat view ini:
touch resources/views/filament/forms/truck-capacity-preview.blade.php

Truck capacity preview component untuk forms
--}}

@php
    $max = $max_capacity ?? 0;
    $current = $current_capacity ?? 0;
    $status = $status ?? 'available';
    
    $percentage = $max > 0 ? ($current / $max) * 100 : 0;
    $available = $max - $current;
    
    $fuelStatus = match(true) {
        $percentage >= 80 => ['text' => 'Full', 'icon' => '🚛', 'color' => 'text-blue-600'],
        $percentage >= 50 => ['text' => 'Half Full', 'icon' => '🚚', 'color' => 'text-green-600'],
        $percentage >= 20 => ['text' => 'Low', 'icon' => '🚐', 'color' => 'text-yellow-600'],
        $percentage > 0 => ['text' => 'Very Low', 'icon' => '🚌', 'color' => 'text-orange-600'],
        default => ['text' => 'Empty', 'icon' => '🚐', 'color' => 'text-gray-600']
    };
    
    $operationalStatus = match($status) {
        'available' => ['text' => 'Available', 'color' => 'text-green-600'],
        'in_use' => ['text' => 'In Use', 'color' => 'text-blue-600'],
        'maintenance' => ['text' => 'Maintenance', 'color' => 'text-yellow-600'],
        'out_of_service' => ['text' => 'Out of Service', 'color' => 'text-red-600'],
        default => ['text' => 'Unknown', 'color' => 'text-gray-600']
    };
@endphp

<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
    @if($max > 0)
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <span class="text-lg">{{ $fuelStatus['icon'] }}</span>
                    <div>
                        <div class="font-medium {{ $fuelStatus['color'] }} dark:text-white">
                            {{ $fuelStatus['text'] }}
                        </div>
                        <div class="text-sm {{ $operationalStatus['color'] }}">
                            {{ $operationalStatus['text'] }}
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ number_format($percentage, 1) }}%
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Fuel Level
                    </div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                @php
                    $barColor = match(true) {
                        $percentage >= 80 => 'bg-blue-500',
                        $percentage >= 50 => 'bg-green-500',
                        $percentage >= 20 => 'bg-yellow-500',
                        $percentage > 0 => 'bg-orange-500',
                        default => 'bg-gray-300'
                    };
                @endphp
                <div 
                    class="{{ $barColor }} h-3 rounded-full transition-all duration-300" 
                    style="width: {{ min($percentage, 100) }}%"
                ></div>
            </div>
            
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div class="text-center">
                    <div class="font-medium text-gray-900 dark:text-white">
                        {{ number_format($current, 0) }}L
                    </div>
                    <div class="text-gray-500 dark:text-gray-400">Current</div>
                </div>
                <div class="text-center">
                    <div class="font-medium text-gray-900 dark:text-white">
                        {{ number_format($available, 0) }}L
                    </div>
                    <div class="text-gray-500 dark:text-gray-400">Available</div>
                </div>
                <div class="text-center">
                    <div class="font-medium text-gray-900 dark:text-white">
                        {{ number_format($max, 0) }}L
                    </div>
                    <div class="text-gray-500 dark:text-gray-400">Maximum</div>
                </div>
            </div>
            
            @if($status === 'maintenance')
                <div class="flex items-center justify-center space-x-2 text-yellow-600 dark:text-yellow-400">
                    <x-filament::icon icon="heroicon-m-wrench-screwdriver" class="h-4 w-4" />
                    <span class="text-sm font-medium">Under Maintenance</span>
                </div>
            @elseif($status === 'out_of_service')
                <div class="flex items-center justify-center space-x-2 text-red-600 dark:text-red-400">
                    <x-filament::icon icon="heroicon-m-x-circle" class="h-4 w-4" />
                    <span class="text-sm font-medium">Out of Service</span>
                </div>
            @elseif($percentage <= 10 && $status === 'available')
                <div class="flex items-center justify-center space-x-2 text-orange-600 dark:text-orange-400">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />
                    <span class="text-sm font-medium">Low Fuel - Needs Refill</span>
                </div>
            @endif
        </div>
    @else
        <div class="text-center text-gray-500 dark:text-gray-400">
            <div class="text-sm">Enter maximum capacity to see preview</div>
        </div>
    @endif
</div>