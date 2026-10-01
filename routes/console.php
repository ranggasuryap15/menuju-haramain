<?php

/**
 * File: routes/console.php
 * Tujuan: Definisi artisan commands dan jadwal cron penjadwalan (Scheduler) aplikasi
 * Dipakai Oleh: php artisan schedule:run / Laravel Scheduler runner
 * Dependensi Utama: Illuminate\Support\Facades\Schedule, App\Console\Commands\GenerateMonthlyInvoicesCommand
 * Daftar Schedule: billing:generate-monthly dieksekusi otomatis setiap tanggal 1 pukul 00:01 WIB
 * Side Effect: Trigger background billing task otomatis
 */

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Otomatis terbit tagihan tanggal 1 setiap bulan pukul 00:01
Schedule::command('billing:generate-monthly')->monthlyOn(1, '00:01');
