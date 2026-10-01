<?php
/**
 * File: app/Services/NotificationService.php
 * Tujuan: Service agregasi dan kalkulasi notifikasi pengguna (antrean approval pendaftaran kloter, approval bukti transfer, tagihan jatuh tempo/belum lunas, dan verifikasi) dengan memoization cache
 * Dipakai Oleh: AppServiceProvider (View Composer untuk layouts.app dan subviews)
 * Dependensi Utama: App\Models\User, App\Models\KloterRegistration, App\Models\Payment, App\Models\Invoice
 * Daftar Fungsi Utama: getNotificationsForUser(User $user): array, clearCache(?int $userId = null): void
 * Side Effect: Query DB tabel kloter_registrations, payments, dan invoices (dimemoize per request)
 */

namespace App\Services;

use App\Models\Invoice;
use App\Models\KloterRegistration;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * @var array<int, array> Cache notifikasi per user untuk request yang sedang berjalan
     */
    protected array $cachedNotifications = [];

    /**
     * Mengambil kumpulan notifikasi aktif untuk pengguna yang sedang login
     *
     * @return array{
     *     total_count: int,
     *     approval_registrations_count: int,
     *     approval_registrations: Collection,
     *     approval_payments_count: int,
     *     approval_payments: Collection,
     *     unpaid_invoices_count: int,
     *     unpaid_invoices: Collection,
     *     pending_payments_count: int,
     *     pending_payments: Collection,
     *     registration_alerts_count: int,
     *     registration_alerts: Collection
     * }
     */
    public function getNotificationsForUser(User $user): array
    {
        if (isset($this->cachedNotifications[$user->id])) {
            return $this->cachedNotifications[$user->id];
        }

        $isStaff = $user->isStaff();

        // 1. Notifikasi Approval untuk Superadmin dan Admin Keuangan
        $approvalRegistrations = collect();
        $approvalPayments = collect();

        if ($isStaff) {
            $approvalRegistrations = KloterRegistration::query()
                ->where('status', KloterRegistration::STATUS_PENDING)
                ->with(['user:id,name,email', 'kloter:id,name,code'])
                ->latest()
                ->take(8)
                ->get();

            $approvalPayments = Payment::query()
                ->where('status', Payment::STATUS_PENDING)
                ->with([
                    'user:id,name',
                    'bankAccount:id,bank_name,account_number',
                    'invoice:id,invoice_number,billing_year,billing_month,registration_id',
                    'invoice.registration.kloter:id,name,code',
                ])
                ->latest()
                ->take(8)
                ->get();
        }

        // 2. Notifikasi Tagihan Belum Lunas (Personal Jamaah / Dual-Role Staff)
        $activeRegistrationIds = KloterRegistration::query()
            ->where('user_id', $user->id)
            ->where('status', KloterRegistration::STATUS_ACTIVE)
            ->pluck('id');

        $unpaidInvoices = collect();
        if ($activeRegistrationIds->isNotEmpty()) {
            $unpaidInvoices = Invoice::query()
                ->whereIn('registration_id', $activeRegistrationIds)
                ->where('status', '!=', Invoice::STATUS_PAID)
                ->whereColumn('paid_amount', '<', 'total_amount')
                ->with(['registration.kloter:id,name,code'])
                ->orderBy('billing_year', 'asc')
                ->orderBy('billing_month', 'asc')
                ->take(8)
                ->get();
        }

        // 3. Notifikasi Pengajuan Pembayaran Sedang Diverifikasi (Personal)
        $pendingPayments = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', Payment::STATUS_PENDING)
            ->with([
                'invoice:id,invoice_number,billing_year,billing_month,registration_id',
                'invoice.registration.kloter:id,name,code',
            ])
            ->latest()
            ->take(5)
            ->get();

        // 4. Notifikasi Status Pendaftaran Kloter Jamaah (Menunggu approval / Ditolak)
        $registrationAlerts = KloterRegistration::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [KloterRegistration::STATUS_PENDING, KloterRegistration::STATUS_REJECTED])
            ->with(['kloter:id,name,code'])
            ->latest()
            ->take(5)
            ->get();

        $totalCount = $approvalRegistrations->count()
            + $approvalPayments->count()
            + $unpaidInvoices->count()
            + $pendingPayments->count()
            + $registrationAlerts->count();

        $result = [
            'total_count' => $totalCount,
            'approval_registrations_count' => $approvalRegistrations->count(),
            'approval_registrations' => $approvalRegistrations,
            'approval_payments_count' => $approvalPayments->count(),
            'approval_payments' => $approvalPayments,
            'unpaid_invoices_count' => $unpaidInvoices->count(),
            'unpaid_invoices' => $unpaidInvoices,
            'pending_payments_count' => $pendingPayments->count(),
            'pending_payments' => $pendingPayments,
            'registration_alerts_count' => $registrationAlerts->count(),
            'registration_alerts' => $registrationAlerts,
        ];

        return $this->cachedNotifications[$user->id] = $result;
    }

    /**
     * Membersihkan cache memoization
     */
    public function clearCache(?int $userId = null): void
    {
        if ($userId !== null) {
            unset($this->cachedNotifications[$userId]);
        } else {
            $this->cachedNotifications = [];
        }
    }
}
