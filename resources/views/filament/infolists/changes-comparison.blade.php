{{-- 
File: resources/views/filament/infolists/changes-comparison.blade.php

Command untuk membuat view ini:
touch resources/views/filament/infolists/changes-comparison.blade.php

Changes comparison untuk approval requests
--}}

@php
    $record = $getRecord();
    $originalData = $record->original_data ?? [];
    $newData = $record->new_data ?? [];
    $changes = $record->changes_summary;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
    @if($record->request_type === \App\Models\ApprovalRequest::TYPE_DELETE)
        <div class="p-6">
            <div class="flex items-center justify-center space-x-3 text-red-600 dark:text-red-400">
                <x-filament::icon icon="heroicon-o-trash" class="h-8 w-8" />
                <div class="text-center">
                    <div class="text-lg font-semibold">Delete Transaction Request</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        This request will permanently delete the transaction
                    </div>
                </div>
            </div>
            
            @if($originalData)
                <div class="mt-6 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                    <div class="text-sm font-medium text-red-800 dark:text-red-200 mb-3">
                        Transaction to be deleted:
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Code:</span>
                            <span class="font-medium ml-2">{{ $originalData['transaction_code'] ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Amount:</span>
                            <span class="font-medium ml-2">{{ number_format($originalData['fuel_amount'] ?? 0, 0) }} L</span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Type:</span>
                            <span class="font-medium ml-2">{{ ucfirst(str_replace('_', ' ', $originalData['transaction_type'] ?? '')) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-600 dark:text-gray-400">Date:</span>
                            <span class="font-medium ml-2">
                                {{ isset($originalData['transaction_date']) ? \Carbon\Carbon::parse($originalData['transaction_date'])->format('M d, Y H:i') : 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @elseif($changes && count($changes) > 0)
        <div class="p-6">
            <div class="flex items-center space-x-3 mb-6">
                <x-filament::icon icon="heroicon-o-pencil-square" class="h-6 w-6 text-yellow-600" />
                <div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-white">
                        Proposed Changes
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        {{ count($changes) }} field(s) will be modified
                    </div>
                </div>
            </div>
            
            <div class="space-y-4">
                @foreach($changes as $change)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                            <div class="font-medium text-gray-900 dark:text-white capitalize">
                                {{ str_replace('_', ' ', $change['field']) }}
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                        Current Value
                                    </div>
                                    <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded">
                                        <div class="text-sm text-red-800 dark:text-red-200 font-mono">
                                            {{ $this->formatValue($change['from']) }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                        Proposed Value
                                    </div>
                                    <div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded">
                                        <div class="text-sm text-green-800 dark:text-green-200 font-mono">
                                            {{ $this->formatValue($change['to']) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="p-6 text-center">
            <x-filament::icon icon="heroicon-o-document-text" class="h-12 w-12 text-gray-400 mx-auto mb-4" />
            <div class="text-gray-500 dark:text-gray-400">
                No changes to display
            </div>
        </div>
    @endif
</div>

@php
    // Helper method to format values for display
    if (!function_exists('formatValue')) {
        function formatValue($value) {
            if (is_null($value)) {
                return 'Not set';
            }
            
            if (is_bool($value)) {
                return $value ? 'Yes' : 'No';
            }
            
            if (is_numeric($value)) {
                return number_format($value, 2);
            }
            
            if (is_string($value) && strlen($value) > 50) {
                return substr($value, 0, 50) . '...';
            }
            
            return (string) $value;
        }
    }
@endphp