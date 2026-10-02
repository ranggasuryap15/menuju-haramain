<?php
/**
 * File: app/Http/Controllers/Jamaah/InvoiceController.php
 * Tujuan: Menampilkan daftar tagihan bulanan dengan preloading status penguncian kronologis dan halaman detail tagihan beserta rekening bank spesifik kloter/fallback dan kontak WhatsApp admin untuk reminder
 * Dipakai Oleh: routes/web.php (/jamaah/invoices, /jamaah/invoices/{invoice})
 * Dependensi Utama: App\Models\Invoice, App\Models\BankAccount, App\Models\User, Auth
 * Daftar Fungsi Utama: index(), show()
 * Side Effect: Query DB data invoice, invoice_items, bank_accounts, kloter_bank_account, users (admin contact), dan payments
 */

namespace App\Http\Controllers\Jamaah;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        $invoices = Invoice::query()
            ->with(['registration.kloter', 'items.registrationPax.familyMember'])
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('billing_date', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(10);

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

        return view('jamaah.invoices.index', compact('invoices'));
    }

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
        $adminContact = \App\Models\User::query()
            ->whereIn('role', [\App\Models\User::ROLE_SUPERADMIN, \App\Models\User::ROLE_ADMIN_KEUANGAN])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderByRaw("CASE WHEN role = 'superadmin' THEN 1 ELSE 2 END")
            ->first();

        return view('jamaah.invoices.show', compact('invoice', 'bankAccounts', 'adminContact'));
    }
}

