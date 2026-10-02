<?php
/**
 * File: database/migrations/2026_10_02_000001_create_kloter_bank_account_table.php
 * Tujuan: Membuat tabel pivot kloter_bank_account untuk relasi many-to-many antara kloter dan rekening bank penampung
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Membuat tabel pivot kloter_bank_account dengan foreign key dan unique composite index
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
        Schema::create('kloter_bank_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kloter_id')->constrained('kloters')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kloter_id', 'bank_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kloter_bank_account');
    }
};
