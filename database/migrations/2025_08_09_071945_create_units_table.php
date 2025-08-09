<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command untuk membuat migration ini:
 * php artisan make:migration create_units_table
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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // DT-001, EX-001, dll
            $table->string('name'); // Nama unit
            $table->enum('type', [
                'dump_truck', 'excavator', 'dozer', 'loader', 
                'grader', 'compactor', 'other'
            ]); // Jenis alat berat
            $table->string('brand')->nullable(); // Caterpillar, Komatsu, dll
            $table->string('model')->nullable(); // Model kendaraan
            $table->year('year')->nullable(); // Tahun pembuatan
            $table->decimal('engine_capacity', 8, 2)->nullable(); // CC mesin
            $table->decimal('fuel_tank_capacity', 8, 2)->nullable(); // Kapasitas tangki
            $table->decimal('current_km', 12, 2)->default(0); // KM saat ini
            $table->decimal('current_hm', 12, 2)->default(0); // HM (Hour Meter) saat ini
            $table->decimal('last_service_km', 12, 2)->nullable(); // KM service terakhir
            $table->decimal('last_service_hm', 12, 2)->nullable(); // HM service terakhir
            $table->string('operator_name')->nullable(); // Nama operator
            $table->string('location')->nullable(); // Lokasi kerja saat ini
            $table->enum('status', ['active', 'maintenance', 'standby', 'out_of_service'])
                  ->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Index untuk performa query
            $table->index(['type', 'status']);
            $table->index(['is_active', 'status']);
            $table->index('current_km');
            $table->index('current_hm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};