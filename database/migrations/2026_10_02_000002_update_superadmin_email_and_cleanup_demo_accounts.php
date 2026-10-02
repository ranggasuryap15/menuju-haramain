<?php
/**
 * File: database/migrations/2026_10_02_000002_update_superadmin_email_and_cleanup_demo_accounts.php
 * Tujuan: Memperbarui email superadmin menjadi ranggasurya.313@gmail.com dan menghapus seluruh akun demo (keuangan, ahmad, siti) beserta data terkait
 * Dipakai Oleh: Artisan migrate (Database Migration)
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Illuminate\Support\Facades\DB, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Schema
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Update email superadmin dan delete data akun demo dari tabel users, family_members, kloter_registrations, registration_paxes, invoices, invoice_items, payments
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $newSuperadminEmail = 'ranggasurya.313@gmail.com';

        // 1. Update atau pastikan akun Superadmin ranggasurya.313@gmail.com
        $existingOld = DB::table('users')->where('email', 'superadmin@haramain.com')->first();
        if ($existingOld) {
            DB::table('users')->where('id', $existingOld->id)->update([
                'email' => $newSuperadminEmail,
                'name' => 'Superadmin',
                'role' => 'superadmin',
                'updated_at' => now(),
            ]);
            $superadminId = $existingOld->id;
        } else {
            $existingNew = DB::table('users')->where('email', $newSuperadminEmail)->first();
            if ($existingNew) {
                $superadminId = $existingNew->id;
                DB::table('users')->where('id', $superadminId)->update([
                    'role' => 'superadmin',
                    'updated_at' => now(),
                ]);
            } else {
                $superadminId = DB::table('users')->insertGetId([
                    'name' => 'Superadmin',
                    'email' => $newSuperadminEmail,
                    'role' => 'superadmin',
                    'phone' => '081234567890',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Pastikan entri family_members untuk superadmin ada
        if (Schema::hasTable('family_members')) {
            $hasFamily = DB::table('family_members')->where('user_id', $superadminId)->exists();
            if (!$hasFamily) {
                DB::table('family_members')->insert([
                    'user_id' => $superadminId,
                    'full_name' => 'Superadmin',
                    'relationship' => 'Kepala Keluarga',
                    'phone' => '081234567890',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Bersihkan seluruh akun demo beserta data relasinya
        $demoEmails = ['keuangan@haramain.com', 'ahmad@gmail.com', 'siti@gmail.com'];
        $demoUsers = DB::table('users')->whereIn('email', $demoEmails)->get();

        if ($demoUsers->isNotEmpty()) {
            $demoUserIds = $demoUsers->pluck('id')->toArray();

            // Ambil registrations milik demo users
            if (Schema::hasTable('kloter_registrations')) {
                $regIds = DB::table('kloter_registrations')->whereIn('user_id', $demoUserIds)->pluck('id')->toArray();

                if (!empty($regIds)) {
                    if (Schema::hasTable('invoices')) {
                        $invoiceIds = DB::table('invoices')->whereIn('registration_id', $regIds)->pluck('id')->toArray();
                        if (!empty($invoiceIds)) {
                            if (Schema::hasTable('payments')) {
                                DB::table('payments')->whereIn('invoice_id', $invoiceIds)->delete();
                            }
                            if (Schema::hasTable('invoice_items')) {
                                DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->delete();
                            }
                            DB::table('invoices')->whereIn('id', $invoiceIds)->delete();
                        }
                    }

                    if (Schema::hasTable('registration_paxes')) {
                        DB::table('registration_paxes')->whereIn('registration_id', $regIds)->delete();
                    }

                    DB::table('kloter_registrations')->whereIn('id', $regIds)->delete();
                }
            }

            if (Schema::hasTable('family_members')) {
                DB::table('family_members')->whereIn('user_id', $demoUserIds)->delete();
            }

            DB::table('users')->whereIn('id', $demoUserIds)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert superadmin email to original
        DB::table('users')->where('email', 'ranggasurya.313@gmail.com')->update([
            'email' => 'superadmin@haramain.com',
            'name' => 'Ustadz Abdullah (Superadmin)',
            'updated_at' => now(),
        ]);
    }
};
