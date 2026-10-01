<?php

/**
 * File: database/migrations/2026_10_01_150008_create_payments_table.php
 * Tujuan: Membuat tabel transaksi pembayaran tabungan dan unggah bukti transfer manual
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Membuat tabel payments dengan audit log verifikasi admin
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Akun pembayar');
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2)->comment('Nominal aktual yang ditransfer');
            $table->date('payment_date');
            $table->string('proof_path');
            $table->string('sender_bank', 100)->nullable();
            $table->string('sender_account_name', 150)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
