<?php
/**
 * File: app/Http/Controllers/Jamaah/PaymentController.php
 * Tujuan: Memproses unggah bukti transfer manual dan pengajuan konfirmasi pembayaran oleh jamaah dengan validasi urutan pelunasan tagihan dan rekening bank kloter
 * Dipakai Oleh: routes/web.php (POST /jamaah/invoices/{invoice}/payments)
 * Dependensi Utama: App\Models\Invoice, App\Models\BankAccount, App\Services\PaymentService, Auth, Request, Rule
 * Daftar Fungsi Utama: store()
 * Side Effect: Upload file bukti ke storage publik, insert record payments (status pending)
 */

namespace App\Http\Controllers\Jamaah;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice, PaymentService $paymentService): RedirectResponse
    {
        $user = Auth::user();

        // Otorisasi: hanya pemilik invoice yang boleh mengirim pembayaran
        if ($invoice->registration->user_id !== $user->id) {
            abort(403);
        }

        // Validasi urutan pelunasan: dilarang membayar jika tagihan periode sebelumnya belum lunas
        if ($unpaidPrev = $invoice->getUnpaidPreviousInvoice()) {
            return redirect()->route('jamaah.invoices.show', $invoice)
                ->with('error', sprintf(
                    'Pembayaran tagihan periode %s belum dapat diproses. Harap lunasi tagihan periode sebelumnya (%s) terlebih dahulu.',
                    $invoice->period_label,
                    $unpaidPrev->period_label
                ));
        }

        $kloter = $invoice->registration?->kloter;
        $allowedBankIds = ($kloter && $kloter->bankAccounts()->active()->exists())
            ? $kloter->bankAccounts()->active()->pluck('bank_accounts.id')->toArray()
            : BankAccount::active()->pluck('id')->toArray();

        $validated = $request->validate([
            'bank_account_id' => ['required', Rule::in($allowedBankIds)],
            'amount' => ['required', 'numeric', 'min:10000'],
            'payment_date' => ['required', 'date'],
            'sender_bank' => ['nullable', 'string', 'max:100'],
            'sender_account_name' => ['nullable', 'string', 'max:150'],
            'proof_file' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf,webp', 'max:10240'], // Maksimal 10MB
        ]);

        $paymentService->submitPayment(
            $user,
            $invoice,
            $validated,
            $request->file('proof_file')
        );

        return redirect()->route('jamaah.invoices.show', $invoice)
            ->with('success', 'Bukti transfer berhasil dikirim. Menunggu verifikasi admin keuangan.');
    }
}

