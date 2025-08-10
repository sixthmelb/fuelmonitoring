<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command untuk membuat migration ini:
 * php artisan make:migration add_used_at_to_approval_requests_table --table=approval_requests
 * 
 * Migration untuk menambahkan field used_at pada approval_requests
 * Untuk track kapan approved edit request sudah digunakan
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            // Check if column doesn't exist already
            if (!Schema::hasColumn('approval_requests', 'used_at')) {
                $table->timestamp('used_at')->nullable()->after('approved_at');
                $table->index(['fuel_transaction_id', 'used_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            if (Schema::hasColumn('approval_requests', 'used_at')) {
                $table->dropIndex(['fuel_transaction_id', 'used_at']);
                $table->dropColumn('used_at');
            }
        });
    }
};