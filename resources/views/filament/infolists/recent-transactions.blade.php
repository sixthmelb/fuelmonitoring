{{-- 
File: resources/views/filament/infolists/recent-transactions.blade.php

Command untuk membuat view ini:
touch resources/views/filament/infolists/recent-transactions.blade.php

Recent transactions table untuk detail view
--}}

@php
    $record = $getRecord();
    
    // Get recent transactions (both incoming and outgoing)
    $recentTransactions = collect()
        ->merge(
            $record->incomingTransactions()
                ->with(['createdBy', 'sourceStorage', 'sourceTruck'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn($t) => ['transaction' => $t, 'direction' => 'in'])
        )
        ->merge(
            $record->outgoingTransactions()
                ->with(['createdBy', 'unit', 'destinationTruck'])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn($t) => ['transaction' => $t, 'direction' => 'out'])
        )
        ->sortByDesc(fn($item) => $item['transaction']->created_at)
        ->take(10);
@endphp

<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
    @if($recentTransactions->count() > 0)
        <div class="overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            From/To
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Amount
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            By
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($recentTransactions as $item)
                        @php
                            $transaction = $item['transaction'];
                            $direction = $item['direction'];
                            $isIncoming = $direction === 'in';
                            
                            $typeLabel = match($transaction->transaction_type) {
                                'vendor_to_storage' => 'Vendor Supply',
                                'storage_to_truck' => 'To Fuel Truck',
                                'storage_to_unit' => 'To Unit',
                                default => ucfirst(str_replace('_', ' ', $transaction->transaction_type))
                            };
                            
                            $fromTo = $isIncoming 
                                ? 'From: ' . ($transaction->sourceTruck ? $transaction->sourceTruck->name : 'Vendor')
                                : 'To: ' . ($transaction->unit ? $transaction->unit->name : $transaction->destinationTruck?->name);
                        @endphp
                        
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                <div>{{ $transaction->created_at->format('M d') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $transaction->created_at->format('H:i') }}
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($isIncoming)
                                        <div class="flex-shrink-0 h-2 w-2 bg-green-400 rounded-full mr-2"></div>
                                        <span class="text-sm font-medium text-green-600 dark:text-green-400">IN</span>
                                    @else
                                        <div class="flex-shrink-0 h-2 w-2 bg-red-400 rounded-full mr-2"></div>
                                        <span class="text-sm font-medium text-red-600 dark:text-red-400">OUT</span>
                                    @endif
                                    <div class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $typeLabel }}
                                    </div>
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                {{ $fromTo }}
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="text-sm font-medium {{ $isIncoming ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $isIncoming ? '+' : '-' }}{{ number_format($transaction->fuel_amount, 0) }}L
                                </div>
                            </td>
                            
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                {{ $transaction->createdBy->name }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing recent {{ $recentTransactions->count() }} transactions
                </div>
                <a 
                    href="{{ \App\Filament\Resources\FuelTransactionResource::getUrl('index', ['tableFilters[storage][value]' => $record->id]) }}" 
                    class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 font-medium"
                >
                    View all transactions →
                </a>
            </div>
        </div>
    @else
        <div class="p-8 text-center">
            <x-filament::icon icon="heroicon-o-document-text" class="h-12 w-12 text-gray-400 mx-auto mb-4" />
            <div class="text-gray-500 dark:text-gray-400 text-sm">
                No transactions found for this storage
            </div>
        </div>
    @endif
</div>
