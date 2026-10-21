<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Izinkan request hanya jika role user termasuk salah satu role yang diminta route.
     * Beberapa role dipisah koma, mis. ->middleware('role:admin,koordinator,staf').
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // UserRole::from() melempar error jika nama role di route salah ketik,
        // sehingga kesalahan konfigurasi langsung ketahuan saat development.
        $allowed = array_map(fn (string $role) => UserRole::from(trim($role)), $roles);

        $user = $request->user();

        if (! $user || ! $user->hasRole(...$allowed)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
