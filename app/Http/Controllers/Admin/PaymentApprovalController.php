<?php
/**
 * File: app/Http/Controllers/Admin/PaymentApprovalController.php
 * Tujuan: Manajemen antrean verifikasi bukti transfer manual oleh admin keuangan dengan mitigasi self-approval
 * Dipakai Oleh: routes/web.php (/admin/payments, /admin/payments/{payment}/approve, /admin/payments/{payment}/reject)
 * Dependensi Utama: App\Models\Payment, App\Services\PaymentService, Auth, Request
 * Daftar Fungsi Utama: index(), show(), approve(), reject()
 * Side Effect: Update status payments, rekonsiliasi invoice, audit log verifikator
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', Payment::STATUS_PENDING);

        $paymentsQuery = Payment::query()
            ->with([
                'user',
                'bankAccount',
                'verifier',
                'invoice.registration.kloter',
            ]);

        if ($status !== 'all') {
            $paymentsQuery->where('status', $status);
        }

        $payments = $paymentsQuery->latest()->paginate(15)->withQueryString();

        $counts = [
            'pending' => Payment::where('status', Payment::STATUS_PENDING)->count(),
            'approved' => Payment::where('status', Payment::STATUS_APPROVED)->count(),
            'rejected' => Payment::where('status', Payment::STATUS_REJECTED)->count(),
        ];

        return view('admin.payments.index', compact('payments', 'status', 'counts'));
    }

    public function show(Payment $payment): View
    {
        $payment->load([
            'user',
            'bankAccount',
            'verifier',
            'invoice.registration.kloter',
            'invoice.items.registrationPax.familyMember',
        ]);

        return view('admin.payments.show', compact('payment'));
    }

    public function approve(Request $request, Payment $payment, PaymentService $paymentService): RedirectResponse
    {
        $admin = Auth::user();

        try {
            $paymentService->approvePayment($payment, $admin);

            return redirect()->route('admin.payments.index')
                ->with('success', 'Pembayaran sebesar Rp ' . number_format($payment->amount, 0, ',', '.') . ' berhasil disetujui (Approved).');
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, Payment $payment, PaymentService $paymentService): RedirectResponse
    {
        $request->validate([
            'admin_notes' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $admin = Auth::user();

        try {
            $paymentService->rejectPayment($payment, $admin, $request->input('admin_notes'));

            return redirect()->route('admin.payments.index')
                ->with('success', 'Pembayaran telah ditolak (Rejected) dan alasan dicatat.');
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}

