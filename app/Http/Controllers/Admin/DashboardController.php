<?php
/**
 * File: app/Http/Controllers/Admin/DashboardController.php
 * Tujuan: Dasbor administratif pengelola dan admin keuangan (rekapitulasi kas masuk, antrean approval, dan kloter)
 * Dipakai Oleh: routes/web.php (/admin/dashboard)
 * Dependensi Utama: App\Models\Payment, App\Models\Invoice, App\Models\Kloter, App\Models\User
 * Daftar Fungsi Utama: index()
 * Side Effect: Query DB agregat statistik keuangan dan daftar antrean transaksi
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kloter;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $totalApprovedFunds = (float) Payment::where('status', Payment::STATUS_APPROVED)->sum('amount');
        $pendingApprovalsCount = Payment::where('status', Payment::STATUS_PENDING)->count();
        $totalActiveJamaah = User::where('role', User::ROLE_JAMAAH)->count();
        $totalActiveKloters = Kloter::where('status', 'active')->count();

        // Antrean verifikasi pembayaran yang masih pending
        $pendingPayments = Payment::query()
            ->with(['user', 'invoice.registration.kloter', 'bankAccount'])
            ->where('status', Payment::STATUS_PENDING)
            ->latest()
            ->take(10)
            ->get();

        // Kloter aktif beserta rekapitulasi pendaftar
        $activeKloters = Kloter::query()
            ->withCount('registrations')
            ->where('status', 'active')
            ->orderBy('start_date')
            ->get();

        return view('admin.dashboard', compact(
            'totalApprovedFunds',
            'pendingApprovalsCount',
            'totalActiveJamaah',
            'totalActiveKloters',
            'pendingPayments',
            'activeKloters'
        ));
    }
}

