{{-- 
File: resources/views/filament/columns/unit-metrics.blade.php

Command untuk membuat view ini:
touch resources/views/filament/columns/unit-metrics.blade.php

Unit metrics display untuk table
--}}

@php
    $record = $getRecord();
    $showKm = in_array($record->type, [\App\Models\Unit::TYPE_DUMP_TRUCK, \App\Models\Unit::TYPE_LOADER]);
    
    // Calculate service intervals
    $kmSinceService = $record->current_km && $record->last_service_km ? 
        $record->current_km - $record->last_service_km : null;
    $hmSinceService = $record->current_hm && $record->last_service_hm ? 
        $record->current_hm - $record->last_service_hm : null;
        
    $kmServiceDue = $kmSinceService && $kmSinceService > 5000;
    $hmServiceDue = $hmSinceService && $hmSinceService > 500;
@endphp

<div class="space-y-2 text-sm">
    @if($showKm && $record->current_km > 0)
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-1">
                <x-filament::icon icon="heroicon-m-map" class="h-3 w-3 text-gray-400" />
                <span class="text-xs text-gray-600 dark:text-gray-400">KM:</span>
            </div>
            <div class="text-right">
                <div class="font-medium">{{ number_format($record->current_km, 0) }}</div>
                @if($kmSinceService)
                    <div class="text-xs {{ $kmServiceDue ? 'text-red-600' : 'text-gray-500' }}">
                        +{{ number_format($kmSinceService, 0) }} since service
                        @if($kmServiceDue)
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-3 w-3 inline ml-1" />
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
    
    @if($record->current_hm > 0)
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-1">
                <x-filament::icon icon="heroicon-m-clock" class="h-3 w-3 text-gray-400" />
                <span class="text-xs text-gray-600 dark:text-gray-400">HM:</span>
            </div>
            <div class="text-right">
                <div class="font-medium">{{ number_format($record->current_hm, 1) }}</div>
                @if($hmSinceService)
                    <div class="text-xs {{ $hmServiceDue ? 'text-red-600' : 'text-gray-500' }}">
                        +{{ number_format($hmSinceService, 1) }} since service
                        @if($hmServiceDue)
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-3 w-3 inline ml-1" />
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
    
    @if(!$showKm && $record->current_hm == 0)
        <div class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">
            No metrics recorded
        </div>
    @endif
    
    @if($record->location)
        <div class="flex items-center space-x-1 pt-1 border-t border-gray-200 dark:border-gray-700">
            <x-filament::icon icon="heroicon-m-map-pin" class="h-3 w-3 text-gray-400" />
            <span class="text-xs text-gray-500 dark:text-gray-500 truncate">
                {{ $record->location }}
            </span>
        </div>
    @endif
</div>