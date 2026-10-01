<?php

/**
 * File: database/seeders/DatabaseSeeder.php
 * Tujuan: Seeder data awal untuk pengujian peran (Superadmin, Admin Keuangan, Jamaah), anggota keluarga, kloter, rekening bank, dan tagihan
 * Dipakai Oleh: php artisan db:seed / php artisan migrate:fresh --seed
 * Dependensi Utama: User, FamilyMember, Kloter, KloterRegistration, RegistrationPax, BankAccount, BillingService
 * Daftar Fungsi Utama: run()
 * Side Effect: Insert data awal ke berbagai tabel database
 */

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\FamilyMember;
use App\Models\Kloter;
use App\Models\KloterRegistration;
use App\Models\RegistrationPax;
use App\Models\User;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Pengguna & Hak Akses
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@haramain.com'],
            [
                'name' => 'Ustadz Abdullah (Superadmin)',
                'role' => User::ROLE_SUPERADMIN,
                'phone' => '081234567890',
                'password' => Hash::make('password'),
            ]
        );

        $adminKeuangan = User::firstOrCreate(
            ['email' => 'keuangan@haramain.com'],
            [
                'name' => 'Hendra Pratama (Admin Keuangan & Calon Jamaah)',
                'role' => User::ROLE_ADMIN_KEUANGAN,
                'phone' => '081298765432',
                'password' => Hash::make('password'),
            ]
        );

        $jamaahAhmad = User::firstOrCreate(
            ['email' => 'ahmad@gmail.com'],
            [
                'name' => 'Ahmad Wijaya (Kepala Keluarga)',
                'role' => User::ROLE_JAMAAH,
                'phone' => '081311223344',
                'password' => Hash::make('password'),
            ]
        );

        $jamaahSiti = User::firstOrCreate(
            ['email' => 'siti@gmail.com'],
            [
                'name' => 'Siti Rahma',
                'role' => User::ROLE_JAMAAH,
                'phone' => '081355667788',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Anggota Keluarga (Multi-person di dalam akun)
        // Keluarga Ahmad (3 orang: Suami, Istri, 1 Anak)
        $paxAhmad1 = FamilyMember::create([
            'user_id' => $jamaahAhmad->id,
            'full_name' => 'Ahmad Wijaya',
            'relationship' => 'Kepala Keluarga',
            'identity_number' => '3271011508850001',
            'birth_date' => '1985-08-15',
            'gender' => 'L',
            'phone' => '081311223344',
        ]);

        $paxAhmad2 = FamilyMember::create([
            'user_id' => $jamaahAhmad->id,
            'full_name' => 'Fatimah Az-Zahra',
            'relationship' => 'Istri',
            'identity_number' => '3271015204880002',
            'birth_date' => '1988-04-12',
            'gender' => 'P',
        ]);

        $paxAhmad3 = FamilyMember::create([
            'user_id' => $jamaahAhmad->id,
            'full_name' => 'Muhammad Ali Wijaya',
            'relationship' => 'Anak',
            'identity_number' => '3271012010150003',
            'birth_date' => '2015-10-20',
            'gender' => 'L',
        ]);

        // Keluarga Siti (2 orang: Istri dan Suami)
        $paxSiti1 = FamilyMember::create([
            'user_id' => $jamaahSiti->id,
            'full_name' => 'Siti Rahma',
            'relationship' => 'Kepala Keluarga',
            'identity_number' => '3271026011900004',
            'birth_date' => '1990-11-20',
            'gender' => 'P',
            'phone' => '081355667788',
        ]);

        $paxSiti2 = FamilyMember::create([
            'user_id' => $jamaahSiti->id,
            'full_name' => 'Yusuf Maulana',
            'relationship' => 'Suami',
            'identity_number' => '3271021406890005',
            'birth_date' => '1989-06-14',
            'gender' => 'L',
        ]);

        // Keluarga Admin Keuangan (Menunjukkan Admin juga bisa sebagai Jamaah bersama Istrinya)
        $paxHendra1 = FamilyMember::create([
            'user_id' => $adminKeuangan->id,
            'full_name' => 'Hendra Pratama',
            'relationship' => 'Kepala Keluarga',
            'identity_number' => '3271031102870006',
            'birth_date' => '1987-02-11',
            'gender' => 'L',
            'phone' => '081298765432',
        ]);

        $paxHendra2 = FamilyMember::create([
            'user_id' => $adminKeuangan->id,
            'full_name' => 'Nurul Aini',
            'relationship' => 'Istri',
            'identity_number' => '3271034509890007',
            'birth_date' => '1989-09-05',
            'gender' => 'P',
        ]);

        // 3. Rekening Bank Tujuan Transfer
        $bankBsi = BankAccount::create([
            'bank_name' => 'Bank Syariah Indonesia (BSI)',
            'account_number' => '7123-4567-89',
            'account_holder' => 'Tabungan Umroh Menuju Haramain',
            'is_active' => true,
        ]);

        $bankMuamalat = BankAccount::create([
            'bank_name' => 'Bank Muamalat Indonesia',
            'account_number' => '101-00-88899',
            'account_holder' => 'Yayasan Menuju Haramain Mandiri',
            'is_active' => true,
        ]);

        // 4. Master Kloter Umroh (Beda Periode & Nominal)
        $kloterRamadhan = Kloter::create([
            'name' => 'Kloter Ramadhan Berkah 1448H (10 Bulan)',
            'code' => 'RAMADHAN-1448',
            'target_per_pax' => 35000000,
            'monthly_per_pax' => 3500000,
            'start_date' => Carbon::now()->subMonths(2)->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(7)->endOfMonth()->toDateString(),
            'description' => 'Paket Umroh iktikaf 10 hari terakhir Ramadhan bintang 5 di Makkah & Madinah.',
            'status' => 'active',
        ]);

        $kloterSyawal = Kloter::create([
            'name' => 'Kloter Reguler Syawal 1448H (12 Bulan)',
            'code' => 'SYAWAL-1448',
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 2500000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(11)->endOfMonth()->toDateString(),
            'description' => 'Paket Umroh santai pasca Idul Fitri bersama pembimbing ibadah berpengalaman.',
            'status' => 'active',
        ]);

        // 5. Pendaftaran Kloter
        // Ahmad mendaftar Kloter Ramadhan untuk 3 orang (Ahmad, Istri, Anak)
        $regAhmad = KloterRegistration::create([
            'kloter_id' => $kloterRamadhan->id,
            'user_id' => $jamaahAhmad->id,
            'total_pax' => 3,
            'status' => 'active',
        ]);

        RegistrationPax::create(['registration_id' => $regAhmad->id, 'family_member_id' => $paxAhmad1->id]);
        RegistrationPax::create(['registration_id' => $regAhmad->id, 'family_member_id' => $paxAhmad2->id]);
        RegistrationPax::create(['registration_id' => $regAhmad->id, 'family_member_id' => $paxAhmad3->id]);

        // Hendra (Admin Keuangan) mendaftar Kloter Syawal untuk 2 orang (Hendra & Istri)
        $regHendra = KloterRegistration::create([
            'kloter_id' => $kloterSyawal->id,
            'user_id' => $adminKeuangan->id,
            'total_pax' => 2,
            'status' => 'active',
        ]);

        RegistrationPax::create(['registration_id' => $regHendra->id, 'family_member_id' => $paxHendra1->id]);
        RegistrationPax::create(['registration_id' => $regHendra->id, 'family_member_id' => $paxHendra2->id]);

        // 6. Generate Invoices untuk bulan berjalan dan bulan sebelumnya
        $billingService = app(BillingService::class);
        // Bulan lalu (Kloter Ramadhan aktif)
        $billingService->generateMonthlyInvoices(Carbon::now()->subMonth()->startOfMonth());
        // Bulan berjalan (Kloter Ramadhan & Syawal aktif)
        $billingService->generateMonthlyInvoices(Carbon::now()->startOfMonth());
    }
}
