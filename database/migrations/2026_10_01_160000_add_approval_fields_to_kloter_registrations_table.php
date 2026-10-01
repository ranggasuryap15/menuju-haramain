<?php
/**
 * File: database/migrations/2026_10_01_160000_add_approval_fields_to_kloter_registrations_table.php
 * Tujuan: Menambahkan status pending & rejected serta atribut approval admin pada pendaftaran kloter
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Schema builder, DB
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Alter table kloter_registrations menambah status 'pending', 'rejected', approved_by, approved_at, admin_notes
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ubah enum status di MySQL dengan raw statement agar mendukung pending dan rejected
        DB::statement("ALTER TABLE kloter_registrations MODIFY COLUMN status ENUM('pending', 'active', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('kloter_registrations', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('admin_notes')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kloter_registrations', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_by', 'approved_at', 'admin_notes']);
        });

        DB::statement("ALTER TABLE kloter_registrations MODIFY COLUMN status ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active'");
    }
};
