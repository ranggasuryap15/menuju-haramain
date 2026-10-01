<?php
/**
 * File: database/migrations/2026_10_01_150007_create_invoice_items_table.php
 * Tujuan: Membuat tabel rincian tagihan per pax anggota keluarga
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Membuat tabel invoice_items dengan relasi cascade ke invoices
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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_pax_id')->constrained('registration_paxes')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->timestamps();

            $table->index(['invoice_id', 'registration_pax_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};

