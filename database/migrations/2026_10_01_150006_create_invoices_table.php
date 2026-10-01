<?php
/**
 * File: database/migrations/2026_10_01_150006_create_invoices_table.php
 * Tujuan: Membuat tabel tagihan bulanan (invoices) dengan composite unique period untuk idempotensi
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Membuat tabel invoices dengan unique index idempotensi tagihan bulanan
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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('kloter_registrations')->cascadeOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->unsignedSmallInteger('billing_year');
            $table->unsignedTinyInteger('billing_month');
            $table->date('billing_date')->comment('Tanggal 1 setiap bulan');
            $table->date('due_date')->comment('Batas waktu pembayaran');
            $table->decimal('total_amount', 15, 2)->comment('Total tagihan seluruh pax aktif');
            $table->decimal('paid_amount', 15, 2)->default(0)->comment('Total nominal terverifikasi');
            $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'overdue'])->default('unpaid');
            $table->timestamps();

            // Indeks komposit unik untuk mencegah duplikasi tagihan pada bulan dan tahun yang sama (Idempotency)
            $table->unique(['registration_id', 'billing_year', 'billing_month'], 'uk_invoice_registration_period');
            $table->index(['status', 'billing_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

