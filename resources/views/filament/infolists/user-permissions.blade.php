{{-- 
File: resources/views/filament/infolists/user-permissions.blade.php

Command untuk membuat view ini:
mkdir -p resources/views/filament/infolists
touch resources/views/filament/infolists/user-permissions.blade.php

User permissions summary display
--}}

@php
    $record = $getRecord();
    $roles = $record->roles;
    
    // Key permissions to highlight
    $keyPermissions = [
        'approve_fuel_transaction' => 'Approve Transactions',
        'approve_approval_request' => 'Approve Requests',
        'delete_fuel_transaction' => 'Delete Transactions',
        'edit_fuel_storage' => 'Edit Storage',
        'view_reports' => 'View Reports',
        'edit_users' => 'Manage Users',
    ];
@endphp

<div class="space-y-4">
    @if($roles->isNotEmpty())
        @foreach($roles as $role)
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-gray-900 dark:text-white capitalize">
                        {{ $role->name }} Role
                    </h4>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ match($role->name) {
                        'superadmin' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                        'manager' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                        'staff' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
                    } }}">
                        {{ $role->permissions->count() }} permissions
                    </span>
                </div>
                
                @if($role->name === 'superadmin')
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex items-center">
                            <x-filament::icon icon="heroicon-m-star" class="h-4 w-4 text-yellow-500 mr-2" />
                            Full system access with all permissions
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($keyPermissions as $permission => $label)
                            @php
                                $hasPermission = $role->permissions->where('name', $permission)->isNotEmpty();
                            @endphp
                            <div class="flex items-center space-x-2">
                                @if($hasPermission)
                                    <x-filament::icon icon="heroicon-m-check" class="h-4 w-4 text-green-500" />
                                    <span class="text-sm text-green-700 dark:text-green-400">{{ $label }}</span>
                                @else
                                    <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4 text-gray-400" />
                                    <span class="text-sm text-gray-500 dark:text-gray-500">{{ $label }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="text-center py-6">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-12 w-12 text-yellow-500 mx-auto mb-3" />
            <p class="text-gray-500 dark:text-gray-400">No roles assigned</p>
            <p class="text-sm text-gray-400 dark:text-gray-500">This user has no permissions</p>
        </div>
    @endif
</div>