<?php
/**
 * File: database/migrations/2026_10_01_170000_ensure_default_superadmin_user_exists.php
 * Tujuan: Memastikan akun default Superadmin selalu ada di database secara otomatis bahkan saat migrate:fresh tanpa seeder
 * Dipakai Oleh: Artisan migrate / migrate:fresh
 * Dependensi Utama: Illuminate\Database\Migrations\Migration, Illuminate\Support\Facades\DB, Illuminate\Support\Facades\Hash, Illuminate\Support\Facades\Schema
 * Daftar Fungsi Utama: up(), down()
 * Side Effect: Insert data akun Superadmin ke tabel users dan entri default ke family_members
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
        $superadminEmail = 'superadmin@haramain.com';

        // Cari apakah akun superadmin sudah ada
        $user = DB::table('users')->where('email', $superadminEmail)->first();

        if (!$user) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Ustadz Abdullah (Superadmin)',
                'email' => $superadminEmail,
                'role' => 'superadmin',
                'phone' => '081234567890',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = $user->id;
            // Pastikan perannya tetap superadmin
            if ($user->role !== 'superadmin') {
                DB::table('users')->where('id', $userId)->update([
                    'role' => 'superadmin',
                    'updated_at' => now(),
                ]);
            }
        }

        // Pastikan entri profil keluarga kepala keluarga superadmin tersedia jika tabel family_members ada
        if ($userId && Schema::hasTable('family_members')) {
            $hasFamily = DB::table('family_members')->where('user_id', $userId)->exists();
            if (!$hasFamily) {
                DB::table('family_members')->insert([
                    'user_id' => $userId,
                    'full_name' => 'Ustadz Abdullah (Superadmin)',
                    'relationship' => 'Kepala Keluarga',
                    'phone' => '081234567890',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $superadminEmail = 'superadmin@haramain.com';
        $user = DB::table('users')->where('email', $superadminEmail)->first();

        if ($user) {
            if (Schema::hasTable('family_members')) {
                DB::table('family_members')->where('user_id', $user->id)->delete();
            }
            DB::table('users')->where('id', $user->id)->delete();
        }
    }
};
