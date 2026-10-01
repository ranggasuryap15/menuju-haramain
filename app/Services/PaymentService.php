<?php
/**
 * File: app/Services/PaymentService.php
 * Tujuan: Layanan manajemen pengajuan bukti transfer pembayaran dan alur persetujuan admin keuangan
 * Dipakai Oleh: PaymentController, AdminApprovalController
 * Dependensi Utama: App\Models\Payment, App\Models\Invoice, App\Models\User, App\Services\BillingService, Storage
 * Daftar Fungsi Utama: submitPayment(), approvePayment(), rejectPayment()
 * Side Effect: File upload I/O ke storage publik, write DB tabel payments, pembaruan invoice
 */

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        protected BillingService $billingService
    ) {}

    /**
     * Menyimpan transaksi pembayaran dan bukti transfer yang diunggah oleh peserta
     */
    public function submitPayment(User $user, Invoice $invoice, array $payload, UploadedFile $proofFile): Payment
    {
        // Simpan file bukti transfer ke storage publik dengan nama unik
        $path = $proofFile->store('payment-proofs/' . date('Y/m'), 'public');

        return Payment::create([
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'bank_account_id' => $payload['bank_account_id'],
            'amount' => $payload['amount'],
            'payment_date' => $payload['payment_date'],
            'proof_path' => $path,
            'sender_bank' => $payload['sender_bank'] ?? null,
            'sender_account_name' => $payload['sender_account_name'] ?? null,
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /**
     * Memverifikasi dan menyetujui bukti transfer
     * Dilengkapi mitigasi 'Separation of Duty': Admin dilarang menyetujui transaksi milik sendiri
     */
    public function approvePayment(Payment $payment, User $admin): void
    {
        if (!$payment->canBeApprovedBy($admin)) {
            throw ValidationException::withMessages([
                'approval' => 'Konflik kepentingan: Anda tidak diperbolehkan menyetujui bukti pembayaran dari akun Anda sendiri.',
            ]);
        }

        DB::transaction(function () use ($payment, $admin) {
            $payment->update([
                'status' => Payment::STATUS_APPROVED,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            // Sinkronkan status dan total terbayar invoice
            $this->billingService->recalculateInvoiceStatus($payment->invoice);
        });
    }

    /**
     * Menolak bukti pembayaran dengan mencantumkan alasan verifikasi
     */
    public function rejectPayment(Payment $payment, User $admin, string $reason): void
    {
        if (!$payment->canBeApprovedBy($admin)) {
            throw ValidationException::withMessages([
                'approval' => 'Konflik kepentingan: Anda tidak diperbolehkan memproses bukti pembayaran dari akun Anda sendiri.',
            ]);
        }

        DB::transaction(function () use ($payment, $admin, $reason) {
            $payment->update([
                'status' => Payment::STATUS_REJECTED,
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'admin_notes' => $reason,
            ]);

            // Sinkronkan kembali invoice
            $this->billingService->recalculateInvoiceStatus($payment->invoice);
        });
    }
}
