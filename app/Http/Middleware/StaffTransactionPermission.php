<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Command untuk membuat middleware ini:
 * php artisan make:middleware StaffTransactionPermission
 * 
 * Daftarkan di app/Http/Kernel.php:
 * protected $middlewareAliases = [
 *     'staff.transaction' => \App\Http\Middleware\StaffTransactionPermission::class,
 * ];
 * 
 * Middleware untuk membatasi akses staff ke edit transactions
 */
class StaffTransactionPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Jika user adalah staff
        if (auth()->check() && auth()->user()->hasRole('staff')) {
            
            // Block akses ke edit page untuk fuel transactions
            if ($request->is('admin/fuel-transactions/*/edit')) {
                abort(403, 'Staff users cannot edit transactions directly. Please submit an edit request.');
            }
            
            // Block direct PUT/PATCH requests to fuel transactions (API calls)
            if ($request->isMethod(['PUT', 'PATCH']) && $request->is('admin/fuel-transactions/*')) {
                abort(403, 'Staff users cannot modify transactions directly.');
            }
        }
        
        return $next($request);
    }
}