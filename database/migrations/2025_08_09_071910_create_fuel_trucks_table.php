<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command untuk membuat migration ini:
 * php artisan make:migration create_fuel_trucks_table
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
        Schema::create('fuel_trucks', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama truck: Fuel Truck A
            $table->string('code')->unique(); // Kode: FT-001, FT-002
            $table->string('license_plate')->unique(); // Nomor polisi
            $table->string('driver_name'); // Nama driver
            $table->string('driver_phone')->nullable(); // Nomor HP driver
            $table->decimal('max_capacity', 10, 2); // Kapasitas maksimum (liter)
            $table->decimal('current_capacity', 10, 2)->default(0); // Kapasitas saat ini
            $table->enum('status', ['available', 'in_use', 'maintenance', 'out_of_service'])
                  ->default('available');
            $table->date('last_maintenance')->nullable(); // Tanggal maintenance terakhir
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Index untuk performa query
            $table->index(['status', 'is_active']);
            $table->index('current_capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_trucks');
    }
};