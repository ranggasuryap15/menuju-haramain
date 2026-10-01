<?php
/**
 * File: app/Http/Controllers/Jamaah/DashboardController.php
 * Tujuan: Menampilkan dasbor utama calon jamaah (ringkasan tabungan, anggota keluarga, tagihan, dan mutasi pembayaran)
 * Dipakai Oleh: routes/web.php (GET /jamaah/dashboard)
 * Dependensi Utama: App\Models\KloterRegistration, App\Models\Invoice, App\Models\Payment, Auth
 * Daftar Fungsi Utama: index()
 * Side Effect: Query DB data pendaftaran kloter, anggota keluarga, dan histori transaksi
 */

namespace App\Http\Controllers\Jamaah;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\KloterRegistration;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        // Ambil pendaftaran kloter aktif dengan eager loading optimal
        $registrations = KloterRegistration::query()
            ->with([
                'kloter',
                'paxes.familyMember',
                'invoices' => fn ($q) => $q->latest('billing_date'),
            ])
            ->where('user_id', $user->id)
            ->get();

        // Tagihan yang belum lunas (unpaid / partially_paid)
        $unpaidInvoices = Invoice::query()
            ->with(['registration.kloter', 'items.registrationPax.familyMember', 'payments'])
            ->whereHas('registration', fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_OVERDUE])
            ->orderBy('billing_date', 'asc')
            ->get();

        // Riwayat pembayaran terbaru
        $recentPayments = Payment::query()
            ->with(['invoice.registration.kloter', 'bankAccount'])
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        // Total akumulasi tabungan dan target (hanya dari kloter yang sudah aktif/disetujui)
        $totalSaved = $registrations->sum(fn ($reg) => $reg->total_saved);
        $totalTarget = $registrations->where('status', KloterRegistration::STATUS_ACTIVE)->sum(fn ($reg) => $reg->target_total);
        $progressPercentage = $totalTarget > 0 ? min(100.0, round(($totalSaved / $totalTarget) * 100, 1)) : 0;

        return view('jamaah.dashboard', compact(
            'user',
            'registrations',
            'unpaidInvoices',
            'recentPayments',
            'totalSaved',
            'totalTarget',
            'progressPercentage'
        ));
    }
}

