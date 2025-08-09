{{-- 
File: resources/views/filament/infolists/user-activity.blade.php

Command untuk membuat view ini:
touch resources/views/filament/infolists/user-activity.blade.php

User activity summary display
--}}

@php
    $record = $getRecord();
    
    // Get user's fuel transactions
    $totalTransactions = $record->fuelTransactions()->count();
    $thisMonthTransactions = $record->fuelTransactions()
        ->whereMonth('created_at', now()->month)
        ->count();
    $totalFuelHandled = $record->fuelTransactions()
        ->sum('fuel_amount');
    
    // Get approval requests
    $totalApprovalRequests = $record->createdApprovalRequests()->count();
    $pendingRequests = $record->createdApprovalRequests()
        ->where('status', \App\Models\ApprovalRequest::STATUS_PENDING)
        ->count();
    
    // Get approved requests (if manager/superadmin)
    $approvedRequests = $record->approvedRequests()->count();
    
    // Recent activity
    $recentTransactions = $record->fuelTransactions()
        ->with(['unit', 'sourceStorage', 'destinationStorage'])
        ->latest()
        ->take(5)
        ->get();
@endphp

<div class="space-y-6">
    <!-- Activity Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ number_format($totalTransactions) }}
            </div>
            <div class="text-sm text-blue-600 dark:text-blue-400">
                Total Transactions
            </div>
        </div>
        
        <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ number_format($thisMonthTransactions) }}
            </div>
            <div class="text-sm text-green-600 dark:text-green-400">
                This Month
            </div>
        </div>
        
        <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                {{ number_format($totalFuelHandled, 0) }}L
            </div>
            <div class="text-sm text-purple-600 dark:text-purple-400">
                Fuel Handled
            </div>
        </div>
        
        <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
            <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                {{ number_format($totalApprovalRequests) }}
            </div>
            <div class="text-sm text-yellow-600 dark:text-yellow-400">
                Approval Requests
            </div>
        </div>
    </div>
    
    <!-- Manager Stats (if applicable) -->
    @if($record->hasAnyRole(['manager', 'superadmin']) && $approvedRequests > 0)
        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white">Management Activity</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Approval decisions made</p>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ number_format($approvedRequests) }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Total Approved
                    </div>
                </div>
            </div>
        </div>
    @endif
    
    <!-- Recent Transactions -->
    @if($recentTransactions->isNotEmpty())
        <div>
            <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Recent Transactions</h4>
            <div class="space-y-2">
                @foreach($recentTransactions as $transaction)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                @if($transaction->transaction_type === \App\Models\FuelTransaction::TYPE_VENDOR_TO_STORAGE)
                                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                @else
                                    <div class="w-2 h-2 bg-blue-400 rounded-full"></div>
                                @endif
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $transaction->transaction_code }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $transaction->created_at->format('M d, Y H:i') }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ number_format($transaction->fuel_amount, 0) }}L
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    
    <!-- Pending Requests Alert -->
    @if($pendingRequests > 0)
        <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
            <div class="flex items-center">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 text-yellow-500 mr-2" />
                <div class="text-yellow-700 dark:text-yellow-400">
                    <div class="font-medium">{{ $pendingRequests }} Pending Approval Request(s)</div>
                    <div class="text-sm">This user has approval requests awaiting manager decision.</div>
                </div>
            </div>
        </div>
    @endif
    
    @if($totalTransactions === 0)
        <div class="text-center py-8">
            <x-filament::icon icon="heroicon-o-document-text" class="h-12 w-12 text-gray-400 mx-auto mb-3" />
            <p class="text-gray-500 dark:text-gray-400">No activity yet</p>
            <p class="text-sm text-gray-400 dark:text-gray-500">User hasn't created any transactions</p>
        </div>
    @endif
</div>