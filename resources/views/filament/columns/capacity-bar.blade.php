

@php
    $record = $getRecord();
    $percentage = $record->capacity_percentage;
    $current = $record->current_capacity;
    $max = $record->max_capacity;
    $isLow = $record->isBelowThreshold();
    
    $colorClass = match(true) {
        $percentage >= 80 => 'bg-green-500',
        $percentage >= 50 => 'bg-yellow-500', 
        $percentage >= 20 => 'bg-orange-500',
        default => 'bg-red-500'
    };
    
    $textColor = match(true) {
        $percentage >= 80 => 'text-green-700',
        $percentage >= 50 => 'text-yellow-700',
        $percentage >= 20 => 'text-orange-700', 
        default => 'text-red-700'
    };
@endphp

<div class="w-full">
    <div class="flex items-center justify-between mb-1">
        <span class="text-xs font-medium {{ $textColor }}">
            {{ number_format($percentage, 1) }}%
        </span>
        @if($isLow)
            <x-filament::icon 
                icon="heroicon-m-exclamation-triangle" 
                class="h-4 w-4 text-red-500"
                title="Below threshold!"
            />
        @endif
    </div>
    
    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
        <div 
            class="{{ $colorClass }} h-2.5 rounded-full transition-all duration-300 ease-in-out" 
            style="width: {{ min($percentage, 100) }}%"
            title="{{ number_format($current, 0) }}L / {{ number_format($max, 0) }}L"
        ></div>
    </div>
    
    <div class="flex justify-between mt-1">
        <span class="text-xs text-gray-500">
            {{ number_format($current, 0) }}L
        </span>
        <span class="text-xs text-gray-500">
            {{ number_format($max, 0) }}L
        </span>
    </div>
</div>