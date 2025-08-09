<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Command untuk membuat migration ini:
 * php artisan make:migration add_columns_to_users_table --table=users
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->unique()->nullable()->after('email');
            $table->string('phone')->nullable()->after('employee_id');
            $table->string('department')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('department');
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'employee_id',
                'phone', 
                'department',
                'is_active'
            ]);
            $table->dropSoftDeletes();
        });
    }
};