<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Command untuk membuat seeder ini:
 * php artisan make:seeder UpdateStaffPermissionsSeeder
 * 
 * Untuk menjalankan:
 * php artisan db:seed --class=UpdateStaffPermissionsSeeder
 */
class UpdateStaffPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create new permissions if not exist
        $newPermissions = [
            'request_edit_fuel_transaction',
            'request_delete_fuel_transaction',
        ];

        foreach ($newPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Update staff role permissions
        $staffRole = Role::findByName('staff');
        
        // Current permissions + new request permissions
        $staffPermissions = [
            'view_fuel_storage',
            'view_fuel_truck',
            'view_unit',
            'view_fuel_transaction',
            'create_fuel_transaction',
            'request_edit_fuel_transaction',  // NEW
            'request_delete_fuel_transaction', // NEW
            'view_dashboard',
            'view_approval_request',
        ];

        // Sync permissions (removes old, adds new)
        $staffRole->syncPermissions($staffPermissions);

        $this->command->info('Staff permissions updated successfully!');
        $this->command->info('Staff can now request edit/delete approvals');
    }
}