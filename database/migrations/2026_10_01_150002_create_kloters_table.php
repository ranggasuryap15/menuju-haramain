<?php
/**
 * File: database/migrations/2026_10_01_150002_create_kloters_table.php
 * Tujuan: Membuat tabel kloter / paket periode tabungan umroh
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Membuat tabel kloters dengan index tanggal dan status
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
        Schema::create('kloters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->decimal('target_per_pax', 15, 2)->comment('Target total tabungan per orang');
            $table->decimal('monthly_per_pax', 15, 2)->comment('Nominal tagihan bulanan per orang');
            $table->date('start_date')->comment('Awal periode menabung');
            $table->date('end_date')->comment('Akhir periode / keberangkatan');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'closed', 'completed'])->default('active');
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kloters');
    }
};

