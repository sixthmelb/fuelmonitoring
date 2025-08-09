<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Command untuk membuat model ini:
 * php artisan make:model User
 * 
 * Note: User model sudah ada di Laravel, ini adalah modifikasi
 * Tambahkan package permission: composer require spatie/laravel-permission
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'employee_id',
        'phone',
        'department',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user is superadmin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /**
     * Check if user is manager
     */
    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    /**
     * Check if user is staff
     */
    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    /**
     * Get fuel transactions created by this user
     */
    public function fuelTransactions()
    {
        return $this->hasMany(FuelTransaction::class, 'created_by');
    }

    /**
     * Get approval requests created by this user
     */
    public function createdApprovalRequests()
    {
        return $this->hasMany(ApprovalRequest::class, 'requested_by');
    }

    /**
     * Get approval requests approved by this user
     */
    public function approvedRequests()
    {
        return $this->hasMany(ApprovalRequest::class, 'approved_by');
    }
}