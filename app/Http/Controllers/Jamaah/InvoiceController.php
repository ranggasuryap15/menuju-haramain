<?php
/**
 * File: app/Http/Controllers/Jamaah/InvoiceController.php
 * Tujuan: Menampilkan daftar tagihan bulanan jama'ah dengan sistem tab terpisah (Tab 'unpaid' urut ASC / bulan pertama belum bayar teratas, Tab 'paid' urut DESC / bulan terbaru teratas) dengan batch preloading status penguncian kronologis dan detail tagihan beserta rekening kloter & reminder WhatsApp
 * Dipakai Oleh: routes/web.php (/jamaah/invoices, /jamaah/invoices/{invoice})
 * Dependensi Utama: App\Models\Invoice, App\Models\BankAccount, App\Models\User, Auth, Request
 * Daftar Fungsi Utama: index(Request $request), show(Invoice $invoice)
 * Side Effect: Query DB data invoice, invoice_items, bank_accounts, kloter_bank_account, users (admin contact), dan payments
 */

namespace App\Http\Controllers\Jamaah;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * Menampilkan daftar tagihan bulanan dengan pemisahan tab:
     * - Tab 'unpaid' (Belum Dibayar): diurutkan ASCENDING (bulan pertama yang belum dibayar paling atas berurutan)
     * - Tab 'paid' (Sudah Dibayar): diurutkan DESCENDING (bulan terbaru paling atas)
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->input('tab', 'unpaid');
        if (!in_array($tab, ['unpaid', 'paid'])) {
            $tab = 'unpaid';
        }

        $baseQuery = Invoice::query()
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id));

        // Menghitung jumlah tagihan belum lunas vs sudah lunas untuk badge tab
        $unpaidCount = (clone $baseQuery)
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->count();

        $paidCount = (clone $baseQuery)
            ->where(function ($q) {
                $q->where('status', Invoice::STATUS_PAID)
                  ->orWhereColumn('paid_amount', '>=', 'total_amount');
            })
            ->count();

        $query = Invoice::query()
            ->with(['registration.kloter', 'items.registrationPax.familyMember'])
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id));

        if ($tab === 'paid') {
            // Sudah dibayar: DESC (bulan terbaru paling atas)
            $query->where(function ($q) {
                $q->where('status', Invoice::STATUS_PAID)
                  ->orWhereColumn('paid_amount', '>=', 'total_amount');
            })
            ->orderBy('billing_date', 'desc')
            ->orderBy('id', 'desc');
        } else {
            // Belum dibayar: ASC (bulan pertama yang belum dibayar paling atas, berurutan)
            $query->where('status', '!=', Invoice::STATUS_PAID)
                  ->whereColumn('paid_amount', '<', 'total_amount')
                  ->orderBy('billing_date', 'asc')
                  ->orderBy('id', 'asc');
        }

        $invoices = $query->paginate(10)->withQueryString();

        // Preload status penguncian kronologis secara batch (eliminasi N+1 query)
        $registrationIds = $invoices->getCollection()->pluck('registration_id')->unique()->filter();
        if ($registrationIds->isNotEmpty()) {
            $earliestUnpaidInvoices = Invoice::query()
                ->whereIn('registration_id', $registrationIds)
                ->where('status', '!=', Invoice::STATUS_PAID)
                ->whereColumn('paid_amount', '<', 'total_amount')
                ->orderBy('billing_year', 'asc')
                ->orderBy('billing_month', 'asc')
                ->get()
                ->groupBy('registration_id')
                ->map(fn ($group) => $group->first());

            foreach ($invoices as $inv) {
                if ($inv->isPaid()) {
                    $inv->setUnpaidPreviousInvoice(null);
                    continue;
                }
                $firstUnpaid = $earliestUnpaidInvoices->get($inv->registration_id);
                if ($firstUnpaid && $firstUnpaid->id !== $inv->id && (
                    $firstUnpaid->billing_year < $inv->billing_year ||
                    ($firstUnpaid->billing_year === $inv->billing_year && $firstUnpaid->billing_month < $inv->billing_month)
                )) {
                    $inv->setUnpaidPreviousInvoice($firstUnpaid);
                } else {
                    $inv->setUnpaidPreviousInvoice(null);
                }
            }
        }

        return view('jamaah.invoices.index', compact('invoices', 'tab', 'unpaidCount', 'paidCount'));
    }

    /**
     * Menampilkan rincian invoice, nomor rekening tujuan transfer, dan tombol reminder WhatsApp
     */
    public function show(Invoice $invoice): View
    {
        $user = Auth::user();

        // Otorisasi kepemilikan invoice
        if ($invoice->registration->user_id !== $user->id) {
            abort(403);
        }

        $invoice->load([
            'registration.kloter.bankAccounts',
            'items.registrationPax.familyMember',
            'payments' => fn ($q) => $q->with('bankAccount')->latest(),
        ]);

        $kloter = $invoice->registration?->kloter;
        $bankAccounts = ($kloter && $kloter->bankAccounts->where('is_active', true)->isNotEmpty())
            ? $kloter->bankAccounts->where('is_active', true)
            : BankAccount::active()->get();

        // Mengambil kontak admin / superadmin untuk tombol reminder konfirmasi transfer via WhatsApp
        $adminContact = User::query()
            ->whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN_KEUANGAN])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderByRaw("CASE WHEN role = 'superadmin' THEN 1 ELSE 2 END")
            ->first();

        return view('jamaah.invoices.show', compact('invoice', 'bankAccounts', 'adminContact'));
    }
}
