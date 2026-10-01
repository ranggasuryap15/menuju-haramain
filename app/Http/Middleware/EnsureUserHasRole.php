<?php
/**
 * File: app/Http/Middleware/EnsureUserHasRole.php
 * Tujuan: Middleware otorisasi untuk membatasi akses rute berdasarkan role pengguna
 * Dipakai Oleh: routes/web.php
 * Dependensi Utama: Illuminate\Http\Request, Symfony\Component\HttpFoundation\Response
 * Daftar Fungsi Utama: handle()
 * Side Effect: Redirect atau abort 403 jika role tidak sesuai
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki hak akses untuk halaman ini.');
        }

        return $next($request);
    }
}

