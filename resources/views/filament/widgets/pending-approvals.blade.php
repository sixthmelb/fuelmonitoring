{{-- 
File: resources/views/filament/widgets/pending-approvals.blade.php

Alternative view-based widget untuk pending approvals jika table widget tidak diinginkan
--}}

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Pending Approval Requests
        </x-slot>
        
        <x-slot name="headerEnd">
            @if($pendingRequests->count() > 0)
                <x-filament::badge color="warning">
                    {{ $pendingRequests->count() }} pending
                </x-filament::badge>
            @endif
        </x-slot>

        @php
            $pendingRequests = \App\Models\ApprovalRequest::with(['fuelTransaction', 'requestedBy'])
                ->where('status', \App\Models\ApprovalRequest::STATUS_PENDING)
                ->latest()
                ->limit(5)
                ->get();
        @endphp

        @if($pendingRequests->count() > 0)
            <div class="space-y-4">
                @foreach($pendingRequests as $request)
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    @if($request->request_type === \App\Models\ApprovalRequest::TYPE_DELETE)
                                        <x-filament::icon icon="heroicon-o-trash" class="h-5 w-5 text-red-500" />
                                    @else
                                        <x-filament::icon icon="heroicon-o-pencil-square" class="h-5 w-5 text-yellow-500" />
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $request->fuelTransaction->transaction_code ?? 'N/A' }}
                                        </p>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $request->request_type === \App\Models\ApprovalRequest::TYPE_DELETE ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' }}">
                                            {{ ucfirst($request->request_type) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                        {{ $request->reason }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500">
                                        Requested by {{ $request->requestedBy->name }} • {{ $request->created_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        @if(auth()->user()->hasAnyRole(['manager', 'superadmin']))
                            <div class="flex items-center space-x-2 ml-4">
                                <x-filament::button
                                    color="success"
                                    size="sm"
                                    wire:click="approveRequest({{ $request->id }})"
                                    wire:confirm="Are you sure you want to approve this {{ $request->request_type }} request?"
                                >
                                    <x-filament::icon icon="heroicon-o-check" class="h-4 w-4" />
                                    Approve
                                </x-filament::button>
                                
                                <x-filament::button
                                    color="danger"
                                    size="sm"
                                    wire:click="rejectRequest({{ $request->id }})"
                                >
                                    <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                                    Reject
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endforeach
                
                @if($pendingRequests->count() >= 5)
                    <div class="text-center pt-4">
                        <x-filament::link href="{{ \App\Filament\Resources\ApprovalRequestResource::getUrl('index') }}">
                            View all approval requests →
                        </x-filament::link>
                    </div>
                @endif
            </div>
        @else
            <div class="text-center py-8">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                <p class="text-gray-500 dark:text-gray-400">No pending approval requests</p>
                <p class="text-sm text-gray-400 dark:text-gray-500">All requests have been processed</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>