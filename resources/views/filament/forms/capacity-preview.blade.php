{{-- 
File: resources/views/filament/forms/capacity-preview.blade.php

Command untuk membuat view ini:
mkdir -p resources/views/filament/forms
touch resources/views/filament/forms/capacity-preview.blade.php

Capacity preview component untuk forms
--}}

@php
    $max = $max_capacity ?? 0;
    $current = $current_capacity ?? 0;
    $threshold = $min_threshold ?? 0;
    
    $percentage = $max > 0 ? ($current / $max) * 100 : 0;
    $available = $max - $current;
    $belowThreshold = $current <= $threshold;
    
    $status = match(true) {
        $percentage >= 80 => ['text' => 'Optimal', 'icon' => '🟢'],
        $percentage >= 50 => ['text' => 'Good', 'icon' => '🟡'],
        $percentage >= 20 => ['text' => 'Low', 'icon' => '🟠'],
        $percentage > 0 => ['text' => 'Critical', 'icon' => '🔴'],
        default => ['text' => 'Empty', 'icon' => '⚫']
    };
@endphp

<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
    @if($max > 0)
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="text-lg">{{ $status['icon'] }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $status['text'] }}</span>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ number_format($percentage, 1) }}%
                    </div>
                </div>
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
            
            @if($belowThreshold && $threshold > 0)
                <div class="flex items-center justify-center space-x-2 text-red-600 dark:text-red-400">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />
                    <span class="text-sm font-medium">Below Threshold!</span>
                </div>
            @endif
        </div>
    @else
        <div class="text-center text-gray-500 dark:text-gray-400">
            <div class="text-sm">Enter maximum capacity to see preview</div>
        </div>
    @endif
</div>