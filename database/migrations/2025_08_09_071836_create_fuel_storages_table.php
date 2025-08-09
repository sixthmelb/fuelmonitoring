<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command untuk membuat migration ini:
 * php artisan make:migration create_fuel_storages_table
 * 
 * Untuk menjalankan migration:
 * php artisan migrate
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fuel_storages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama storage: Storage A, Storage B
            $table->string('code')->unique(); // Kode: ST-001, ST-002
            $table->string('location'); // Lokasi storage
            $table->decimal('max_capacity', 10, 2); // Kapasitas maksimum (liter)
            $table->decimal('current_capacity', 10, 2)->default(0); // Kapasitas saat ini
            $table->decimal('min_threshold', 10, 2)->default(0); // Batas minimum untuk alert
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Index untuk performa query
            $table->index(['is_active', 'created_at']);
            $table->index('current_capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_storages');
    }
};