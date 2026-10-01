<?php
/**
 * File: app/Providers/AppServiceProvider.php
 * Tujuan: Bootstrap layanan aplikasi global dan view composer notifikasi untuk layout utama serta views aplikasi
 * Dipakai Oleh: Laravel Framework Bootstrap
 * Dependensi Utama: App\Services\NotificationService, Illuminate\Support\Facades\View, Illuminate\Support\Facades\Auth
 * Daftar Fungsi Utama: boot(), register()
 * Side Effect: Menyediakan variabel $notificationsData ke view layouts.app, admin.*, dan jamaah.*
 */

namespace App\Providers;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Bagikan data notifikasi ke layout utama dan views portal bila pengguna terotentikasi
        View::composer(['layouts.app', 'admin.*', 'jamaah.*'], function ($view) {
            if (Auth::check()) {
                $notificationService = app(NotificationService::class);
                $view->with('notificationsData', $notificationService->getNotificationsForUser(Auth::user()));
            }
        });
    }
}
