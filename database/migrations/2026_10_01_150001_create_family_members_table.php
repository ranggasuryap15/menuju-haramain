<?php
/**
 * File: database/migrations/2026_10_01_150001_create_family_members_table.php
 * Tujuan: Membuat tabel data anggota keluarga / peserta di bawah akun user
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Membuat tabel family_members dengan foreign key ke users
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
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship', 50)->default('Kepala Keluarga');
            $table->text('identity_number')->nullable()->comment('NIK atau Nomor Paspor terenkripsi (UU PDP)');
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'full_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};

