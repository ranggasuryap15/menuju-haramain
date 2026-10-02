<?php

/**
 * File: database/seeders/DatabaseSeeder.php
 * Tujuan: Seeder data awal untuk akun Superadmin resmi, master rekening bank tujuan, dan paket master kloter umroh
 * Dipakai Oleh: php artisan db:seed / php artisan migrate:fresh --seed
 * Dependensi Utama: User, FamilyMember, Kloter, BankAccount
 * Daftar Fungsi Utama: run()
 * Side Effect: Insert data awal superadmin, rekening bank, master kloter & pivot kloter_bank_account
 */

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\FamilyMember;
use App\Models\Kloter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Superadmin Resmi
        $superadmin = User::firstOrCreate(
            ['email' => 'ranggasurya.313@gmail.com'],
            [
                'name' => 'Superadmin',
                'role' => User::ROLE_SUPERADMIN,
                'phone' => '081234567890',
                'password' => Hash::make('password'),
            ]
        );

        // Profil data anggota keluarga awal untuk Superadmin
        FamilyMember::firstOrCreate(
            [
                'user_id' => $superadmin->id,
                'identity_number' => '3271010101900001',
            ],
            [
                'full_name' => 'Superadmin',
                'relationship' => 'Kepala Keluarga',
                'birth_date' => '1990-01-01',
                'gender' => 'L',
                'phone' => '081234567890',
            ]
        );

        // 2. Rekening Bank Resmi Tujuan Transfer
        $bankBsi = BankAccount::firstOrCreate(
            ['account_number' => '7123-4567-89'],
            [
                'bank_name' => 'Bank Syariah Indonesia (BSI)',
                'account_holder' => 'Tabungan Umroh Menuju Haramain',
                'is_active' => true,
            ]
        );

        $bankMuamalat = BankAccount::firstOrCreate(
            ['account_number' => '101-00-88899'],
            [
                'bank_name' => 'Bank Muamalat Indonesia',
                'account_holder' => 'Yayasan Menuju Haramain Mandiri',
                'is_active' => true,
            ]
        );

        $bankBcaSyariah = BankAccount::firstOrCreate(
            ['account_number' => '088-234-5566'],
            [
                'bank_name' => 'BCA Syariah',
                'account_holder' => 'PT Menuju Haramain Berkah',
                'is_active' => true,
            ]
        );

        // 3. Master Kloter Umroh (Beda Periode, Nominal, & Rekening Bank)
        $kloterRamadhan = Kloter::firstOrCreate(
            ['code' => 'RAMADHAN-1448'],
            [
                'name' => 'Kloter Ramadhan Berkah 1448H (10 Bulan)',
                'target_per_pax' => 35000000,
                'monthly_per_pax' => 3500000,
                'start_date' => Carbon::now()->subMonths(2)->startOfMonth()->toDateString(),
                'end_date' => Carbon::now()->addMonths(7)->endOfMonth()->toDateString(),
                'description' => 'Paket Umroh iktikaf 10 hari terakhir Ramadhan bintang 5 di Makkah & Madinah.',
                'status' => 'active',
            ]
        );
        // Tautkan BSI dan Muamalat ke Kloter Ramadhan
        $kloterRamadhan->bankAccounts()->sync([$bankBsi->id, $bankMuamalat->id]);

        $kloterSyawal = Kloter::firstOrCreate(
            ['code' => 'SYAWAL-1448'],
            [
                'name' => 'Kloter Reguler Syawal 1448H (12 Bulan)',
                'target_per_pax' => 30000000,
                'monthly_per_pax' => 2500000,
                'start_date' => Carbon::now()->startOfMonth()->toDateString(),
                'end_date' => Carbon::now()->addMonths(11)->endOfMonth()->toDateString(),
                'description' => 'Paket Umroh santai pasca Idul Fitri bersama pembimbing ibadah berpengalaman.',
                'status' => 'active',
            ]
        );
        // Tautkan BCA Syariah khusus ke Kloter Syawal
        $kloterSyawal->bankAccounts()->sync([$bankBcaSyariah->id]);
    }
}
