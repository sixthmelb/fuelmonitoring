<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Command untuk membuat migration ini:
 * php artisan make:migration fix_fuel_transactions_created_by_field
 * 
 * Migration untuk memperbaiki field created_by yang tidak nullable
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, check if there are any records without created_by
        $recordsWithoutCreatedBy = DB::table('fuel_transactions')
            ->whereNull('created_by')
            ->count();

        if ($recordsWithoutCreatedBy > 0) {
            // Get the first available user (preferably superadmin)
            $defaultUser = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', 'superadmin')
                ->where('users.is_active', true)
                ->select('users.id')
                ->first();

            // If no superadmin, get any active user
            if (!$defaultUser) {
                $defaultUser = DB::table('users')
                    ->where('is_active', true)
                    ->select('id')
                    ->first();
            }

            // If still no user, create a system user
            if (!$defaultUser) {
                $systemUserId = DB::table('users')->insertGetId([
                    'name' => 'System User',
                    'email' => 'system@fuelmonitor.local',
                    'password' => bcrypt('system'),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $defaultUserId = $systemUserId;
            } else {
                $defaultUserId = $defaultUser->id;
            }

            // Update existing records
            DB::table('fuel_transactions')
                ->whereNull('created_by')
                ->update(['created_by' => $defaultUserId]);
        }

        // Now make the field NOT NULL
        Schema::table('fuel_transactions', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_transactions', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
        });
    }
};