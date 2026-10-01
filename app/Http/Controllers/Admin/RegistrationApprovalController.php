<?php
/**
 * File: app/Http/Controllers/Admin/RegistrationApprovalController.php
 * Tujuan: Manajemen antrean verifikasi dan persetujuan (approval) pendaftaran kloter umroh oleh Admin/Superadmin
 * Dipakai Oleh: routes/web.php (/admin/registrations, /admin/registrations/{registration}/approve, reject)
 * Dependensi Utama: App\Models\KloterRegistration, App\Services\BillingService, Auth, Request
 * Daftar Fungsi Utama: index(), show(), approve(), reject()
 * Side Effect: Update status kloter_registrations, generate monthly invoice jika kloter sedang berlangsung
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KloterRegistration;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegistrationApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', KloterRegistration::STATUS_PENDING);

        $query = KloterRegistration::query()
            ->with([
                'user',
                'kloter',
                'paxes.familyMember',
                'approver',
            ]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $registrations = $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'pending' => KloterRegistration::where('status', KloterRegistration::STATUS_PENDING)->count(),
            'active' => KloterRegistration::where('status', KloterRegistration::STATUS_ACTIVE)->count(),
            'rejected' => KloterRegistration::where('status', KloterRegistration::STATUS_REJECTED)->count(),
        ];

        return view('admin.registrations.index', compact('registrations', 'status', 'counts'));
    }

    public function show(KloterRegistration $registration): View
    {
        $registration->load([
            'user',
            'kloter',
            'paxes.familyMember',
            'approver',
            'invoices.payments',
        ]);

        return view('admin.registrations.show', compact('registration'));
    }

    public function approve(Request $request, KloterRegistration $registration, BillingService $billingService): RedirectResponse
    {
        $admin = Auth::user();

        if ($registration->status !== KloterRegistration::STATUS_PENDING) {
            return back()->with('error', 'Pendaftaran ini sudah diproses sebelumnya.');
        }

        DB::transaction(function () use ($registration, $admin, $billingService) {
            $registration->update([
                'status' => KloterRegistration::STATUS_ACTIVE,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'admin_notes' => null,
            ]);

            // Jika periode kloter sedang berjalan, otomatis terbitkan tagihan bulan berjalan
            $kloter = $registration->kloter;
            if (Carbon::now()->between($kloter->start_date, $kloter->end_date)) {
                $billingService->generateMonthlyInvoices(Carbon::now()->startOfMonth());
            }
        });

        return redirect()->route('admin.registrations.index')
            ->with('success', 'Pendaftaran kloter peserta "' . $registration->user->name . '" berhasil disetujui (Approved).');
    }

    public function reject(Request $request, KloterRegistration $registration): RedirectResponse
    {
        $request->validate([
            'admin_notes' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $admin = Auth::user();

        if ($registration->status !== KloterRegistration::STATUS_PENDING) {
            return back()->with('error', 'Pendaftaran ini sudah diproses sebelumnya.');
        }

        $registration->update([
            'status' => KloterRegistration::STATUS_REJECTED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'admin_notes' => $request->input('admin_notes'),
        ]);

        return redirect()->route('admin.registrations.index')
            ->with('success', 'Pendaftaran kloter peserta "' . $registration->user->name . '" telah ditolak.');
    }
}
