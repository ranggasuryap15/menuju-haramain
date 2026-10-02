<?php

/**
 * Migration: Menambahkan kolom whatsapp_group_url pada tabel kloters.
 *
 * Caller/Context: Database Migration (php artisan migrate)
 * Dependensi: Illuminate\Database\Migrations\Migration, Illuminate\Database\Schema\Blueprint
 * Fungsi:
 * - up(): Menambahkan kolom whatsapp_group_url nullable string (255) setelah description
 * - down(): Menghapus kolom whatsapp_group_url
 * Side Effect: Schema alter table kloters (DB I/O)
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
        Schema::table('kloters', function (Blueprint $table) {
            $table->string('whatsapp_group_url', 255)->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kloters', function (Blueprint $table) {
            $table->dropColumn('whatsapp_group_url');
        });
    }
};
