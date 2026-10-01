<?php
/**
 * File: bootstrap/app.php
 * Tujuan: Bootstrap framework Laravel 11, routing, alias middleware, redirect guest/user, dan exception handling
 * Dipakai Oleh: public/index.php, artisan
 * Dependensi Utama: Illuminate\Foundation\Application, App\Http\Middleware\EnsureUserHasRole
 * Daftar Alias Middleware: role
 * Side Effect: Konfigurasi runtime kernel aplikasi dan redirect otentikasi
 */

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();

