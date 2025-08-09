<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command untuk membuat migration ini:
 * php artisan make:migration create_fuel_transactions_table
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
        Schema::create('fuel_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->unique(); // TRX20240809001
            $table->enum('transaction_type', [
                'storage_to_unit',      // Storage langsung ke Unit
                'storage_to_truck',     // Storage ke Fuel Truck
                'truck_to_unit',        // Fuel Truck ke Unit
                'vendor_to_storage'     // Vendor ke Storage (supply)
            ]);
            
            // Source relationships (nullable karena bisa dari vendor)
            $table->foreignId('source_storage_id')->nullable()
                  ->constrained('fuel_storages')->onDelete('restrict');
            $table->foreignId('source_truck_id')->nullable()
                  ->constrained('fuel_trucks')->onDelete('restrict');
            
            // Destination relationships (nullable tergantung jenis transaksi)
            $table->foreignId('destination_storage_id')->nullable()
                  ->constrained('fuel_storages')->onDelete('restrict');
            $table->foreignId('destination_truck_id')->nullable()
                  ->constrained('fuel_trucks')->onDelete('restrict');
            $table->foreignId('unit_id')->nullable()
                  ->constrained('units')->onDelete('restrict');
            
            // Transaction details
            $table->decimal('fuel_amount', 10, 2); // Jumlah bahan bakar (liter)
            $table->decimal('unit_km', 12, 2)->nullable(); // KM unit saat pengisian
            $table->decimal('unit_hm', 12, 2)->nullable(); // HM unit saat pengisian
            $table->timestamp('transaction_date'); // Tanggal transaksi
            $table->text('notes')->nullable(); // Catatan tambahan
            
            // Approval system
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()
                  ->constrained('users')->onDelete('restrict');
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_approved')->default(false);
            
            $table->timestamps();
            $table->softDeletes();

            // Indexes untuk performa query
            $table->index(['transaction_type', 'transaction_date']);
            $table->index(['unit_id', 'transaction_date']);
            $table->index(['source_storage_id', 'transaction_date']);
            $table->index(['destination_storage_id', 'transaction_date']);
            $table->index(['source_truck_id', 'transaction_date']);
            $table->index(['destination_truck_id', 'transaction_date']);
            $table->index(['is_approved', 'created_at']);
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_transactions');
    }
};