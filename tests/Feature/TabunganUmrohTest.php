<?php
/**
 * File: tests/Feature/TabunganUmrohTest.php
 * Tujuan: Feature test suite menyeluruh untuk sistem Tabungan Umroh Menuju Haramain
 * Dipakai Oleh: PHPUnit / php artisan test
 * Dependensi Utama: Tests\TestCase, Illuminate\Foundation\Testing\DatabaseTransactions, App\Models\*
 * Daftar Test Utama:
 *   - test_user_authentication_and_role_redirection()
 *   - test_user_can_view_and_update_profile()
 *   - test_user_can_update_password()
 *   - test_multi_person_family_management()
 *   - test_family_member_update_and_authorization()
 *   - test_kloter_registration_with_selected_paxes()
 *   - test_can_register_additional_family_member_to_same_kloter()
 *   - test_kloter_registration_auto_approved_for_staff()
 *   - test_admin_can_approve_and_reject_registration()
 *   - test_monthly_billing_generation_and_idempotency()
 *   - test_manual_payment_submission_with_proof()
 *   - test_anti_self_approval_rule_enforcement()
 *   - test_payment_approval_by_different_admin()
 *   - test_overpayment_carry_over_waterfall_allocation()
 *   - test_kloter_pages_correctly_calculate_collected_funds_after_payments_approved()
 *   - test_sequential_bill_payment_locking()
 *   - test_superadmin_and_admin_can_edit_kloter()
 *   - test_kloter_update_validation()
 *   - test_notification_service_and_modal_popup_integration()
 *   - test_default_superadmin_always_exists_and_can_authenticate()
 *   - test_kloter_can_have_distinct_bank_accounts_and_displayed_on_invoice()
 *   - test_kloter_code_is_always_persisted_and_retrieved_in_uppercase()
 *   - test_late_joining_jamaah_billing_and_final_month_catchup_settlement()
 *   - test_superadmin_user_management_access_control()
 *   - test_superadmin_can_create_edit_and_reset_password_for_jamaah_and_admin()
 *   - test_superadmin_can_promote_and_demote_user_roles_with_security_guards()
 *   - test_kloter_specific_billing_generation_via_show_page()
 *   - test_csrf_token_mismatch_exception_handling_and_friendly_recovery()
 *   - test_retroactive_billing_and_catchup_generation_for_older_periods()
 *   - test_kloter_show_displays_paginated_payments_history_descending()
 *   - test_superadmin_and_admin_keuangan_can_access_and_filter_unpaid_invoices_monitoring()
 *   - test_admin_and_superadmin_can_update_bank_account_details_and_add_new_bank()
 * Side Effect: Database read/write dalam transaction rollback
 */

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\FamilyMember;
use App\Models\Invoice;
use App\Models\Kloter;
use App\Models\KloterRegistration;
use App\Models\Payment;
use App\Models\RegistrationPax;
use App\Models\User;
use App\Services\BillingService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TabunganUmrohTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 1. Test autentikasi & pengalihan berdasarkan peran (Role Redirection)
     */
    public function test_user_authentication_and_role_redirection(): void
    {
        $jamaah = User::factory()->create([
            'role' => User::ROLE_JAMAAH,
            'password' => Hash::make('password123'),
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_KEUANGAN,
            'password' => Hash::make('password123'),
        ]);

        // Login sebagai Jamaah -> redirect ke /dashboard
        $responseJamaah = $this->post('/login', [
            'email' => $jamaah->email,
            'password' => 'password123',
        ]);
        $responseJamaah->assertRedirect(route('jamaah.dashboard'));

        // Pengguna yang sudah login sebagai Jamaah TIDAK BOLEH melihat form login atau register, melainkan diredirect ke dashboard
        $this->actingAs($jamaah);
        $this->get('/login')->assertRedirect(route('jamaah.dashboard'));
        $this->get('/register')->assertRedirect(route('jamaah.dashboard'));

        $this->post('/logout');

        // Login sebagai Admin -> redirect ke /admin/dashboard
        $responseAdmin = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);
        $responseAdmin->assertRedirect(route('admin.dashboard'));

        // Pengguna yang sudah login sebagai Admin TIDAK BOLEH melihat form login atau register, melainkan diredirect ke admin dashboard
        $this->actingAs($admin);
        $this->get('/login')->assertRedirect(route('admin.dashboard'));
        $this->get('/register')->assertRedirect(route('admin.dashboard'));

        $this->post('/logout');

        // Tamu (Guest) dapat mengakses form login & register secara normal
        $this->get('/login')->assertStatus(200)->assertSee('Masuk Akun');
        $this->get('/register')->assertStatus(200)->assertSee('Daftar Akun Tabungan');
    }

    /**
     * 2. Test manajemen multi-orang per akun (Kepala keluarga mendaftarkan istri dan anak)
     */
    public function test_multi_person_family_management(): void
    {
        $headOfFamily = User::factory()->create(['role' => User::ROLE_JAMAAH]);

        $this->actingAs($headOfFamily);

        // Tambah Istri
        $response = $this->post(route('jamaah.family.store'), [
            'full_name' => 'Khadijah Pratama',
            'relationship' => 'Istri',
            'gender' => 'P',
            'identity_number' => '3201019901880002',
            'birth_date' => '1990-01-01',
        ]);

        $response->assertRedirect(route('jamaah.family.index'));
        $this->assertDatabaseHas('family_members', [
            'user_id' => $headOfFamily->id,
            'full_name' => 'Khadijah Pratama',
            'relationship' => 'Istri',
        ]);

        // Tambah Anak
        $this->post(route('jamaah.family.store'), [
            'full_name' => 'Fatimah Az-Zahra',
            'relationship' => 'Anak',
            'gender' => 'P',
            'identity_number' => '3201012015050003',
            'birth_date' => '2015-05-10',
        ]);

        $this->assertEquals(2, $headOfFamily->familyMembers()->count());
    }

    /**
     * Test pembaruan data anggota keluarga dan proteksi otorisasi antar akun
     */
    public function test_family_member_update_and_authorization(): void
    {
        $user1 = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $user2 = User::factory()->create(['role' => User::ROLE_JAMAAH]);

        $member = FamilyMember::create([
            'user_id' => $user1->id,
            'full_name' => 'Fatimah Asli',
            'relationship' => 'Istri',
            'gender' => 'P',
            'identity_number' => '3201019901880001',
            'birth_date' => '1992-05-15',
            'phone' => '081234567890',
        ]);

        // User lain (user2) mencoba update data milik user1 -> 403 Forbidden
        $this->actingAs($user2);
        $responseUnauthorized = $this->put(route('jamaah.family.update', $member), [
            'full_name' => 'Hacker Name',
            'relationship' => 'Istri',
            'gender' => 'P',
        ]);
        $responseUnauthorized->assertStatus(403);

        // User pemilik (user1) mengupdate data dengan benar
        $this->actingAs($user1);
        $response = $this->put(route('jamaah.family.update', $member), [
            'full_name' => 'Fatimah Az-Zahra Updated',
            'relationship' => 'Istri',
            'gender' => 'P',
            'identity_number' => '3201019901889999',
            'birth_date' => '1992-05-20',
            'phone' => '089988776655',
        ]);

        $response->assertRedirect(route('jamaah.family.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('family_members', [
            'id' => $member->id,
            'user_id' => $user1->id,
            'full_name' => 'Fatimah Az-Zahra Updated',
            'identity_number' => '3201019901889999',
            'birth_date' => '1992-05-20 00:00:00',
            'phone' => '089988776655',
        ]);
    }

    /**
     * 3. Test pendaftaran kloter dengan multi-pax
     */
    public function test_kloter_registration_with_selected_paxes(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $member1 = FamilyMember::create([
            'user_id' => $user->id,
            'full_name' => 'Ahmad Kepala Keluarga',
            'relationship' => 'Kepala Keluarga',
            'gender' => 'L',
        ]);
        $member2 = FamilyMember::create([
            'user_id' => $user->id,
            'full_name' => 'Siti Istri',
            'relationship' => 'Istri',
            'gender' => 'P',
        ]);

        $kloter = Kloter::create([
            'name' => 'Kloter Syawal 1448H',
            'code' => 'TEST-SYAWAL-' . uniqid(),
            'target_per_pax' => 35000000,
            'monthly_per_pax' => 3500000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(10)->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('jamaah.registrations.store'), [
            'kloter_id' => $kloter->id,
            'family_member_ids' => [$member1->id, $member2->id],
        ]);

        $response->assertRedirect(route('jamaah.dashboard'));

        // Jamaah mendaftar harus berstatus pending (menunggu approval admin)
        $this->assertDatabaseHas('kloter_registrations', [
            'user_id' => $user->id,
            'kloter_id' => $kloter->id,
            'status' => 'pending',
        ]);

        $registration = KloterRegistration::where('user_id', $user->id)->first();
        $this->assertEquals(2, $registration->paxes()->count());
        $this->assertTrue($registration->isPending());
    }

    /**
     * Test penambahan anggota keluarga baru ke kloter yang sudah ada pendaftaran sebelumnya
     */
    public function test_can_register_additional_family_member_to_same_kloter(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $member1 = FamilyMember::create([
            'user_id' => $user->id,
            'full_name' => 'Joehar Anwari',
            'relationship' => 'Kepala Keluarga',
            'gender' => 'L',
        ]);

        $kloter = Kloter::create([
            'name' => 'Kloter Tambun 1448H',
            'code' => 'KLTR-TAMBUN-' . uniqid(),
            'target_per_pax' => 27000000,
            'monthly_per_pax' => 450000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(60)->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($user);

        // 1. Pendaftaran pertama: hanya mendaftarkan diri sendiri (member1)
        $res1 = $this->post(route('jamaah.registrations.store'), [
            'kloter_id' => $kloter->id,
            'family_member_ids' => [$member1->id],
        ]);
        $res1->assertRedirect(route('jamaah.dashboard'));

        $this->assertDatabaseHas('kloter_registrations', [
            'user_id' => $user->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 1,
        ]);

        // 2. Buat anggota keluarga baru: Arinda Permata Sari (Istri)
        $member2 = FamilyMember::create([
            'user_id' => $user->id,
            'full_name' => 'Arinda Permata Sari',
            'relationship' => 'Istri',
            'gender' => 'P',
        ]);

        // 3. Akses form pendaftaran kloter: kloter harus tetap dapat dipilih (tidak disabled)
        $formRes = $this->get(route('jamaah.registrations.create'));
        $formRes->assertStatus(200);
        $formRes->assertSee('Arinda Permata Sari');
        $formRes->assertSee('Sudah Terdaftar di Kloter Ini');

        // 4. Daftarkan anggota keluarga kedua (member2) ke kloter yang sama
        $res2 = $this->post(route('jamaah.registrations.store'), [
            'kloter_id' => $kloter->id,
            'family_member_ids' => [$member2->id],
        ]);
        $res2->assertRedirect(route('jamaah.dashboard'));

        // Verifikasi registrasi berhasil diupdate menjadi total 2 pax
        $this->assertDatabaseHas('kloter_registrations', [
            'user_id' => $user->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 2,
        ]);

        $reg = KloterRegistration::where('user_id', $user->id)->where('kloter_id', $kloter->id)->first();
        $this->assertEquals(2, $reg->paxes()->count());

        // 5. Jika mencoba submit ulang member yang sudah terdaftar semua -> Ditolak dengan error
        $res3 = $this->post(route('jamaah.registrations.store'), [
            'kloter_id' => $kloter->id,
            'family_member_ids' => [$member1->id, $member2->id],
        ]);
        $res3->assertSessionHas('error');
    }

    /**
     * 4. Test penagihan bulanan tanggal 1 & proteksi Idempotency (anti-double billing)
     */
    public function test_monthly_billing_generation_and_idempotency(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $member1 = FamilyMember::create([
            'user_id' => $user->id,
            'full_name' => 'Peserta Satu',
            'relationship' => 'Kepala Keluarga',
            'gender' => 'L',
        ]);
        $member2 = FamilyMember::create([
            'user_id' => $user->id,
            'full_name' => 'Peserta Dua',
            'relationship' => 'Istri',
            'gender' => 'P',
        ]);

        $currentMonth = Carbon::now()->startOfMonth();
        $kloter = Kloter::create([
            'name' => 'Kloter Idempotency Test',
            'code' => 'TEST-IDEMP-' . uniqid(),
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => $currentMonth->toDateString(),
            'end_date' => $currentMonth->copy()->addMonths(10)->toDateString(),
            'status' => 'active',
        ]);

        $registration = KloterRegistration::create([
            'user_id' => $user->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 2,
            'status' => 'active',
        ]);

        RegistrationPax::create(['registration_id' => $registration->id, 'family_member_id' => $member1->id, 'status' => 'active']);
        RegistrationPax::create(['registration_id' => $registration->id, 'family_member_id' => $member2->id, 'status' => 'active']);

        $billingService = app(BillingService::class);

        // Eksekusi generate billing pertama
        $stats1 = $billingService->generateMonthlyInvoices($currentMonth);
        $this->assertGreaterThanOrEqual(1, $stats1['created']);

        // Verifikasi invoice & rincian nominal (2 pax * Rp 3.000.000 = Rp 6.000.000)
        $invoice = Invoice::where('registration_id', $registration->id)
            ->where('billing_year', $currentMonth->year)
            ->where('billing_month', $currentMonth->month)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(6000000, (int) $invoice->total_amount);
        $this->assertEquals(2, $invoice->items()->count());

        // Eksekusi generate billing kedua pada bulan yang sama -> Harus Idempoten (skipped, tidak duplikat)
        $stats2 = $billingService->generateMonthlyInvoices($currentMonth);
        $this->assertEquals(0, $stats2['created']);
        $this->assertGreaterThanOrEqual(1, $stats2['skipped']);

        // Pastikan hanya tetap 1 invoice yang ada di DB
        $invoiceCount = Invoice::where('registration_id', $registration->id)
            ->where('billing_year', $currentMonth->year)
            ->where('billing_month', $currentMonth->month)
            ->count();
        $this->assertEquals(1, $invoiceCount);
    }

    /**
     * 5. Test upload bukti transfer manual oleh jamaah
     */
    public function test_manual_payment_submission_with_proof(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $bank = BankAccount::create([
            'bank_name' => 'Bank Syariah Indonesia (BSI)',
            'account_number' => '1234567890',
            'account_holder' => 'Yayasan Menuju Haramain',
            'is_active' => true,
        ]);

        $kloter = Kloter::create([
            'name' => 'Kloter Manual Pay',
            'code' => 'TEST-PAY-' . uniqid(),
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(10)->toDateString(),
            'status' => 'active',
        ]);

        $reg = KloterRegistration::create([
            'user_id' => $user->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 1,
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'registration_id' => $reg->id,
            'billing_year' => Carbon::now()->year,
            'billing_month' => Carbon::now()->month,
            'billing_date' => Carbon::now()->startOfMonth()->toDateString(),
            'due_date' => Carbon::now()->addDays(10)->toDateString(),
            'total_amount' => 3000000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('struk_transfer.jpg');

        $response = $this->post(route('jamaah.payments.store', $invoice), [
            'bank_account_id' => $bank->id,
            'amount' => 3000000,
            'payment_date' => Carbon::now()->toDateString(),
            'proof_file' => $file,
            'sender_bank' => 'BSI Mobile',
            'sender_account_name' => 'Ahmad Pratama',
        ]);

        $response->assertRedirect(route('jamaah.invoices.show', $invoice));

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'status' => Payment::STATUS_PENDING,
            'amount' => 3000000,
        ]);

        // Invoice mendeteksi adanya pending payment
        $this->assertTrue($invoice->fresh()->hasPendingPayment());
    }

    /**
     * 6. Test penegakan aturan Anti Self-Approval (Admin dilarang approve pembayarannya sendiri)
     */
    public function test_anti_self_approval_rule_enforcement(): void
    {
        Storage::fake('public');

        // Admin Keuangan yang mendaftar sebagai jamaah tabungan
        $adminUser = User::factory()->create([
            'name' => 'Admin Keuangan Menabung',
            'role' => User::ROLE_ADMIN_KEUANGAN,
        ]);

        $bank = BankAccount::create([
            'bank_name' => 'Bank Muamalat',
            'account_number' => '0987654321',
            'account_holder' => 'Yayasan Menuju Haramain',
            'is_active' => true,
        ]);

        $kloter = Kloter::create([
            'name' => 'Kloter Admin Dual-Role',
            'code' => 'TEST-DUAL-' . uniqid(),
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(10)->toDateString(),
            'status' => 'active',
        ]);

        $reg = KloterRegistration::create([
            'user_id' => $adminUser->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 1,
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-SELF-001',
            'registration_id' => $reg->id,
            'billing_year' => Carbon::now()->year,
            'billing_month' => Carbon::now()->month,
            'billing_date' => Carbon::now()->startOfMonth()->toDateString(),
            'due_date' => Carbon::now()->addDays(10)->toDateString(),
            'total_amount' => 3000000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $adminUser->id,
            'bank_account_id' => $bank->id,
            'amount' => 3000000,
            'payment_date' => Carbon::now()->toDateString(),
            'proof_path' => 'payments/dummy.jpg',
            'status' => Payment::STATUS_PENDING,
        ]);

        // Cek domain service langsung: canBeApprovedBy harus false
        $this->assertFalse($payment->canBeApprovedBy($adminUser));

        $paymentService = app(PaymentService::class);

        // Eksekusi approve oleh admin yang bersangkutan wajib melempar ValidationException
        $this->expectException(ValidationException::class);
        $paymentService->approvePayment($payment, $adminUser);
    }

    /**
     * 7. Test verifikasi dan approval oleh admin lain (Separation of Duty)
     */
    public function test_payment_approval_by_different_admin(): void
    {
        $jamaah = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $verifyingAdmin = User::factory()->create(['role' => User::ROLE_ADMIN_KEUANGAN]);

        $bank = BankAccount::create([
            'bank_name' => 'Bank Mandiri Syariah',
            'account_number' => '555666777',
            'account_holder' => 'Yayasan Menuju Haramain',
            'is_active' => true,
        ]);

        $kloter = Kloter::create([
            'name' => 'Kloter Approval Test',
            'code' => 'TEST-APPV-' . uniqid(),
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(10)->toDateString(),
            'status' => 'active',
        ]);

        $reg = KloterRegistration::create([
            'user_id' => $jamaah->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 1,
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-APPV-001',
            'registration_id' => $reg->id,
            'billing_year' => Carbon::now()->year,
            'billing_month' => Carbon::now()->month,
            'billing_date' => Carbon::now()->startOfMonth()->toDateString(),
            'due_date' => Carbon::now()->addDays(10)->toDateString(),
            'total_amount' => 3000000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $jamaah->id,
            'bank_account_id' => $bank->id,
            'amount' => 3000000,
            'payment_date' => Carbon::now()->toDateString(),
            'proof_path' => 'payments/dummy.jpg',
            'status' => Payment::STATUS_PENDING,
        ]);

        // Verifying admin bisa meng-approve
        $this->assertTrue($payment->canBeApprovedBy($verifyingAdmin));

        // Melakukan approve melalui route admin
        $this->actingAs($verifyingAdmin);

        $response = $this->post(route('admin.payments.approve', $payment));
        $response->assertRedirect(route('admin.payments.index'));

        // Cek status Payment menjadi approved dan verifikator tercatat
        $payment->refresh();
        $this->assertEquals(Payment::STATUS_APPROVED, $payment->status);
        $this->assertEquals($verifyingAdmin->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);

        // Cek status Invoice berubah menjadi paid dan paid_amount terupdate
        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEquals(3000000, (int) $invoice->paid_amount);
    }

    /**
     * 8. Test pendaftaran kloter oleh Superadmin & Admin Keuangan: langsung otomatis disetujui (Auto-Approved)
     */
    public function test_kloter_registration_auto_approved_for_staff(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEUANGAN]);
        $member = FamilyMember::create([
            'user_id' => $admin->id,
            'full_name' => 'Peserta Staff',
            'relationship' => 'Kepala Keluarga',
            'gender' => 'L',
        ]);

        $kloter = Kloter::create([
            'name' => 'Kloter Auto Approved Staff',
            'code' => 'TEST-STAFF-' . uniqid(),
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(10)->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        $response = $this->post(route('jamaah.registrations.store'), [
            'kloter_id' => $kloter->id,
            'family_member_ids' => [$member->id],
        ]);

        $response->assertRedirect(route('jamaah.dashboard'));

        // Admin/Superadmin tidak perlu approval -> langsung active
        $this->assertDatabaseHas('kloter_registrations', [
            'user_id' => $admin->id,
            'kloter_id' => $kloter->id,
            'status' => 'active',
            'approved_by' => $admin->id,
        ]);

        $reg = KloterRegistration::where('user_id', $admin->id)->first();
        $this->assertTrue($reg->isActive());
        $this->assertFalse($reg->isPending());
    }

    /**
     * 9. Test admin/superadmin menyetujui dan menolak pendaftaran kloter jamaah
     */
    public function test_admin_can_approve_and_reject_registration(): void
    {
        $jamaah = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEUANGAN]);

        $kloter = Kloter::create([
            'name' => 'Kloter Approval Flow Test',
            'code' => 'TEST-FLOW-' . uniqid(),
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(10)->toDateString(),
            'status' => 'active',
        ]);

        $member = FamilyMember::create([
            'user_id' => $jamaah->id,
            'full_name' => 'Jamaah Test Approval',
            'relationship' => 'Kepala Keluarga',
            'gender' => 'L',
        ]);

        $reg = KloterRegistration::create([
            'user_id' => $jamaah->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 1,
            'status' => KloterRegistration::STATUS_PENDING,
        ]);

        RegistrationPax::create([
            'registration_id' => $reg->id,
            'family_member_id' => $member->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        // 1. Admin menyetujui (Approve)
        $response = $this->post(route('admin.registrations.approve', $reg));
        $response->assertRedirect(route('admin.registrations.index'));

        $reg->refresh();
        $this->assertTrue($reg->isActive());
        $this->assertEquals($admin->id, $reg->approved_by);
        $this->assertNotNull($reg->approved_at);

        // 2. Buat registrasi pending kedua untuk ditolak (Reject)
        $jamaah2 = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $reg2 = KloterRegistration::create([
            'user_id' => $jamaah2->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 1,
            'status' => KloterRegistration::STATUS_PENDING,
        ]);

        $rejectResponse = $this->post(route('admin.registrations.reject', $reg2), [
            'admin_notes' => 'Kuota kloter saat ini sudah penuh.',
        ]);
        $rejectResponse->assertRedirect(route('admin.registrations.index'));

        $reg2->refresh();
        $this->assertTrue($reg2->isRejected());
        $this->assertEquals('Kuota kloter saat ini sudah penuh.', $reg2->admin_notes);
        $this->assertEquals($admin->id, $reg2->approved_by);
    }

    /**
     * 10. Test alokasi kronologis (Waterfall FIFO) dan carry-over saldo kelebihan pembayaran antar invoice
     * Kasus: Tagihan bulan 1 Rp 3.000.000 dibayar Rp 5.000.000 (lebih Rp 2.000.000).
     * Tagihan bulan 2 Rp 3.000.000 otomatis berkurang sisa kewajiban menjadi Rp 1.000.000.
     * Lalu jamaah setor Rp 500.000, sisa kewajiban menjadi Rp 500.000 (bukan Rp 2.500.000).
     */
    public function test_overpayment_carry_over_waterfall_allocation(): void
    {
        $jamaah = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEUANGAN]);

        $bank = BankAccount::create([
            'bank_name' => 'BSI Tabungan',
            'account_number' => '7788990011',
            'account_holder' => 'Yayasan Menuju Haramain',
            'is_active' => true,
        ]);

        $kloter = Kloter::create([
            'name' => 'Kloter Overpayment Waterfall',
            'code' => 'TEST-WATERFALL-' . uniqid(),
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(10)->toDateString(),
            'status' => 'active',
        ]);

        $reg = KloterRegistration::create([
            'user_id' => $jamaah->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 1,
            'status' => 'active',
        ]);

        $paymentService = app(PaymentService::class);

        // 1. Tagihan Periode 1: Rp 3.000.000
        $invoice1 = Invoice::create([
            'invoice_number' => 'INV-WAT-001',
            'registration_id' => $reg->id,
            'billing_year' => 2026,
            'billing_month' => 9,
            'billing_date' => '2026-09-01',
            'due_date' => '2026-09-10',
            'total_amount' => 3000000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        // Jamaah transfer Rp 5.000.000 di Tagihan 1 (Kelebihan Rp 2.000.000)
        $payment1 = Payment::create([
            'invoice_id' => $invoice1->id,
            'user_id' => $jamaah->id,
            'bank_account_id' => $bank->id,
            'amount' => 5000000,
            'payment_date' => '2026-09-05',
            'proof_path' => 'payments/dummy.jpg',
            'status' => Payment::STATUS_PENDING,
        ]);

        $paymentService->approvePayment($payment1, $admin);

        $invoice1->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $invoice1->status);
        $this->assertEquals(3000000, (int) $invoice1->paid_amount);
        $this->assertEquals(5000000, (int) $invoice1->direct_paid_amount);
        $this->assertEquals(0, (int) $invoice1->remaining_amount);
        $this->assertEquals(2000000, (int) $invoice1->surplus_amount);
        $this->assertEquals(0, (int) $invoice1->carry_over_credit);

        // 2. Tagihan Periode 2 Terbit: Rp 3.000.000
        $invoice2 = Invoice::create([
            'invoice_number' => 'INV-WAT-002',
            'registration_id' => $reg->id,
            'billing_year' => 2026,
            'billing_month' => 10,
            'billing_date' => '2026-10-01',
            'due_date' => '2026-10-10',
            'total_amount' => 3000000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        // Alokasikan saldo pendaftaran
        app(BillingService::class)->recalculateRegistrationInvoices($reg);

        $invoice2->refresh();
        // Tagihan 2 langsung terpotong kredit bawaan Rp 2.000.000
        $this->assertEquals(2000000, (int) $invoice2->carry_over_credit);
        $this->assertEquals(2000000, (int) $invoice2->paid_amount);
        $this->assertEquals(1000000, (int) $invoice2->remaining_amount);
        $this->assertEquals(Invoice::STATUS_PARTIALLY_PAID, $invoice2->status);

        // 3. Jamaah transfer Rp 500.000 pada Tagihan 2
        $payment2 = Payment::create([
            'invoice_id' => $invoice2->id,
            'user_id' => $jamaah->id,
            'bank_account_id' => $bank->id,
            'amount' => 500000,
            'payment_date' => '2026-10-05',
            'proof_path' => 'payments/dummy2.jpg',
            'status' => Payment::STATUS_PENDING,
        ]);

        $paymentService->approvePayment($payment2, $admin);

        $invoice2->refresh();
        $this->assertEquals(500000, (int) $invoice2->direct_paid_amount);
        $this->assertEquals(2000000, (int) $invoice2->carry_over_credit);
        $this->assertEquals(2500000, (int) $invoice2->paid_amount);
        // Sisa tagihan sekarang harus tepat Rp 500.000!
        $this->assertEquals(500000, (int) $invoice2->remaining_amount);
        $this->assertEquals(Invoice::STATUS_PARTIALLY_PAID, $invoice2->status);

        // 4. Jamaah melunasi sisa Rp 500.000
        $payment3 = Payment::create([
            'invoice_id' => $invoice2->id,
            'user_id' => $jamaah->id,
            'bank_account_id' => $bank->id,
            'amount' => 500000,
            'payment_date' => '2026-10-06',
            'proof_path' => 'payments/dummy3.jpg',
            'status' => Payment::STATUS_PENDING,
        ]);

        $paymentService->approvePayment($payment3, $admin);

        $invoice2->refresh();
        $this->assertEquals(3000000, (int) $invoice2->paid_amount);
        $this->assertEquals(0, (int) $invoice2->remaining_amount);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice2->status);
    }

    /**
     * 11. Test pengguna dapat melihat dan memperbarui profil pribadi
     */
    public function test_user_can_view_and_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Lama',
            'email' => 'lama@example.com',
            'phone' => '0811111111',
        ]);

        $this->actingAs($user);

        // Halaman profil dapat diakses
        $response = $this->get(route('profile.edit'));
        $response->assertStatus(200);
        $response->assertSee('Nama Lama');

        // Update profil
        $updateResponse = $this->put(route('profile.update'), [
            'name' => 'Nama Baru Lengkap',
            'email' => 'baru@example.com',
            'phone' => '0822222222',
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('status_profile');

        $user->refresh();
        $this->assertEquals('Nama Baru Lengkap', $user->name);
        $this->assertEquals('baru@example.com', $user->email);
        $this->assertEquals('0822222222', $user->phone);
    }

    /**
     * 12. Test pengguna dapat memperbarui kata sandi dengan verifikasi kata sandi saat ini
     */
    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password_lama'),
        ]);

        $this->actingAs($user);

        // Update password baru
        $response = $this->put(route('profile.password.update'), [
            'current_password' => 'password_lama',
            'password' => 'password_baru_123',
            'password_confirmation' => 'password_baru_123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status_password');

        $user->refresh();
        $this->assertTrue(Hash::check('password_baru_123', $user->password));
    }

    /**
     * 13. Test kalkulasi dana terkumpul pada halaman kloter index & show setelah verifikasi pembayaran
     */
    public function test_kloter_pages_correctly_calculate_collected_funds_after_payments_approved(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEUANGAN]);
        $jamaah = User::factory()->create(['role' => User::ROLE_JAMAAH]);

        $kloter = Kloter::create([
            'name' => 'Kloter Uji Finansial',
            'code' => 'KLTR-FIN-' . uniqid(),
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addMonths(10)->endOfMonth(),
            'status' => 'active',
        ]);

        $reg = KloterRegistration::create([
            'user_id' => $jamaah->id,
            'kloter_id' => $kloter->id,
            'total_pax' => 1,
            'status' => KloterRegistration::STATUS_ACTIVE,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $bank = BankAccount::create([
            'bank_name' => 'BSI Uji',
            'account_number' => '1234567890',
            'account_holder' => 'Yayasan Haramain',
            'is_active' => true,
        ]);

        // Invoice 1: 3.000.000
        $invoice1 = Invoice::create([
            'registration_id' => $reg->id,
            'invoice_number' => 'INV-FIN-' . uniqid(),
            'billing_year' => now()->year,
            'billing_month' => now()->month,
            'billing_date' => now()->startOfMonth(),
            'due_date' => now()->startOfMonth()->day(10),
            'total_amount' => 3000000,
            'paid_amount' => 3000000,
            'status' => Invoice::STATUS_PAID,
        ]);

        // Payment 1: 3.000.000 approved
        Payment::create([
            'invoice_id' => $invoice1->id,
            'user_id' => $jamaah->id,
            'bank_account_id' => $bank->id,
            'amount' => 3000000,
            'payment_date' => now(),
            'proof_path' => 'proofs/dummy.jpg',
            'status' => Payment::STATUS_APPROVED,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        // Verifikasi kalkulasi model kloter
        $this->assertEquals(3000000, $kloter->total_paid);
        $this->assertEquals(3000000, $kloter->total_billed);
        $this->assertEquals(3000000, $reg->total_saved);

        // Halaman Daftar Kloter Admin (index) harus menampilkan dana terkumpul
        $this->actingAs($admin);
        $responseIndex = $this->get(route('admin.kloters.index'));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Rp 3.000.000');

        // Halaman Detail Kloter Admin (show) harus menampilkan total terkumpul, rincian terbayar, dan persentase
        $responseShow = $this->get(route('admin.kloters.show', $kloter));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('Rp 3.000.000');
        $responseShow->assertSee('Total Terkumpul');
    }

    /**
     * 14. Test aturan pelunasan berurutan kronologis (Sequential Bill Payment Lock)
     * Jika ada tagihan bulan September dan Oktober, Oktober tidak bisa dibayar sebelum September lunas.
     */
    public function test_sequential_bill_payment_locking(): void
    {
        Storage::fake('public');

        $jamaah = User::factory()->create(['role' => User::ROLE_JAMAAH]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEUANGAN]);

        $kloter = Kloter::create([
            'name' => 'Kloter Uji Sequential',
            'code' => 'KLT-SEQ-' . uniqid(),
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::create(2026, 9, 1),
            'end_date' => Carbon::create(2027, 6, 30),
            'status' => 'active',
        ]);

        $reg = KloterRegistration::create([
            'kloter_id' => $kloter->id,
            'user_id' => $jamaah->id,
            'total_pax' => 1,
            'status' => KloterRegistration::STATUS_ACTIVE,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $bank = BankAccount::create([
            'bank_name' => 'BSI Uji Sequential',
            'account_number' => '987654321',
            'account_holder' => 'Yayasan Haramain',
            'is_active' => true,
        ]);

        // Tagihan Bulan 1 (September 2026)
        $invoiceSep = Invoice::create([
            'registration_id' => $reg->id,
            'invoice_number' => 'INV-202609-' . uniqid(),
            'billing_year' => 2026,
            'billing_month' => 9,
            'billing_date' => Carbon::create(2026, 9, 1),
            'due_date' => Carbon::create(2026, 9, 10),
            'total_amount' => 3000000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        // Tagihan Bulan 2 (Oktober 2026)
        $invoiceOkt = Invoice::create([
            'registration_id' => $reg->id,
            'invoice_number' => 'INV-202610-' . uniqid(),
            'billing_year' => 2026,
            'billing_month' => 10,
            'billing_date' => Carbon::create(2026, 10, 1),
            'due_date' => Carbon::create(2026, 10, 10),
            'total_amount' => 3000000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        // 1. Verifikasi logic model
        $this->assertFalse($invoiceSep->isLockedByPreviousUnpaid());
        $this->assertNull($invoiceSep->getUnpaidPreviousInvoice());

        $this->assertTrue($invoiceOkt->isLockedByPreviousUnpaid());
        $this->assertNotNull($invoiceOkt->getUnpaidPreviousInvoice());
        $this->assertEquals($invoiceSep->id, $invoiceOkt->getUnpaidPreviousInvoice()->id);

        // 2. Verifikasi UI Jamaah: Halaman Daftar Tagihan (index) menampilkan badge Terkunci dan urutan ASC
        $this->actingAs($jamaah);
        $resIndex = $this->get(route('jamaah.invoices.index'));
        $resIndex->assertStatus(200);
        $resIndex->assertSee('Terkunci');

        // Verifikasi urutan ASC: tagihan September harus berada di atas tagihan Oktober
        $content = $resIndex->getContent();
        $posSep = strpos($content, $invoiceSep->invoice_number);
        $posOkt = strpos($content, $invoiceOkt->invoice_number);
        $this->assertNotFalse($posSep);
        $this->assertNotFalse($posOkt);
        $this->assertTrue($posSep < $posOkt, 'Tagihan periode sebelumnya harus tampil lebih atas (ASC) dibanding tagihan periode selanjutnya');

        // 3. Verifikasi UI Jamaah: Halaman Detail Tagihan Oktober (show)
        $resShowOkt = $this->get(route('jamaah.invoices.show', $invoiceOkt));
        $resShowOkt->assertStatus(200);
        $resShowOkt->assertSee('Pembayaran Periode Ini Terkunci');
        $resShowOkt->assertSee($invoiceSep->period_label);
        $resShowOkt->assertDontSee('Kirim Bukti Pembayaran');

        // 4. Verifikasi Backend Guard: POST bayar tagihan Oktober langsung ditolak
        $dummyFile = UploadedFile::fake()->image('bukti_oktober.jpg', 600, 400);
        $resPostOkt = $this->post(route('jamaah.payments.store', $invoiceOkt), [
            'bank_account_id' => $bank->id,
            'amount' => 3000000,
            'payment_date' => '2026-10-02',
            'sender_bank' => 'BSI',
            'sender_account_name' => 'Ahmad Fulan',
            'proof_file' => $dummyFile,
        ]);

        $resPostOkt->assertRedirect(route('jamaah.invoices.show', $invoiceOkt));
        $resPostOkt->assertSessionHas('error');
        $this->assertDatabaseMissing('payments', [
            'invoice_id' => $invoiceOkt->id,
        ]);

        // 5. Jamaah membayar tagihan September dan diverifikasi admin
        $proofSep = UploadedFile::fake()->image('bukti_sep.jpg', 600, 400);
        $resPostSep = $this->post(route('jamaah.payments.store', $invoiceSep), [
            'bank_account_id' => $bank->id,
            'amount' => 3000000,
            'payment_date' => '2026-09-02',
            'sender_bank' => 'BSI',
            'sender_account_name' => 'Ahmad Fulan',
            'proof_file' => $proofSep,
        ]);
        $resPostSep->assertRedirect(route('jamaah.invoices.show', $invoiceSep));
        $resPostSep->assertSessionHas('success');

        $paymentSep = Payment::where('invoice_id', $invoiceSep->id)->firstOrFail();
        
        // Admin memverifikasi pembayaran September
        $paymentService = app(PaymentService::class);
        $paymentService->approvePayment($paymentSep, $admin);

        // 6. Verifikasi setelah September lunas: Tagihan Oktober harus terbuka (unlocked)
        $invoiceSep->refresh();
        $this->assertTrue($invoiceSep->isPaid());

        // Refresh instance invoice Oktober
        $invoiceOktFresh = Invoice::find($invoiceOkt->id);
        $this->assertFalse($invoiceOktFresh->isLockedByPreviousUnpaid());
        $this->assertNull($invoiceOktFresh->getUnpaidPreviousInvoice());

        // 7. Jamaah membuka kembali halaman Oktober: form muncul
        $resShowOktUnlocked = $this->get(route('jamaah.invoices.show', $invoiceOkt));
        $resShowOktUnlocked->assertStatus(200);
        $resShowOktUnlocked->assertDontSee('Pembayaran Periode Ini Terkunci');
        $resShowOktUnlocked->assertSee('Kirim Bukti Pembayaran');

        // 8. Jamaah sekarang berhasil membayar tagihan Oktober
        $proofOkt = UploadedFile::fake()->image('bukti_okt_sukses.jpg', 600, 400);
        $resPostOktSuccess = $this->post(route('jamaah.payments.store', $invoiceOkt), [
            'bank_account_id' => $bank->id,
            'amount' => 3000000,
            'payment_date' => '2026-10-05',
            'sender_bank' => 'BSI',
            'sender_account_name' => 'Ahmad Fulan',
            'proof_file' => $proofOkt,
        ]);

        $resPostOktSuccess->assertRedirect(route('jamaah.invoices.show', $invoiceOkt));
        $resPostOktSuccess->assertSessionHas('success');
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoiceOkt->id,
            'amount' => 3000000,
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /**
     * 15. Test Superadmin dan Admin Keuangan dapat mengedit detail kloter
     * Jamaah tidak diizinkan mengakses halaman maupun endpoint update kloter.
     */
    public function test_superadmin_and_admin_can_edit_kloter(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $adminKeuangan = User::factory()->create(['role' => User::ROLE_ADMIN_KEUANGAN]);
        $jamaah = User::factory()->create(['role' => User::ROLE_JAMAAH]);

        $kloter = Kloter::create([
            'name' => 'Kloter Awal 1448 H',
            'code' => 'KLT-INIT-' . uniqid(),
            'target_per_pax' => 35000000,
            'monthly_per_pax' => 3500000,
            'start_date' => Carbon::create(2027, 1, 1),
            'end_date' => Carbon::create(2027, 10, 31),
            'description' => 'Fasilitas standar',
            'status' => 'draft',
        ]);

        // 1. Superadmin membuka halaman edit kloter
        $this->actingAs($superadmin);
        $resSuperGet = $this->get(route('admin.kloters.edit', $kloter));
        $resSuperGet->assertStatus(200);
        $resSuperGet->assertSee($kloter->name);
        $resSuperGet->assertSee($kloter->code);
        $resSuperGet->assertSee('Simpan Perubahan Kloter');

        // 2. Superadmin mengubah detail kloter
        $resSuperPut = $this->put(route('admin.kloters.update', $kloter), [
            'name' => 'Kloter Superadmin Diperbarui',
            'code' => $kloter->code,
            'target_per_pax' => 38000000,
            'monthly_per_pax' => 3800000,
            'start_date' => '2027-01-01',
            'end_date' => '2027-10-31',
            'description' => 'Fasilitas Bintang 5 Dekat Masjidil Haram',
            'status' => 'active',
        ]);

        $resSuperPut->assertRedirect(route('admin.kloters.show', $kloter));
        $resSuperPut->assertSessionHas('success');

        $this->assertDatabaseHas('kloters', [
            'id' => $kloter->id,
            'name' => 'Kloter Superadmin Diperbarui',
            'target_per_pax' => 38000000,
            'monthly_per_pax' => 3800000,
            'description' => 'Fasilitas Bintang 5 Dekat Masjidil Haram',
            'status' => 'active',
        ]);

        // 3. Admin Keuangan membuka halaman edit kloter
        $this->actingAs($adminKeuangan);
        $resAdminGet = $this->get(route('admin.kloters.edit', $kloter));
        $resAdminGet->assertStatus(200);
        $resAdminGet->assertSee('Kloter Superadmin Diperbarui');

        // 4. Admin Keuangan memperbarui status dan catatan kloter
        $resAdminPut = $this->put(route('admin.kloters.update', $kloter), [
            'name' => 'Kloter Umroh Siap Berangkat',
            'code' => 'KLT-UPD-' . uniqid(),
            'target_per_pax' => 38000000,
            'monthly_per_pax' => 3800000,
            'start_date' => '2027-01-01',
            'end_date' => '2027-10-31',
            'description' => 'Pendaftaran ditutup karena kuota penuh',
            'status' => 'closed',
        ]);

        $resAdminPut->assertRedirect(route('admin.kloters.show', $kloter));
        $resAdminPut->assertSessionHas('success');

        $this->assertDatabaseHas('kloters', [
            'id' => $kloter->id,
            'name' => 'Kloter Umroh Siap Berangkat',
            'status' => 'closed',
            'description' => 'Pendaftaran ditutup karena kuota penuh',
        ]);

        // 5. Jamaah dilarang mengakses halaman edit maupun endpoint update (403 Forbidden)
        $this->actingAs($jamaah);
        $resJamaahGet = $this->get(route('admin.kloters.edit', $kloter));
        $resJamaahGet->assertStatus(403);

        $resJamaahPut = $this->put(route('admin.kloters.update', $kloter), [
            'name' => 'Kloter Diretas',
            'code' => 'KLT-HACK',
            'target_per_pax' => 1000000,
            'monthly_per_pax' => 100000,
            'start_date' => '2027-01-01',
            'end_date' => '2027-10-31',
            'status' => 'active',
        ]);
        $resJamaahPut->assertStatus(403);
    }

    /**
     * 16. Test validasi update kloter (duplikat kode, rentang tanggal tidak valid, dll)
     */
    public function test_kloter_update_validation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEUANGAN]);

        $kloterA = Kloter::create([
            'name' => 'Kloter A',
            'code' => 'KLT-A-UNIQUE',
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::create(2027, 1, 1),
            'end_date' => Carbon::create(2027, 10, 31),
            'status' => 'active',
        ]);

        $kloterB = Kloter::create([
            'name' => 'Kloter B',
            'code' => 'KLT-B-UNIQUE',
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::create(2027, 1, 1),
            'end_date' => Carbon::create(2027, 10, 31),
            'status' => 'active',
        ]);

        $this->actingAs($admin);

        // Percobaan mengubah kode Kloter B menjadi sama dengan kode Kloter A (harus gagal validasi unique)
        $resDuplicateCode = $this->put(route('admin.kloters.update', $kloterB), [
            'name' => 'Kloter B Duplikat',
            'code' => 'KLT-A-UNIQUE',
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => '2027-01-01',
            'end_date' => '2027-10-31',
            'status' => 'active',
        ]);
        $resDuplicateCode->assertSessionHasErrors('code');

        // Percobaan tanggal selesai sebelum tanggal mulai
        $resInvalidDate = $this->put(route('admin.kloters.update', $kloterB), [
            'name' => 'Kloter B Tanggal Rusak',
            'code' => 'KLT-B-UNIQUE',
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => '2027-10-31',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);
        $resInvalidDate->assertSessionHasErrors('end_date');
    }

    /**
     * Memverifikasi kalkulasi NotificationService dan render popup modal notifikasi di layout utama
     */
    public function test_notification_service_and_modal_popup_integration(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_KEUANGAN,
            'email' => 'admin_notif_test@haramain.test',
        ]);

        $jamaah = User::factory()->create([
            'role' => User::ROLE_JAMAAH,
            'email' => 'jamaah_notif_test@haramain.test',
        ]);

        $kloterA = Kloter::create([
            'name' => 'Kloter Notif Test A',
            'code' => 'KLT-NOTIF-99A',
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::create(2027, 1, 1),
            'end_date' => Carbon::create(2027, 10, 31),
            'status' => 'active',
        ]);

        $kloterB = Kloter::create([
            'name' => 'Kloter Notif Test B',
            'code' => 'KLT-NOTIF-99B',
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::create(2027, 1, 1),
            'end_date' => Carbon::create(2027, 10, 31),
            'status' => 'active',
        ]);

        // 1. Buat pendaftaran kloter pending oleh jamaah
        $regPending = KloterRegistration::create([
            'user_id' => $jamaah->id,
            'kloter_id' => $kloterA->id,
            'status' => KloterRegistration::STATUS_PENDING,
            'total_pax' => 2,
            'target_total' => 60000000,
            'monthly_total' => 6000000,
        ]);

        // 2. Buat pendaftaran aktif dan tagihan belum lunas untuk jamaah
        $regActive = KloterRegistration::create([
            'user_id' => $jamaah->id,
            'kloter_id' => $kloterB->id,
            'status' => KloterRegistration::STATUS_ACTIVE,
            'total_pax' => 1,
            'target_total' => 30000000,
            'monthly_total' => 3000000,
        ]);

        $invoiceUnpaid = Invoice::create([
            'registration_id' => $regActive->id,
            'invoice_number' => 'INV-TEST-NOTIF-001',
            'billing_month' => 9,
            'billing_year' => 2026,
            'billing_date' => Carbon::create(2026, 9, 1),
            'due_date' => Carbon::create(2026, 9, 10),
            'total_amount' => 3000000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        // 3. Test Service langsung
        $service = app(NotificationService::class);
        $adminNotifs = $service->getNotificationsForUser($admin);
        $jamaahNotifs = $service->getNotificationsForUser($jamaah);

        // Admin harus mendeteksi minimal 1 pendaftaran kloter pending
        $this->assertGreaterThanOrEqual(1, $adminNotifs['approval_registrations_count']);
        $this->assertGreaterThanOrEqual(1, $adminNotifs['total_count']);

        // Jamaah harus mendeteksi tagihan unpaid dan registration alert pending
        $this->assertGreaterThanOrEqual(1, $jamaahNotifs['unpaid_invoices_count']);
        $this->assertGreaterThanOrEqual(1, $jamaahNotifs['registration_alerts_count']);
        $this->assertGreaterThanOrEqual(2, $jamaahNotifs['total_count']);

        // 4. Test View Integration saat Admin membuka Dashboard
        $adminResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $adminResponse->assertStatus(200);
        $adminResponse->assertViewHas('notificationsData');
        $adminResponse->assertSee('Pusat Notifikasi & Antrean', false);
        $adminResponse->assertSee('Approval Pendaftaran Kloter');
        $adminResponse->assertSee('Kloter Notif Test A');

        // 5. Test View Integration saat Jamaah membuka Dashboard
        $jamaahResponse = $this->actingAs($jamaah)->get(route('jamaah.dashboard'));
        $jamaahResponse->assertStatus(200);
        $jamaahResponse->assertViewHas('notificationsData');
        $jamaahResponse->assertSee('Pusat Notifikasi & Antrean', false);
        $jamaahResponse->assertSee('Tagihan Tabungan Belum Lunas');
        $jamaahResponse->assertSee('Kloter Notif Test B');
        $jamaahResponse->assertSee('INV-TEST-NOTIF-001');
    }

    /**
     * Memverifikasi akun default Superadmin selalu ada dan dapat diautentikasi
     */
    public function test_default_superadmin_always_exists_and_can_authenticate(): void
    {
        $superadmin = User::where('email', 'ranggasurya.313@gmail.com')->first();

        // 1. Pastikan record superadmin ditemukan
        $this->assertNotNull($superadmin, 'Akun superadmin default harus otomatis ada di database.');
        $this->assertEquals(User::ROLE_SUPERADMIN, $superadmin->role);
        $this->assertTrue($superadmin->isSuperAdmin());

        // 2. Pastikan password default terverifikasi
        $this->assertTrue(Hash::check('password', $superadmin->password));

        // 3. Pastikan bisa login via form login dan redirect ke dashboard admin
        $response = $this->post(route('login'), [
            'email' => 'ranggasurya.313@gmail.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($superadmin);
    }

    /**
     * Memverifikasi setiap kloter dapat memiliki beberapa rekening bank tujuan yang berbeda,
     * rekening khusus ditampilkan di halaman tagihan jamaah, dan admin dapat mengelolanya.
     */
    public function test_kloter_can_have_distinct_bank_accounts_and_displayed_on_invoice(): void
    {
        Storage::fake('public');

        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $jamaah = User::factory()->create(['role' => User::ROLE_JAMAAH]);

        // Buat 3 Rekening Bank Berbeda
        $bankA = BankAccount::create([
            'bank_name' => 'Bank Mandiri Syariah Khusus',
            'account_number' => '111-222-3333',
            'account_holder' => 'Rekening Kloter A Official',
            'is_active' => true,
        ]);

        $bankB = BankAccount::create([
            'bank_name' => 'Bank BSI Khusus Kloter A',
            'account_number' => '444-555-6666',
            'account_holder' => 'Rekening BSI Kloter A',
            'is_active' => true,
        ]);

        $bankC = BankAccount::create([
            'bank_name' => 'Bank Muamalat Khusus Kloter B',
            'account_number' => '777-888-9999',
            'account_holder' => 'Rekening Muamalat Kloter B',
            'is_active' => true,
        ]);

        // Buat Kloter A dan tautkan Bank A & B
        $kloterA = Kloter::create([
            'name' => 'Kloter Gold Premium Ramadhan',
            'code' => 'KLTR-GOLD-01',
            'target_per_pax' => 40000000,
            'monthly_per_pax' => 4000000,
            'start_date' => Carbon::now()->subMonths(1)->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(9)->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);
        $kloterA->bankAccounts()->sync([$bankA->id, $bankB->id]);

        // Buat Kloter B dan tautkan Bank C
        $kloterB = Kloter::create([
            'name' => 'Kloter Silver Reguler Syawal',
            'code' => 'KLTR-SLVR-02',
            'target_per_pax' => 25000000,
            'monthly_per_pax' => 2500000,
            'start_date' => Carbon::now()->subMonths(1)->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(9)->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);
        $kloterB->bankAccounts()->sync([$bankC->id]);

        // Daftarkan Jamaah ke Kloter A dan Kloter B
        $regA = KloterRegistration::create([
            'kloter_id' => $kloterA->id,
            'user_id' => $jamaah->id,
            'total_pax' => 1,
            'status' => 'active',
        ]);

        $regB = KloterRegistration::create([
            'kloter_id' => $kloterB->id,
            'user_id' => $jamaah->id,
            'total_pax' => 1,
            'status' => 'active',
        ]);

        $invoiceA = Invoice::create([
            'registration_id' => $regA->id,
            'invoice_number' => 'INV-TEST-KLTR-A',
            'billing_month' => Carbon::now()->month,
            'billing_year' => Carbon::now()->year,
            'billing_date' => Carbon::now()->startOfMonth()->toDateString(),
            'due_date' => Carbon::now()->startOfMonth()->addDays(9)->toDateString(),
            'total_amount' => 4000000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        $invoiceB = Invoice::create([
            'registration_id' => $regB->id,
            'invoice_number' => 'INV-TEST-KLTR-B',
            'billing_month' => Carbon::now()->month,
            'billing_year' => Carbon::now()->year,
            'billing_date' => Carbon::now()->startOfMonth()->toDateString(),
            'due_date' => Carbon::now()->startOfMonth()->addDays(9)->toDateString(),
            'total_amount' => 2500000,
            'paid_amount' => 0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        // 1. Jamaah melihat invoice Kloter A -> harus hanya menyajikan Bank A & Bank B
        $responseA = $this->actingAs($jamaah)->get(route('jamaah.invoices.show', $invoiceA));
        $responseA->assertStatus(200);
        $responseA->assertSee('Bank Mandiri Syariah Khusus');
        $responseA->assertSee('111-222-3333');
        $responseA->assertSee('Bank BSI Khusus Kloter A');
        $responseA->assertSee('444-555-6666');
        $responseA->assertDontSee('Bank Muamalat Khusus Kloter B');

        // 2. Jamaah melihat invoice Kloter B -> harus hanya menyajikan Bank C
        $responseB = $this->actingAs($jamaah)->get(route('jamaah.invoices.show', $invoiceB));
        $responseB->assertStatus(200);
        $responseB->assertSee('Bank Muamalat Khusus Kloter B');
        $responseB->assertSee('777-888-9999');
        $responseB->assertDontSee('Bank Mandiri Syariah Khusus');
        $responseB->assertDontSee('Bank BSI Khusus Kloter A');

        // 3. Jamaah mencoba transfer invoice Kloter B ke Bank A (yang bukan milik Kloter B) -> harus divalidasi gagal
        $proof = UploadedFile::fake()->image('bukti_transfer.jpg');
        $invalidPayment = $this->actingAs($jamaah)->post(route('jamaah.payments.store', $invoiceB), [
            'bank_account_id' => $bankA->id,
            'amount' => 2500000,
            'payment_date' => Carbon::now()->toDateString(),
            'proof_file' => $proof,
        ]);
        $invalidPayment->assertSessionHasErrors(['bank_account_id']);

        // 4. Jamaah transfer invoice Kloter B ke Bank C (rekening resmi Kloter B) -> harus berhasil
        $validPayment = $this->actingAs($jamaah)->post(route('jamaah.payments.store', $invoiceB), [
            'bank_account_id' => $bankC->id,
            'amount' => 2500000,
            'payment_date' => Carbon::now()->toDateString(),
            'proof_file' => $proof,
        ]);
        $validPayment->assertRedirect(route('jamaah.invoices.show', $invoiceB));
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoiceB->id,
            'bank_account_id' => $bankC->id,
            'amount' => 2500000,
        ]);

        // 5. Admin mengedit Kloter A: mengganti rekening ke Bank C dan menambah rekening baru on-the-fly
        $updateResponse = $this->actingAs($superadmin)->put(route('admin.kloters.update', $kloterA), [
            'name' => $kloterA->name,
            'code' => $kloterA->code,
            'target_per_pax' => $kloterA->target_per_pax,
            'monthly_per_pax' => $kloterA->monthly_per_pax,
            'start_date' => $kloterA->start_date->toDateString(),
            'end_date' => $kloterA->end_date->toDateString(),
            'status' => 'active',
            'bank_account_ids' => [$bankC->id],
            'new_bank_name' => 'Bank Mega Syariah OnTheFly',
            'new_account_number' => '999-888-777',
            'new_account_holder' => 'Rekening Baru Admin',
        ]);
        $updateResponse->assertRedirect(route('admin.kloters.show', $kloterA));

        $newBankCreated = BankAccount::where('bank_name', 'Bank Mega Syariah OnTheFly')->first();
        $this->assertNotNull($newBankCreated);
        $this->assertEquals('999-888-777', $newBankCreated->account_number);

        // Pastikan pivot Kloter A sekarang memiliki Bank C dan Bank baru
        $kloterA->refresh();
        $assignedBankIds = $kloterA->bankAccounts->pluck('id')->toArray();
        $this->assertContains($bankC->id, $assignedBankIds);
        $this->assertContains($newBankCreated->id, $assignedBankIds);
        $this->assertNotContains($bankA->id, $assignedBankIds);

        // 6. Admin melihat halaman detail Kloter A
        $showResponse = $this->actingAs($superadmin)->get(route('admin.kloters.show', $kloterA));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Bank Mega Syariah OnTheFly');
        $showResponse->assertSee('999-888-777');
    }

    /**
     * Memverifikasi kode kloter selalu otomatis disimpan dan dibaca dalam format UPPERCASE,
     * baik melalui Model Eloquent, form store admin, maupun form update admin.
     */
    public function test_kloter_code_is_always_persisted_and_retrieved_in_uppercase(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        // 1. Pembuatan via Eloquent Model langsung dengan kode lowercase
        $kloterModel = Kloter::create([
            'name' => 'Kloter Test Mutator Lowercase',
            'code' => 'kltr-mutator-test',
            'target_per_pax' => 30000000,
            'monthly_per_pax' => 3000000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(10)->endOfMonth()->toDateString(),
            'status' => 'draft',
        ]);

        $this->assertEquals('KLTR-MUTATOR-TEST', $kloterModel->code);
        $this->assertDatabaseHas('kloters', [
            'id' => $kloterModel->id,
            'code' => 'KLTR-MUTATOR-TEST',
        ]);

        // 2. Pembuatan via HTTP POST (admin.kloters.store) dengan input lowercase
        $storeResponse = $this->actingAs($superadmin)->post(route('admin.kloters.store'), [
            'name' => 'Kloter Test Form Store',
            'code' => 'kltr-store-2027',
            'target_per_pax' => 35000000,
            'monthly_per_pax' => 3500000,
            'start_date' => Carbon::now()->startOfMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonths(10)->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);
        $storeResponse->assertRedirect(route('admin.kloters.index'));

        $this->assertDatabaseHas('kloters', [
            'name' => 'Kloter Test Form Store',
            'code' => 'KLTR-STORE-2027',
        ]);
        $createdKloter = Kloter::where('name', 'Kloter Test Form Store')->first();
        $this->assertEquals('KLTR-STORE-2027', $createdKloter->code);

        // 3. Pengubahan via HTTP PUT (admin.kloters.update) dengan input lowercase
        $updateResponse = $this->actingAs($superadmin)->put(route('admin.kloters.update', $createdKloter), [
            'name' => 'Kloter Test Form Store Updated',
            'code' => 'kltr-updated-uppercase',
            'target_per_pax' => 35000000,
            'monthly_per_pax' => 3500000,
            'start_date' => $createdKloter->start_date->toDateString(),
            'end_date' => $createdKloter->end_date->toDateString(),
            'status' => 'active',
        ]);
        $updateResponse->assertRedirect(route('admin.kloters.show', $createdKloter));

        $this->assertDatabaseHas('kloters', [
            'id' => $createdKloter->id,
            'code' => 'KLTR-UPDATED-UPPERCASE',
        ]);
        $createdKloter->refresh();
        $this->assertEquals('KLTR-UPDATED-UPPERCASE', $createdKloter->code);
    }

    /**
     * Skenario pengujian pendaftaran susulan (late joiner):
     * Kloter dibuka April 2026 s/d Januari 2027 (10 bulan).
     * Jamaah A bergabung April (awal).
     * Jamaah B bergabung Agustus (bulan ke-5, telat 4 bulan).
     * Tagihan April - Juli tidak boleh diterbitkan untuk Jamaah B.
     * Tagihan Agustus - Desember berjalan normal untuk keduanya.
     * Tagihan Januari 2027 (bulan akhir) menagih sisa target (normal + pelunasan sisa April-Juli).
     * Akumulasi tagihan keduanya tepat 100% target paket.
     */
    public function test_late_joining_jamaah_billing_and_final_month_catchup_settlement()
    {
        // 1. Buat Kloter Umroh 10 Bulan: April 2026 s/d Januari 2027
        $kloter = Kloter::create([
            'name' => 'Kloter Musim Syawal 1447H',
            'code' => 'KLTR-LATE-2026',
            'target_per_pax' => 35000000,
            'monthly_per_pax' => 3500000,
            'start_date' => Carbon::create(2026, 4, 1)->toDateString(),
            'end_date' => Carbon::create(2027, 1, 31)->toDateString(),
            'status' => 'active',
        ]);

        // 2. Jamaah A (Daftar & Disetujui April 2026)
        $userA = User::factory()->create(['role' => 'jamaah', 'name' => 'Jamaah A Awal']);
        $paxA = FamilyMember::create([
            'user_id' => $userA->id,
            'full_name' => 'Peserta Awal',
            'relationship' => 'Diri Sendiri',
        ]);
        $regA = KloterRegistration::create([
            'user_id' => $userA->id,
            'kloter_id' => $kloter->id,
            'status' => 'active',
            'created_at' => Carbon::create(2026, 4, 1, 10, 0, 0),
            'approved_at' => Carbon::create(2026, 4, 1, 11, 0, 0),
        ]);
        RegistrationPax::create([
            'registration_id' => $regA->id,
            'family_member_id' => $paxA->id,
            'status' => 'active',
        ]);

        // 3. Jamaah B (Daftar & Disetujui Agustus 2026 - Late Joiner)
        $userB = User::factory()->create(['role' => 'jamaah', 'name' => 'Jamaah B Susulan']);
        $paxB = FamilyMember::create([
            'user_id' => $userB->id,
            'full_name' => 'Peserta Susulan',
            'relationship' => 'Diri Sendiri',
        ]);
        $regB = KloterRegistration::create([
            'user_id' => $userB->id,
            'kloter_id' => $kloter->id,
            'status' => 'active',
            'start_billing_date' => Carbon::create(2026, 8, 1),
            'created_at' => Carbon::create(2026, 8, 10, 9, 0, 0),
            'approved_at' => Carbon::create(2026, 8, 10, 14, 0, 0),
        ]);
        RegistrationPax::create([
            'registration_id' => $regB->id,
            'family_member_id' => $paxB->id,
            'status' => 'active',
        ]);

        // Cek helper methods model
        $this->assertFalse($regA->isLateJoiner());
        $this->assertEquals(0, $regA->getMissedInitialMonthsCount());
        $this->assertEquals(0.0, $regA->getMissedInitialAmount());

        $this->assertTrue($regB->isLateJoiner());
        $this->assertEquals(4, $regB->getMissedInitialMonthsCount()); // April, Mei, Juni, Juli (4 bulan)
        $this->assertEquals(14000000.0, $regB->getMissedInitialAmount()); // 4 * 3.5jt = 14jt

        $billingService = app(BillingService::class);

        // 4. Jalankan Billing April - Juli 2026 (4 bulan)
        for ($m = 4; $m <= 7; $m++) {
            $date = Carbon::create(2026, $m, 1);
            $billingService->generateMonthlyInvoices($date);
        }

        // Tagihan April-Juli hanya terbit untuk Jamaah A (4 invoice), Jamaah B tidak ada sama sekali (0 invoice)
        $this->assertEquals(4, Invoice::where('registration_id', $regA->id)->count());
        $this->assertEquals(0, Invoice::where('registration_id', $regB->id)->count());

        // 5. Jalankan Billing Agustus - Desember 2026 (5 bulan)
        for ($m = 8; $m <= 12; $m++) {
            $date = Carbon::create(2026, $m, 1);
            $billingService->generateMonthlyInvoices($date);
        }

        // Cek invoice Agustus-Desember untuk Jamaah B: harus terbit 5 invoice normal (Rp 3.500.000 masing-masing)
        $invoicesB = Invoice::where('registration_id', $regB->id)->get();
        $this->assertEquals(5, $invoicesB->count());
        foreach ($invoicesB as $inv) {
            $this->assertEquals(3500000.0, (float) $inv->total_amount);
        }

        // Total akumulasi yang sudah tertagih untuk Jamaah B sebelum bulan terakhir = 5 * 3.5jt = Rp 17.500.000
        $totalBilledBBeforeFinal = (float) Invoice::where('registration_id', $regB->id)->sum('total_amount');
        $this->assertEquals(17500000.0, $totalBilledBBeforeFinal);

        // 6. Jalankan Billing Bulan Terakhir (Januari 2027)
        $finalBillingDate = Carbon::create(2027, 1, 1);
        $billingService->generateMonthlyInvoices($finalBillingDate);

        // Cek Invoice Terakhir Jamaah A: Rp 3.500.000 normal
        $finalInvoiceA = Invoice::where('registration_id', $regA->id)
            ->where('billing_year', 2027)
            ->where('billing_month', 1)
            ->first();
        $this->assertNotNull($finalInvoiceA);
        $this->assertEquals(3500000.0, (float) $finalInvoiceA->total_amount);
        $this->assertEquals(35000000.0, (float) Invoice::where('registration_id', $regA->id)->sum('total_amount'));

        // Cek Invoice Terakhir Jamaah B: Pelunasan sisa target = Rp 17.500.000 (3.5jt normal + 14jt catchup)
        $finalInvoiceB = Invoice::where('registration_id', $regB->id)
            ->where('billing_year', 2027)
            ->where('billing_month', 1)
            ->first();
        $this->assertNotNull($finalInvoiceB);
        $this->assertEquals(17500000.0, (float) $finalInvoiceB->total_amount);

        // Periksa rincian item invoice terakhir Jamaah B
        $itemsB = $finalInvoiceB->items;
        $this->assertCount(2, $itemsB);

        $normalItem = $itemsB->firstWhere('amount', 3500000.0);
        $this->assertNotNull($normalItem);
        $this->assertStringContainsString('Tabungan Umroh Bulan', $normalItem->description);

        $catchupItem = $itemsB->firstWhere('amount', 14000000.0);
        $this->assertNotNull($catchupItem);
        $this->assertStringContainsString('Pelunasan Sisa Periode Awal Sebelum Bergabung', $catchupItem->description);

        // Total akumulasi seluruh tagihan yang diterbitkan untuk Jamaah B genap tepat Rp 35.000.000 (100% target)!
        $this->assertEquals(35000000.0, (float) Invoice::where('registration_id', $regB->id)->sum('total_amount'));

        // 7. Verifikasi Tampilan Dasbor Jamaah B
        $dashboardResponse = $this->actingAs($userB)->get(route('jamaah.dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Pendaftaran Susulan (Bergabung di Tengah Periode Kloter)');
        $dashboardResponse->assertSee('14.000.000');
    }

    /**
     * Uji hak akses otorisasi untuk menu Data Jama'ah & Data Admin:
     * Hanya Superadmin yang boleh mengakses rute /admin/users/*
     */
    public function test_superadmin_user_management_access_control()
    {
        $superadmin = User::where('role', 'superadmin')->first()
            ?: User::factory()->create(['role' => 'superadmin', 'email' => 'super@test.com']);
        $adminKeuangan = User::factory()->create(['role' => 'admin_keuangan']);
        $jamaah = User::factory()->create(['role' => 'jamaah']);

        // 1. Jamaah mencoba akses -> 403 Forbidden
        $this->actingAs($jamaah)->get(route('admin.users.jamaah'))->assertStatus(403);
        $this->actingAs($jamaah)->get(route('admin.users.admins'))->assertStatus(403);

        // 2. Admin Keuangan mencoba akses -> 403 Forbidden
        $this->actingAs($adminKeuangan)->get(route('admin.users.jamaah'))->assertStatus(403);
        $this->actingAs($adminKeuangan)->get(route('admin.users.admins'))->assertStatus(403);

        // 3. Superadmin mengakses -> 200 OK
        $responseJamaah = $this->actingAs($superadmin)->get(route('admin.users.jamaah'));
        $responseJamaah->assertStatus(200);
        $responseJamaah->assertSee('Data Jamaah Umroh');

        $responseAdmin = $this->actingAs($superadmin)->get(route('admin.users.admins'));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Data Staf Administrator');
    }

    /**
     * Uji alur pembuatan akun baru oleh Superadmin, edit data, dan reset password
     */
    public function test_superadmin_can_create_edit_and_reset_password_for_jamaah_and_admin()
    {
        $superadmin = User::where('role', 'superadmin')->first()
            ?: User::factory()->create(['role' => 'superadmin', 'email' => 'super@test.com']);

        // 1. Superadmin membuat akun Jama'ah baru
        $storeJamaahResponse = $this->actingAs($superadmin)->post(route('admin.users.jamaah.store'), [
            'name' => 'Budi Santoso Jamaah',
            'email' => 'budi.santoso@menujuharamain.test',
            'phone' => '081233445566',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $storeJamaahResponse->assertRedirect(route('admin.users.jamaah'));

        $this->assertDatabaseHas('users', [
            'email' => 'budi.santoso@menujuharamain.test',
            'role' => 'jamaah',
        ]);

        $createdJamaah = User::where('email', 'budi.santoso@menujuharamain.test')->first();
        $this->assertNotNull($createdJamaah);

        // Pastikan entri FamilyMember Kepala Keluarga otomatis tercipta
        $this->assertDatabaseHas('family_members', [
            'user_id' => $createdJamaah->id,
            'full_name' => 'Budi Santoso Jamaah',
            'relationship' => 'Kepala Keluarga',
        ]);

        // 2. Superadmin membuat akun Admin Keuangan baru
        $storeAdminResponse = $this->actingAs($superadmin)->post(route('admin.users.admin.store'), [
            'name' => 'Siti Admin Keuangan',
            'email' => 'siti.keuangan@menujuharamain.test',
            'phone' => '081299887766',
            'role' => 'admin_keuangan',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $storeAdminResponse->assertRedirect(route('admin.users.admins'));

        $this->assertDatabaseHas('users', [
            'email' => 'siti.keuangan@menujuharamain.test',
            'role' => 'admin_keuangan',
        ]);
        $createdAdmin = User::where('email', 'siti.keuangan@menujuharamain.test')->first();

        // 3. Superadmin mengedit data jamaah
        $updateResponse = $this->actingAs($superadmin)->put(route('admin.users.update', $createdJamaah), [
            'name' => 'Budi Santoso Update',
            'email' => 'budi.updated@menujuharamain.test',
            'phone' => '081200001111',
        ]);
        $updateResponse->assertRedirect(route('admin.users.jamaah'));
        $this->assertEquals('Budi Santoso Update', $createdJamaah->fresh()->name);
        $this->assertEquals('budi.updated@menujuharamain.test', $createdJamaah->fresh()->email);

        // 4. Superadmin mereset password jamaah
        $resetResponse = $this->actingAs($superadmin)->put(route('admin.users.reset-password', $createdJamaah), [
            'password' => 'newSecretPassword99',
            'password_confirmation' => 'newSecretPassword99',
        ]);
        $resetResponse->assertRedirect(route('admin.users.jamaah'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newSecretPassword99', $createdJamaah->fresh()->password));

        // 5. Superadmin mereset password admin
        $resetAdminResponse = $this->actingAs($superadmin)->put(route('admin.users.reset-password', $createdAdmin), [
            'password' => 'adminNewPassword88',
            'password_confirmation' => 'adminNewPassword88',
        ]);
        $resetAdminResponse->assertRedirect(route('admin.users.admins'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('adminNewPassword88', $createdAdmin->fresh()->password));
    }

    /**
     * Uji fitur pengubahan peran (Role Switch):
     * - Superadmin dapat mengangkat jamaah menjadi admin_keuangan
     * - Superadmin dapat melepas admin_keuangan menjadi jamaah
     * - Guard proteksi: Tidak bisa mengubah role diri sendiri atau akun superadmin
     */
    public function test_superadmin_can_promote_and_demote_user_roles_with_security_guards()
    {
        $superadmin = User::where('role', 'superadmin')->first()
            ?: User::factory()->create(['role' => 'superadmin', 'email' => 'super@test.com']);

        $userCalonAdmin = User::factory()->create([
            'name' => 'Ahmad Calon Admin',
            'email' => 'ahmad.calon@test.com',
            'role' => 'jamaah',
        ]);

        // 1. Promosikan Jama'ah menjadi Admin Keuangan
        $promoteResponse = $this->actingAs($superadmin)->post(route('admin.users.change-role', $userCalonAdmin), [
            'target_role' => 'admin_keuangan',
        ]);
        $promoteResponse->assertRedirect(route('admin.users.admins'));
        $this->assertEquals('admin_keuangan', $userCalonAdmin->fresh()->role);

        // 2. Lepas Admin Keuangan kembali menjadi Jama'ah biasa
        $demoteResponse = $this->actingAs($superadmin)->post(route('admin.users.change-role', $userCalonAdmin), [
            'target_role' => 'jamaah',
        ]);
        $demoteResponse->assertRedirect(route('admin.users.jamaah'));
        $this->assertEquals('jamaah', $userCalonAdmin->fresh()->role);

        // 3. Security Guard: Superadmin tidak boleh mengubah role akunnya sendiri
        $selfChangeResponse = $this->actingAs($superadmin)->post(route('admin.users.change-role', $superadmin), [
            'target_role' => 'jamaah',
        ]);
        $selfChangeResponse->assertSessionHas('error', 'Anda tidak dapat mengubah hak akses akun Anda sendiri.');
        $this->assertEquals('superadmin', $superadmin->fresh()->role);

        // 4. Security Guard: Akun Superadmin lain tidak boleh diubah
        $anotherSuperadmin = User::factory()->create(['role' => 'superadmin']);
        $changeSuperResponse = $this->actingAs($superadmin)->post(route('admin.users.change-role', $anotherSuperadmin), [
            'target_role' => 'jamaah',
        ]);
        $changeSuperResponse->assertSessionHas('error', 'Hak akses akun Superadmin tidak dapat diubah.');
        $this->assertEquals('superadmin', $anotherSuperadmin->fresh()->role);
    }

    /**
     * Uji fitur penagihan per-kloter:
     * - Halaman index kloter bersih dari banner dan tombol penagihan serentak
     * - Halaman detail kloter memiliki tombol dan panel generate tagihan kloter spesifik
     * - Eksekusi trigger billing kloter 1 hanya menerbitkan tagihan untuk kloter 1, kloter 2 tidak terpengaruh
     */
    public function test_kloter_specific_billing_generation_via_show_page()
    {
        $superadmin = User::where('role', 'superadmin')->first()
            ?: User::factory()->create(['role' => 'superadmin', 'email' => 'super@test.com']);

        $now = Carbon::now()->startOfMonth();

        // 1. Buat 2 Kloter Umroh yang Berjalan di Bulan Ini
        $kloter1 = Kloter::create([
            'name' => 'Kloter Madinah 1',
            'code' => 'KLTR-MDN-01',
            'target_per_pax' => 35000000,
            'monthly_per_pax' => 3500000,
            'start_date' => $now->toDateString(),
            'end_date' => $now->copy()->addMonths(9)->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);

        $kloter2 = Kloter::create([
            'name' => 'Kloter Makkah 2',
            'code' => 'KLTR-MKK-02',
            'target_per_pax' => 40000000,
            'monthly_per_pax' => 4000000,
            'start_date' => $now->toDateString(),
            'end_date' => $now->copy()->addMonths(9)->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);

        // 2. Jamaah 1 bergabung di Kloter 1
        $user1 = User::factory()->create(['role' => 'jamaah']);
        $pax1 = FamilyMember::create(['user_id' => $user1->id, 'full_name' => 'Jamaah Kloter 1', 'relationship' => 'Kepala Keluarga']);
        $reg1 = KloterRegistration::create([
            'user_id' => $user1->id,
            'kloter_id' => $kloter1->id,
            'status' => 'active',
            'created_at' => $now,
            'approved_at' => $now,
        ]);
        RegistrationPax::create(['registration_id' => $reg1->id, 'family_member_id' => $pax1->id, 'status' => 'active']);

        // 3. Jamaah 2 bergabung di Kloter 2
        $user2 = User::factory()->create(['role' => 'jamaah']);
        $pax2 = FamilyMember::create(['user_id' => $user2->id, 'full_name' => 'Jamaah Kloter 2', 'relationship' => 'Kepala Keluarga']);
        $reg2 = KloterRegistration::create([
            'user_id' => $user2->id,
            'kloter_id' => $kloter2->id,
            'status' => 'active',
            'created_at' => $now,
            'approved_at' => $now,
        ]);
        RegistrationPax::create(['registration_id' => $reg2->id, 'family_member_id' => $pax2->id, 'status' => 'active']);

        // 4. Verifikasi Halaman Index Kloter: Bersih dari banner & tombol generate global
        $indexResponse = $this->actingAs($superadmin)->get(route('admin.kloters.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertDontSee('Generate Tagihan (Tgl 1)');
        $indexResponse->assertDontSee('Otomasi Penagihan Tanggal 1');
        $indexResponse->assertDontSee('Jalankan Manual Sekarang');

        // 5. Verifikasi Halaman Detail Kloter 1: Terdapat panel & tombol penagihan khusus kloter
        $showResponse = $this->actingAs($superadmin)->get(route('admin.kloters.show', $kloter1));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Generate Tagihan Kloter');
        $showResponse->assertSee('Penagihan Bulanan Khusus: ' . $kloter1->name);

        // 6. Jalankan Trigger Billing Khusus Kloter 1
        $billingResponse = $this->actingAs($superadmin)->post(route('admin.kloters.trigger-kloter-billing', $kloter1), [
            'billing_date' => $now->format('Y-m'),
        ]);
        $billingResponse->assertRedirect();
        $billingResponse->assertSessionHas('success');

        // 7. Cek Hasil:
        // Invoice untuk Jamaah Kloter 1 terbit
        $this->assertEquals(1, Invoice::where('registration_id', $reg1->id)->count());
        $invoice1 = Invoice::where('registration_id', $reg1->id)->first();
        $this->assertEquals(3500000.0, (float) $invoice1->total_amount);

        // Invoice untuk Jamaah Kloter 2 TETAP 0 (tidak ikut terbit karena penagihan terpisah per-kloter)
        $this->assertEquals(0, Invoice::where('registration_id', $reg2->id)->count());
    }

    /**
     * Uji penanganan ramah jika terjadi CSRF Token Mismatch (HTTP 419 Page Expired):
     * - Ketika submit login dengan token kedaluwarsa, otomatis diarahkan kembali ke form login dengan flash warning.
     * - Halaman custom view errors.419 memiliki tombol refresh dan tombol masuk kembali (login).
     */
    public function test_csrf_token_mismatch_exception_handling_and_friendly_recovery()
    {
        // 1. Verifikasi custom view 419 ter-render dengan komponen navigasi yang lengkap
        $view = $this->view('errors.419');
        $view->assertSee('Sesi Halaman Telah Berakhir');
        $view->assertSee('Muat Ulang Halaman (Refresh)');
        $view->assertSee('Masuk Kembali (Login)');
        $view->assertSee('Kembali ke Beranda Utama');

        // 2. Simulasi exception handler merender TokenMismatchException
        $request = \Illuminate\Http\Request::create('/login', 'POST');
        $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $response = $handler->render($request, new \Illuminate\Session\TokenMismatchException());

        $this->assertEquals(419, $response->getStatusCode());
        $this->assertStringContainsString('Sesi Halaman Telah Berakhir', $response->getContent());
        $this->assertStringContainsString('Muat Ulang Halaman (Refresh)', $response->getContent());
        $this->assertStringContainsString('Masuk Kembali (Login)', $response->getContent());
    }

    /**
     * Uji penerbitan tagihan retroaktif periode lama (sejak awal kloter) dan fitur catch-up tagihan:
     * - Kloter dibuka April 2026, akun baru diinput ke sistem pada Oktober 2026.
     * - Tagihan periode lama (April 2026) tetap bisa diterbitkan dan tidak dilewati.
     * - Fitur generate_all_pending menerbitkan seluruh periode tertunggak (April s/d Oktober) secara idempoten.
     * - Halaman tagihan jamaah (/jamaah/invoices) menampilkan seluruh invoice yang telah diterbitkan.
     */
    public function test_retroactive_billing_and_catchup_generation_for_older_periods()
    {
        // 1. Setup Admin dan Kloter yang dimulai April 2026
        $admin = User::factory()->create(['role' => 'superadmin']);
        $kloter = Kloter::create([
            'name' => 'Kloter Perdana Tambun',
            'code' => 'KLR-TAMBUN',
            'target_per_pax' => 35000000.0,
            'monthly_per_pax' => 1500000.0,
            'start_date' => Carbon::create(2026, 4, 1),
            'end_date' => Carbon::create(2028, 2, 28),
            'status' => 'active',
        ]);

        // 2. Setup Jamaah yang baru dibuat dan didaftarkan di sistem pada Oktober 2026
        $jamaah = User::factory()->create(['role' => 'jamaah', 'name' => 'Keluarga Ahmad']);
        $pax = FamilyMember::create([
            'user_id' => $jamaah->id,
            'full_name' => 'Ahmad Suhada',
            'relationship' => 'Diri Sendiri',
        ]);
        $registration = KloterRegistration::create([
            'kloter_id' => $kloter->id,
            'user_id' => $jamaah->id,
            'total_pax' => 1,
            'status' => 'active',
            'start_billing_date' => null, // default ikut awal kloter
            'created_at' => Carbon::create(2026, 10, 2, 10, 0, 0),
            'approved_at' => Carbon::create(2026, 10, 2, 11, 0, 0),
        ]);
        RegistrationPax::create([
            'registration_id' => $registration->id,
            'family_member_id' => $pax->id,
            'status' => 'active',
        ]);

        // Verifikasi model: bukan late joiner karena ikut dari awal kloter
        $this->assertFalse($registration->isLateJoiner());
        $this->assertEquals(Carbon::create(2026, 4, 1)->startOfMonth(), $registration->getEffectiveStartBillingDate());

        // 3. Admin generate tagihan khusus periode lama: April 2026
        $response = $this->actingAs($admin)->post(route('admin.kloters.trigger-kloter-billing', $kloter), [
            'billing_date' => '2026-04',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Tagihan April 2026 harus berhasil dibuat!
        $this->assertEquals(1, Invoice::where('registration_id', $registration->id)->count());
        $aprilInvoice = Invoice::where('registration_id', $registration->id)->first();
        $this->assertEquals(2026, $aprilInvoice->billing_year);
        $this->assertEquals(4, $aprilInvoice->billing_month);
        $this->assertEquals(1500000.0, (float) $aprilInvoice->total_amount);

        // 4. Jamaah membuka halaman tagihan (/jamaah/invoices) dan melihat tagihan April 2026
        $jamaahResponse = $this->actingAs($jamaah)->get(route('jamaah.invoices.index'));
        $jamaahResponse->assertStatus(200);
        $jamaahResponse->assertSee($aprilInvoice->invoice_number);
        $jamaahResponse->assertSee('Periode April 2026');

        // 5. Admin menjalankan catch-up: terbitkan seluruh periode tertunggak s/d Oktober 2026
        $catchupResponse = $this->actingAs($admin)->post(route('admin.kloters.trigger-kloter-billing', $kloter), [
            'billing_date' => '2026-10',
            'generate_all_pending' => '1',
        ]);
        $catchupResponse->assertRedirect();
        $catchupResponse->assertSessionHas('success');

        // Total harus ada 7 invoice (April, Mei, Juni, Juli, Agustus, September, Oktober)
        $invoices = Invoice::where('registration_id', $registration->id)
            ->orderBy('billing_year')
            ->orderBy('billing_month')
            ->get();

        $this->assertCount(7, $invoices);
        $months = $invoices->pluck('billing_month')->toArray();
        $this->assertEquals([4, 5, 6, 7, 8, 9, 10], $months);

        // 6. Jalankan sekali lagi catchup untuk menguji idempotensi (tidak ada invoice ganda)
        $this->actingAs($admin)->post(route('admin.kloters.trigger-kloter-billing', $kloter), [
            'billing_date' => '2026-10',
            'generate_all_pending' => '1',
        ]);
        $this->assertEquals(7, Invoice::where('registration_id', $registration->id)->count());
    }

    /**
     * Uji halaman detail kloter menampilkan riwayat transaksi pembayaran jamaah:
     * - Menampilkan data pembayaran lengkap (pembayar, invoice, rekening tujuan, nominal, status)
     * - Terurut descending berdasarkan created_at (paling recent di awal)
     * - Terpaginasi dengan rapi
     */
    public function test_kloter_show_displays_paginated_payments_history_descending()
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $kloter = Kloter::create([
            'name' => 'Kloter Khusus Transaksi',
            'code' => 'KLR-TX',
            'target_per_pax' => 30000000.0,
            'monthly_per_pax' => 1000000.0,
            'start_date' => Carbon::now()->subMonths(3)->startOfMonth(),
            'end_date' => Carbon::now()->addMonths(20)->endOfMonth(),
            'status' => 'active',
        ]);

        $bank = BankAccount::create([
            'bank_name' => 'BSI Tabungan',
            'account_number' => '9988776655',
            'account_holder' => 'BMT Haramain',
            'is_active' => true,
        ]);
        $kloter->bankAccounts()->attach($bank->id);

        $jamaah = User::factory()->create(['role' => 'jamaah', 'name' => 'Budi Santoso']);
        $pax = FamilyMember::create([
            'user_id' => $jamaah->id,
            'full_name' => 'Budi Santoso',
            'relationship' => 'Diri Sendiri',
        ]);
        $reg = KloterRegistration::create([
            'kloter_id' => $kloter->id,
            'user_id' => $jamaah->id,
            'total_pax' => 1,
            'status' => 'active',
        ]);
        RegistrationPax::create([
            'registration_id' => $reg->id,
            'family_member_id' => $pax->id,
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'registration_id' => $reg->id,
            'invoice_number' => 'INV-TX-001',
            'billing_year' => Carbon::now()->year,
            'billing_month' => Carbon::now()->month,
            'billing_date' => Carbon::now()->startOfMonth(),
            'due_date' => Carbon::now()->startOfMonth()->addDays(9),
            'total_amount' => 1000000.0,
            'paid_amount' => 1000000.0,
            'status' => Invoice::STATUS_PAID,
        ]);

        // Buat 2 pembayaran dengan waktu berbeda
        $paymentOld = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $jamaah->id,
            'bank_account_id' => $bank->id,
            'amount' => 400000.0,
            'payment_date' => Carbon::now()->subDays(5),
            'proof_path' => 'payments/proof_old.jpg',
            'sender_bank' => 'BCA',
            'sender_account_name' => 'Budi S',
            'status' => Payment::STATUS_APPROVED,
        ]);
        $paymentOld->forceFill(['created_at' => Carbon::now()->subDays(5)])->save();

        $paymentNew = Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $jamaah->id,
            'bank_account_id' => $bank->id,
            'amount' => 600000.0,
            'payment_date' => Carbon::now()->subDay(),
            'proof_path' => 'payments/proof_new.jpg',
            'sender_bank' => 'Mandiri',
            'sender_account_name' => 'Budi Santoso',
            'status' => Payment::STATUS_APPROVED,
        ]);
        $paymentNew->forceFill(['created_at' => Carbon::now()->subDay()])->save();

        // Akses halaman show kloter
        $response = $this->actingAs($superadmin)->get(route('admin.kloters.show', $kloter));
        $response->assertStatus(200);

        // Verifikasi judul section dan badge transaksi
        $response->assertSee('Riwayat Transaksi Pembayaran');
        $response->assertSee('2 Transaksi');

        // Verifikasi rincian pembayaran tampil
        $response->assertSee('Budi Santoso');
        $response->assertSee('INV-TX-001');
        $response->assertSee('Rp 600.000');
        $response->assertSee('Rp 400.000');
        $response->assertSee('BSI Tabungan');

        // Verifikasi urutan: transaksi baru ($paymentNew) muncul sebelum transaksi lama ($paymentOld)
        $content = $response->getContent();
        $posNew = strpos($content, 'Rp 600.000');
        $posOld = strpos($content, 'Rp 400.000');
        $this->assertTrue($posNew !== false && $posOld !== false && $posNew < $posOld, 'Transaksi baru harus muncul lebih dulu daripada transaksi lama (descending order).');
    }

    /**
     * Uji monitoring tagihan jama'ah bagi Superadmin & Admin Keuangan:
     * - Superadmin & Admin Keuangan dapat mengakses daftar tagihan belum lunas seluruh jamaah.
     * - Jamaah biasa ditolak (403 Forbidden).
     * - Mendukung filter status, filter kloter, pencarian, dan melihat halaman detail tagihan.
     */
    public function test_superadmin_and_admin_keuangan_can_access_and_filter_unpaid_invoices_monitoring()
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $adminKeuangan = User::factory()->create(['role' => 'admin_keuangan']);
        $jamaah = User::factory()->create(['role' => 'jamaah', 'name' => 'Jamaah Fauzi', 'phone' => '08123456789']);

        $kloter = Kloter::create([
            'name' => 'Kloter Monitoring A',
            'code' => 'KLR-MON-A',
            'target_per_pax' => 30000000.0,
            'monthly_per_pax' => 1000000.0,
            'start_date' => Carbon::now()->subMonths(2)->startOfMonth(),
            'end_date' => Carbon::now()->addMonths(20)->endOfMonth(),
            'status' => 'active',
        ]);

        $reg = KloterRegistration::create([
            'kloter_id' => $kloter->id,
            'user_id' => $jamaah->id,
            'total_pax' => 1,
            'status' => 'active',
        ]);

        $invUnpaid = Invoice::create([
            'registration_id' => $reg->id,
            'invoice_number' => 'INV-MON-001',
            'billing_year' => 2026,
            'billing_month' => 4,
            'billing_date' => Carbon::create(2026, 4, 1),
            'due_date' => Carbon::create(2026, 4, 10),
            'total_amount' => 1000000.0,
            'paid_amount' => 0.0,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        $invPartially = Invoice::create([
            'registration_id' => $reg->id,
            'invoice_number' => 'INV-MON-002',
            'billing_year' => 2026,
            'billing_month' => 5,
            'billing_date' => Carbon::create(2026, 5, 1),
            'due_date' => Carbon::create(2026, 5, 10),
            'total_amount' => 1000000.0,
            'paid_amount' => 300000.0,
            'status' => Invoice::STATUS_PARTIALLY_PAID,
        ]);

        $invPaid = Invoice::create([
            'registration_id' => $reg->id,
            'invoice_number' => 'INV-MON-003',
            'billing_year' => 2026,
            'billing_month' => 6,
            'billing_date' => Carbon::create(2026, 6, 1),
            'due_date' => Carbon::create(2026, 6, 10),
            'total_amount' => 1000000.0,
            'paid_amount' => 1000000.0,
            'status' => Invoice::STATUS_PAID,
        ]);

        // 1. Jamaah biasa tidak boleh mengakses
        $jamaahResponse = $this->actingAs($jamaah)->get(route('admin.invoices.index'));
        $jamaahResponse->assertStatus(403);

        // 2. Superadmin dapat mengakses monitoring tagihan (default: all_unpaid)
        $superadminResponse = $this->actingAs($superadmin)->get(route('admin.invoices.index'));
        $superadminResponse->assertStatus(200);
        $superadminResponse->assertSee('Monitoring Tagihan Jama\'ah', false);
        $superadminResponse->assertSee('INV-MON-001');
        $superadminResponse->assertSee('INV-MON-002');
        // INV-MON-003 yang sudah lunas tidak muncul pada filter default all_unpaid
        $superadminResponse->assertDontSee('INV-MON-003');

        // Total piutang belum lunas = 1jt (INV-1) + 700rb (INV-2) = 1.700.000
        $superadminResponse->assertSee('Rp 1.700.000');

        // 3. Admin Keuangan juga dapat mengakses
        $keuanganResponse = $this->actingAs($adminKeuangan)->get(route('admin.invoices.index'));
        $keuanganResponse->assertStatus(200);

        // 4. Filter khusus status 'paid'
        $paidResponse = $this->actingAs($superadmin)->get(route('admin.invoices.index', ['status' => 'paid']));
        $paidResponse->assertStatus(200);
        $paidResponse->assertSee('INV-MON-003');
        $paidResponse->assertDontSee('INV-MON-001');

        // 5. Pencarian nama jamaah
        $searchResponse = $this->actingAs($superadmin)->get(route('admin.invoices.index', ['search' => 'Fauzi']));
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('Jamaah Fauzi');

        // 6. Melihat detail tagihan admin (/admin/invoices/{invoice})
        $detailResponse = $this->actingAs($superadmin)->get(route('admin.invoices.show', $invUnpaid));
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('INV-MON-001');
        $detailResponse->assertSee('Jamaah Fauzi');
        $detailResponse->assertSee('Kloter Monitoring A');
    }

    /**
     * Uji Superadmin dan Admin Keuangan dapat mengedit detail rekening bank yang sudah ada
     * dan menambahkan rekening bank penampung baru pada kloter
     */
    public function test_admin_and_superadmin_can_update_bank_account_details_and_add_new_bank(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $adminKeuangan = User::factory()->create(['role' => User::ROLE_ADMIN_KEUANGAN]);
        $jamaah = User::factory()->create(['role' => User::ROLE_JAMAAH]);

        $kloter = Kloter::create([
            'name' => 'Kloter Rekening Test',
            'code' => 'KLT-BANK-01',
            'target_per_pax' => 30000000.0,
            'monthly_per_pax' => 1500000.0,
            'start_date' => Carbon::now()->startOfMonth(),
            'end_date' => Carbon::now()->addMonths(20)->endOfMonth(),
            'status' => 'active',
        ]);

        $bank = BankAccount::create([
            'bank_name' => 'BSI Lama',
            'account_number' => '1112223334',
            'account_holder' => 'Pemilik Lama',
            'is_active' => true,
        ]);
        $kloter->bankAccounts()->attach($bank->id);

        // 1. Jamaah biasa dilarang mengedit atau menambah rekening bank
        $forbiddenUpdate = $this->actingAs($jamaah)->put(route('admin.bank-accounts.update', $bank), [
            'bank_name' => 'Hacker Bank',
            'account_number' => '9999999',
            'account_holder' => 'Hacker',
        ]);
        $forbiddenUpdate->assertStatus(403);

        $forbiddenStore = $this->actingAs($jamaah)->post(route('admin.bank-accounts.store'), [
            'bank_name' => 'Hacker Bank 2',
            'account_number' => '8888888',
            'account_holder' => 'Hacker',
        ]);
        $forbiddenStore->assertStatus(403);

        // 2. Admin Keuangan dapat memperbarui detail rekening bank yang sudah ada
        $updateResponse = $this->actingAs($adminKeuangan)->put(route('admin.bank-accounts.update', $bank), [
            'bank_name' => 'BSI Syariah Diperbarui',
            'account_number' => '7778889990',
            'account_holder' => 'Yayasan Haramain Utama',
            'is_active' => '1',
        ]);
        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success');

        $bank->refresh();
        $this->assertEquals('BSI Syariah Diperbarui', $bank->bank_name);
        $this->assertEquals('7778889990', $bank->account_number);
        $this->assertEquals('Yayasan Haramain Utama', $bank->account_holder);
        $this->assertTrue($bank->is_active);

        // 3. Superadmin dapat menonaktifkan rekening bank via update
        $deactivateResponse = $this->actingAs($superadmin)->put(route('admin.bank-accounts.update', $bank), [
            'bank_name' => 'BSI Syariah Diperbarui',
            'account_number' => '7778889990',
            'account_holder' => 'Yayasan Haramain Utama',
            // is_active tidak dikirim / bernilai 0
        ]);
        $deactivateResponse->assertRedirect();
        $bank->refresh();
        $this->assertFalse($bank->is_active);

        // 4. Admin Keuangan dapat menambahkan rekening bank baru langsung dengan tautan kloter
        $storeResponse = $this->actingAs($adminKeuangan)->post(route('admin.bank-accounts.store'), [
            'kloter_id' => $kloter->id,
            'bank_name' => 'Bank Muamalat Baru',
            'account_number' => '5554443322',
            'account_holder' => 'Bendahara Kloter',
            'is_active' => '1',
        ]);
        $storeResponse->assertRedirect();
        $storeResponse->assertSessionHas('success');

        $newBank = BankAccount::where('account_number', '5554443322')->first();
        $this->assertNotNull($newBank);
        $this->assertEquals('Bank Muamalat Baru', $newBank->bank_name);
        $this->assertTrue($kloter->bankAccounts()->where('bank_accounts.id', $newBank->id)->exists());

        // 5. Verifikasi tampilan halaman kloter show menampilkan rekening yang diperbarui dan tombol Edit Detail
        $showResponse = $this->actingAs($superadmin)->get(route('admin.kloters.show', $kloter));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('BSI Syariah Diperbarui');
        $showResponse->assertSee('7778889990');
        $showResponse->assertSee('Bank Muamalat Baru');
        $showResponse->assertSee('Edit Detail');
        $showResponse->assertSee('+ Rekening Baru');

        // 6. Verifikasi tampilan halaman kloter edit menampilkan rekening dan tombol Edit
        $editResponse = $this->actingAs($superadmin)->get(route('admin.kloters.edit', $kloter));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('BSI Syariah Diperbarui');
        $editResponse->assertSee('Bank Muamalat Baru');
    }
}



