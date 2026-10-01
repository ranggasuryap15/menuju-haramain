<?php
/**
 * File: app/Http/Controllers/Jamaah/KloterRegistrationController.php
 * Tujuan: Manajemen pendaftaran akun jamaah dan pemilihan anggota keluarga ke kloter umroh dengan alur approval
 * Dipakai Oleh: routes/web.php (/jamaah/registrations)
 * Dependensi Utama: App\Models\Kloter, App\Models\FamilyMember, App\Models\KloterRegistration, App\Models\RegistrationPax, App\Services\BillingService
 * Daftar Fungsi Utama: create(), store()
 * Side Effect: Write/update DB tabel kloter_registrations (pending/active) dan registration_paxes, trigger initial invoice jika auto-approved
 */

namespace App\Http\Controllers\Jamaah;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\Kloter;
use App\Models\KloterRegistration;
use App\Models\RegistrationPax;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KloterRegistrationController extends Controller
{
    public function create(): View
    {
        $user = Auth::user();
        $kloters = Kloter::where('status', 'active')
            ->whereDate('end_date', '>=', now())
            ->orderBy('start_date')
            ->get();

        $familyMembers = FamilyMember::where('user_id', $user->id)->get();
        $registeredKloterIds = KloterRegistration::where('user_id', $user->id)
            ->whereIn('status', [KloterRegistration::STATUS_ACTIVE, KloterRegistration::STATUS_PENDING])
            ->pluck('kloter_id')
            ->toArray();

        return view('jamaah.registrations.create', compact('kloters', 'familyMembers', 'registeredKloterIds'));
    }

    public function store(Request $request, BillingService $billingService): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'kloter_id' => ['required', 'exists:kloters,id'],
            'family_member_ids' => ['required', 'array', 'min:1'],
            'family_member_ids.*' => ['exists:family_members,id'],
        ]);

        $kloter = Kloter::findOrFail($validated['kloter_id']);

        // Pastikan seluruh family_member_ids yang dipilih adalah milik user yang sedang login
        $validMembers = FamilyMember::where('user_id', $user->id)
            ->whereIn('id', $validated['family_member_ids'])
            ->get();

        if ($validMembers->count() !== count($validated['family_member_ids'])) {
            return back()->with('error', 'Terdapat pilihan anggota keluarga yang tidak valid.');
        }

        // Cek pendaftaran kloter sebelumnya
        $existingRegistration = KloterRegistration::where('user_id', $user->id)
            ->where('kloter_id', $kloter->id)
            ->first();

        if ($existingRegistration) {
            if ($existingRegistration->isPending()) {
                return back()->with('error', 'Pendaftaran Anda pada kloter ini sedang menunggu persetujuan Admin.');
            }
            if ($existingRegistration->isActive()) {
                return back()->with('error', 'Akun Anda sudah terdaftar aktif pada kloter ini.');
            }
        }

        // Superadmin & Admin Keuangan tidak perlu approval; Jamaah biasa wajib approval
        $isAutoApproved = $user->isStaff();
        $status = $isAutoApproved ? KloterRegistration::STATUS_ACTIVE : KloterRegistration::STATUS_PENDING;
        $approvedBy = $isAutoApproved ? $user->id : null;
        $approvedAt = $isAutoApproved ? now() : null;

        DB::transaction(function () use ($user, $kloter, $validMembers, $billingService, $existingRegistration, $status, $approvedBy, $approvedAt, $isAutoApproved) {
            if ($existingRegistration) {
                // Jika sebelumnya pernah ditolak, perbarui status menjadi pending/active kembali
                $existingRegistration->update([
                    'total_pax' => $validMembers->count(),
                    'status' => $status,
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                    'admin_notes' => null,
                ]);
                $registration = $existingRegistration;
                // Bersihkan pax lama dan ganti dengan yang baru dipilih
                $registration->paxes()->delete();
            } else {
                $registration = KloterRegistration::create([
                    'kloter_id' => $kloter->id,
                    'user_id' => $user->id,
                    'total_pax' => $validMembers->count(),
                    'status' => $status,
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                ]);
            }

            foreach ($validMembers as $member) {
                RegistrationPax::create([
                    'registration_id' => $registration->id,
                    'family_member_id' => $member->id,
                    'status' => 'active',
                ]);
            }

            // Tagihan hanya diterbitkan jika pendaftaran langsung aktif (Superadmin/Admin)
            if ($isAutoApproved && Carbon::now()->between($kloter->start_date, $kloter->end_date)) {
                $billingService->generateMonthlyInvoices(Carbon::now()->startOfMonth());
            }
        });

        if ($isAutoApproved) {
            return redirect()->route('jamaah.dashboard')->with('success', 'Pendaftaran kloter berhasil dan otomatis disetujui (Admin/Superadmin).');
        }

        return redirect()->route('jamaah.dashboard')->with('success', 'Pendaftaran kloter berhasil diajukan. Mohon menunggu persetujuan (approval) dari Administrator.');
    }
}

