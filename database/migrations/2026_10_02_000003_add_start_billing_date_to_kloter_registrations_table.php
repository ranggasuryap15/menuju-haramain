<?php

/**
 * File: database/migrations/2026_10_02_000003_add_start_billing_date_to_kloter_registrations_table.php
 * Tujuan: Menambahkan kolom start_billing_date untuk mencatat awal bulan penagihan peserta (null = ditagih sejak awal kloter)
 * Dipakai Oleh: Artisan migrate, KloterRegistration model, BillingService
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint, Illuminate\Support\Facades\Schema
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Skema DDL tabel kloter_registrations bertambah kolom start_billing_date
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kloter_registrations', function (Blueprint $table) {
            $table->date('start_billing_date')
                ->nullable()
                ->after('status')
                ->comment('Bulan mulai penagihan peserta kloter. Jika null, otomatis ditagih sejak awal kloter (start_date).');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kloter_registrations', function (Blueprint $table) {
            $table->dropColumn('start_billing_date');
        });
    }
};
