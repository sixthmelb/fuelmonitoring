<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Command untuk membuat seeder ini:
 * php artisan make:seeder RolesPermissionsSeeder
 * 
 * Jangan lupa install package spatie permission:
 * composer require spatie/laravel-permission
 * php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
 * php artisan migrate
 * 
 * Untuk menjalankan seeder:
 * php artisan db:seed --class=RolesPermissionsSeeder
 * 
 * Atau tambahkan ke DatabaseSeeder.php:
 * $this->call(RolesPermissionsSeeder::class);
 */
class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

// Tambahkan permissions ini di array $permissions:
        $permissions = [
            // Fuel Storage Permissions
            'view_fuel_storage',
            'create_fuel_storage',
            'edit_fuel_storage',
            'delete_fuel_storage',

            // Fuel Truck Permissions
            'view_fuel_truck',
            'create_fuel_truck',
            'edit_fuel_truck',
            'delete_fuel_truck',

            // Unit Permissions
            'view_unit',
            'create_unit',
            'edit_unit',
            'delete_unit',

            // Fuel Transaction Permissions
            'view_fuel_transaction',
            'create_fuel_transaction',
            'edit_fuel_transaction',
            'delete_fuel_transaction',
            'approve_fuel_transaction',
            
            // BARU: Request Permissions untuk Staff
            'request_edit_fuel_transaction',
            'request_delete_fuel_transaction',

            // Approval Request Permissions
            'view_approval_request',
            'approve_approval_request',
            'reject_approval_request',

            // Report Permissions
            'view_reports',
            'export_reports',
            'view_dashboard',

            // User Management Permissions
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // System Settings
            'view_settings',
            'edit_settings',
        ];
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // 1. Superadmin Role - Has all permissions
        $superadminRole = Role::create(['name' => 'superadmin']);
        $superadminRole->givePermissionTo(Permission::all());

        // 2. Manager Role - Can view reports, approve requests, manage data
        $managerRole = Role::create(['name' => 'manager']);
        $managerRole->givePermissionTo([
            // View all data
            'view_fuel_storage',
            'view_fuel_truck',
            'view_unit',
            'view_fuel_transaction',
            'view_approval_request',
            
            // Manage master data
            'create_fuel_storage',
            'edit_fuel_storage',
            'create_fuel_truck',
            'edit_fuel_truck',
            'create_unit',
            'edit_unit',
            
            // Transaction management
            'create_fuel_transaction',
            'edit_fuel_transaction',
            'approve_fuel_transaction',
            
            // Approval system
            'approve_approval_request',
            'reject_approval_request',
            
            // Reports
            'view_reports',
            'export_reports',
            'view_dashboard',
        ]);

        // 3. Staff Role - Updated dengan request permissions
        $staffRole = Role::create(['name' => 'staff']);
        $staffRole->givePermissionTo([
            // View basic data (read-only)
            'view_fuel_storage',
            'view_fuel_truck',
            'view_unit',
            'view_fuel_transaction',
            
            // Create transactions only
            'create_fuel_transaction',
            
            // BARU: Request permissions untuk approval workflow
            'request_edit_fuel_transaction', // Request edit approval
            'request_delete_fuel_transaction', // Request delete approval
            
            // View dashboard
            'view_dashboard',
            
            // BARU: View approval requests (untuk melihat status request mereka)
            'view_approval_request',
        ]);

        // Create default users for each role
        
        // Create Superadmin
        $superadmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'superadmin@fuelmonitor.com',
            'employee_id' => 'SUP001',
            'password' => Hash::make('password123'),
            'department' => 'IT',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        // Create Manager
        $manager = User::create([
            'name' => 'Mining Operations Manager',
            'email' => 'manager@fuelmonitor.com',
            'employee_id' => 'MGR001',
            'password' => Hash::make('password123'),
            'department' => 'Operations',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $manager->assignRole('manager');

        // Create Staff
        $staff = User::create([
            'name' => 'Fuel Operator',
            'email' => 'staff@fuelmonitor.com',
            'employee_id' => 'STA001',
            'password' => Hash::make('password123'),
            'department' => 'Fuel Operations',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole('staff');

        $this->command->info('Roles and permissions created successfully!');
        $this->command->info('Default users created:');
        $this->command->info('- Superadmin: superadmin@fuelmonitor.com (password: password123)');
        $this->command->info('- Manager: manager@fuelmonitor.com (password: password123)');
        $this->command->info('- Staff: staff@fuelmonitor.com (password: password123)');
    }
}