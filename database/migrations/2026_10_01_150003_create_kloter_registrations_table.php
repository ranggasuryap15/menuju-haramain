<?php
/**
 * File: database/migrations/2026_10_01_150003_create_kloter_registrations_table.php
 * Tujuan: Membuat tabel pendaftaran akun user ke kloter tertentu
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Membuat tabel kloter_registrations dengan foreign key kloters dan users
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
        Schema::create('kloter_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kloter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_pax')->default(1)->comment('Jumlah orang yang didaftarkan');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamps();

            $table->unique(['kloter_id', 'user_id']);
            $table->index(['status', 'kloter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kloter_registrations');
    }
};

