<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command untuk membuat migration ini:
 * php artisan make:migration create_approval_requests_table
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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_transaction_id')
                  ->constrained('fuel_transactions')->onDelete('cascade');
            $table->enum('request_type', ['edit', 'delete']); // Tipe permintaan
            $table->foreignId('requested_by')
                  ->constrained('users')->onDelete('restrict'); // Staff yang meminta
            $table->foreignId('approved_by')->nullable()
                  ->constrained('users')->onDelete('restrict'); // Manager yang approve
            $table->timestamp('approved_at')->nullable(); // Waktu approval
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending');
            $table->text('reason'); // Alasan permintaan edit/delete
            $table->json('original_data')->nullable(); // Data asli (untuk audit)
            $table->json('new_data')->nullable(); // Data baru (untuk edit)
            $table->text('rejection_reason')->nullable(); // Alasan reject
            $table->timestamps();
            $table->softDeletes();

            // Indexes untuk performa query
            $table->index(['status', 'created_at']);
            $table->index(['requested_by', 'status']);
            $table->index(['approved_by', 'status']);
            $table->index('fuel_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};