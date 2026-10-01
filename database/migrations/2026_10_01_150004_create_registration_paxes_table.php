<?php
/**
 * File: database/migrations/2026_10_01_150004_create_registration_paxes_table.php
 * Tujuan: Membuat tabel relasi anggota keluarga yang diikutsertakan dalam pendaftaran kloter
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Membuat tabel registration_paxes dengan foreign key kloter_registrations dan family_members
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
        Schema::create('registration_paxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('kloter_registrations')->cascadeOnDelete();
            $table->foreignId('family_member_id')->constrained('family_members')->cascadeOnDelete();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->timestamps();

            $table->unique(['registration_id', 'family_member_id']);
            $table->index(['registration_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_paxes');
    }
};

