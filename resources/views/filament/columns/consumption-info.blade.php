{{-- 
File: resources/views/filament/columns/consumption-info.blade.php

Command untuk membuat view ini:
touch resources/views/filament/columns/consumption-info.blade.php

Consumption information display untuk table
--}}

@php
    $record = $getRecord();
    $consumption = $record->fuel_consumption;
@endphp

<div class="text-sm">
    @if($consumption && $record->unit)
        <div class="space-y-1">
            @if(isset($consumption['per_km']))
                <div class="flex items-center space-x-1">
                    <x-filament::icon icon="heroicon-m-map" class="h-3 w-3 text-gray-400" />
                    <span class="text-xs text-gray-600 dark:text-gray-400">
                        {{ number_format($consumption['per_km'], 2) }} L/KM
                    </span>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-500">
                    {{ number_format($consumption['km_diff'], 0) }} KM driven
                </div>
            @endif
            
            @if(isset($consumption['per_hm']))
                <div class="flex items-center space-x-1">
                    <x-filament::icon icon="heroicon-m-clock" class="h-3 w-3 text-gray-400" />
                    <span class="text-xs text-gray-600 dark:text-gray-400">
                        {{ number_format($consumption['per_hm'], 2) }} L/HM
                    </span>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-500">
                    {{ number_format($consumption['hm_diff'], 1) }} HM operated
                </div>
            @endif
        </div>
    @else
        <div class="text-xs text-gray-400 dark:text-gray-500">
            @if($record->unit)
                No prev. data
            @else
                N/A
            @endif
        </div>
    @endif
</div>