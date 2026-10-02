<?php
/**
 * File: bootstrap/app.php
 * Tujuan: Bootstrap framework Laravel 11, routing, alias middleware, redirect guest/user, dan exception handling (termasuk penanganan ramah 419 TokenMismatchException untuk PWA)
 * Dipakai Oleh: public/index.php, artisan
 * Dependensi Utama: Illuminate\Foundation\Application, App\Http\Middleware\EnsureUserHasRole, Illuminate\Session\TokenMismatchException
 * Daftar Alias Middleware: role
 * Side Effect: Konfigurasi runtime kernel aplikasi, redirect otentikasi, dan render custom exception view
 */

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(function () {
            /** @var \App\Models\User|null $user */
            $user = auth()->user();
            if ($user && $user->isStaff()) {
                return route('admin.dashboard');
            }
            return route('jamaah.dashboard');
        });

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Penanganan ramah jika terjadi 419 Page Expired / Token Mismatch di PWA atau browser
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('login')) {
                return redirect()->route('login')
                    ->with('warning', 'Sesi login telah diperbarui demi keamanan. Silakan masukkan kata sandi kembali.');
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.',
                ], 419);
            }

            return response()->view('errors.419', [], 419);
        });
    })->create();

