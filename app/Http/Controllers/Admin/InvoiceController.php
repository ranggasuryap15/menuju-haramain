<?php

/**
 * File: app/Http/Controllers/Admin/InvoiceController.php
 * Tujuan: Manajemen dan monitoring tagihan bulanan seluruh jama'ah bagi Superadmin & Admin Keuangan, termasuk pemantauan tagihan belum bayar (piutang), tunggakan, filter kloter & periode, serta detail rincian tagihan per keluarga
 * Dipakai Oleh: routes/web.php (/admin/invoices, /admin/invoices/{invoice})
 * Dependensi Utama: App\Models\Invoice, App\Models\Kloter, App\Models\Payment, Carbon\Carbon, DB, Request
 * Daftar Fungsi Utama: index(), show()
 * Side Effect: Query DB tabel invoices, kloter_registrations, users, payments, invoice_items
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Kloter;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /**
     * Menampilkan daftar tagihan jama'ah dengan fokus utama pada tagihan belum lunas
     */
    public function index(Request $request): View
    {
        $status = $request->input('status', 'all_unpaid');
        $kloterId = $request->input('kloter_id');
        $period = $request->input('period');
        $search = $request->input('search');

        $query = Invoice::query()
            ->with([
                'registration.user',
                'registration.kloter',
                'registration.paxes.familyMember',
                'payments' => fn ($q) => $q->where('status', Payment::STATUS_PENDING),
            ]);

        // Filter status tagihan
        if ($status === 'all_unpaid') {
            $query->where('status', '!=', Invoice::STATUS_PAID)
                  ->whereColumn('paid_amount', '<', 'total_amount');
        } elseif ($status === 'unpaid') {
            $query->where('status', Invoice::STATUS_UNPAID);
        } elseif ($status === 'partially_paid') {
            $query->where('status', Invoice::STATUS_PARTIALLY_PAID);
        } elseif ($status === 'overdue') {
            $query->where(function ($q) {
                $q->where('status', Invoice::STATUS_OVERDUE)
                  ->orWhere(function ($sub) {
                      $sub->where('status', '!=', Invoice::STATUS_PAID)
                          ->where('due_date', '<', Carbon::today());
                  });
            });
        } elseif ($status === 'paid') {
            $query->where('status', Invoice::STATUS_PAID);
        }

        // Filter berdasarkan Kloter
        if ($kloterId) {
            $query->whereHas('registration', fn ($q) => $q->where('kloter_id', $kloterId));
        }

        // Filter berdasarkan Periode (format: YYYY-MM)
        if ($period) {
            $periodDate = Carbon::parse($period);
            $query->where('billing_year', $periodDate->year)
                  ->where('billing_month', $periodDate->month);
        }

        // Filter pencarian teks (No Invoice, Nama Jamaah, Email, No Telepon)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('registration.user', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->orderBy('billing_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Agregasi statistik ringkasan tagihan belum lunas
        $unpaidBase = Invoice::query()
            ->where('invoices.status', '!=', Invoice::STATUS_PAID)
            ->whereColumn('invoices.paid_amount', '<', 'invoices.total_amount');

        $stats = [
            'total_unpaid_amount' => (float) (clone $unpaidBase)->sum(DB::raw('invoices.total_amount - invoices.paid_amount')),
            'unpaid_count' => (clone $unpaidBase)->count(),
            'overdue_count' => Invoice::where('invoices.status', '!=', Invoice::STATUS_PAID)
                ->where(function ($q) {
                    $q->where('invoices.status', Invoice::STATUS_OVERDUE)
                      ->orWhere('invoices.due_date', '<', Carbon::today());
                })->count(),
            'unpaid_jamaah_count' => (clone $unpaidBase)
                ->join('kloter_registrations', 'invoices.registration_id', '=', 'kloter_registrations.id')
                ->distinct('kloter_registrations.user_id')
                ->count('kloter_registrations.user_id'),
        ];

        $kloters = Kloter::orderBy('name')->get();

        return view('admin.invoices.index', compact(
            'invoices',
            'stats',
            'kloters',
            'status',
            'kloterId',
            'period',
            'search'
        ));
    }

    /**
     * Menampilkan rincian detail tagihan tertentu bagi admin
     */
    public function show(Invoice $invoice): View
    {
        $invoice->load([
            'registration.user',
            'registration.kloter.bankAccounts',
            'registration.paxes.familyMember',
            'items.registrationPax.familyMember',
            'payments.verifier',
            'payments.bankAccount',
        ]);

        return view('admin.invoices.show', compact('invoice'));
    }
}
