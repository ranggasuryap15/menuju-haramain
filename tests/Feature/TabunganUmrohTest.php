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
 *   - test_kloter_registration_with_selected_paxes()
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

        // 2. Verifikasi UI Jamaah: Halaman Daftar Tagihan (index) menampilkan badge Terkunci
        $this->actingAs($jamaah);
        $resIndex = $this->get(route('jamaah.invoices.index'));
        $resIndex->assertStatus(200);
        $resIndex->assertSee('Terkunci');

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
}

