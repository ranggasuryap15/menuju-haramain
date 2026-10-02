<?php
/**
 * File: database/migrations/2026_10_01_170000_ensure_default_superadmin_user_exists.php
 * Tujuan: Memastikan akun default Superadmin (ranggasurya.313@gmail.com) selalu ada di database secara otomatis bahkan saat migrate:fresh tanpa seeder
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
        $superadminEmail = 'ranggasurya.313@gmail.com';

        // Cari apakah akun superadmin sudah ada (atau jika ada email lama, update)
        $user = DB::table('users')->where('email', $superadminEmail)->first();
        if (!$user) {
            $oldUser = DB::table('users')->where('email', 'superadmin@haramain.com')->first();
            if ($oldUser) {
                DB::table('users')->where('id', $oldUser->id)->update([
                    'email' => $superadminEmail,
                    'name' => 'Superadmin',
                    'updated_at' => now(),
                ]);
                $user = DB::table('users')->where('id', $oldUser->id)->first();
            }
        }

        if (!$user) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Superadmin',
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
        $superadminEmail = 'ranggasurya.313@gmail.com';
        $user = DB::table('users')->where('email', $superadminEmail)->first();

        if ($user) {
            if (Schema::hasTable('family_members')) {
                DB::table('family_members')->where('user_id', $user->id)->delete();
            }
            DB::table('users')->where('id', $user->id)->delete();
        }
    }
};
