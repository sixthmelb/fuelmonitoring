<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        Schema::table('fuel_transactions', function (Blueprint $table) {
            // Ubah created_by menjadi nullable terlebih dahulu
            $table->foreignId('created_by')->nullable()->change();
        });

        // Update existing records yang tidak punya created_by
        DB::table('fuel_transactions')
            ->whereNull('created_by')
            ->update(['created_by' => 1]); // Assuming user ID 1 exists (superadmin)

        // Kemudian buat created_by required lagi
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