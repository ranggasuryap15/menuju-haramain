<?php
/**
 * File: database/migrations/2026_10_01_150000_add_role_and_phone_to_users_table.php
 * Tujuan: Menambahkan kolom role dan nomor telepon pada tabel users
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Modifikasi skema tabel users (DDL ALTER TABLE)
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('jamaah')->after('email')->index();
            $table->string('phone', 30)->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone']);
        });
    }
};

