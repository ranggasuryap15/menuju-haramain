<?php
/**
 * File: app/Http/Controllers/Jamaah/KloterRegistrationController.php
 * Tujuan: Manajemen pendaftaran akun jamaah dan pemilihan anggota keluarga ke kloter umroh (pendaftaran awal maupun penambahan anggota keluarga berikutnya ke kloter yang sama)
 * Dipakai Oleh: routes/web.php (/jamaah/registrations, /jamaah/registrations/create)
 * Dependensi Utama: App\Models\Kloter, App\Models\FamilyMember, App\Models\KloterRegistration, App\Models\RegistrationPax, App\Models\Invoice, App\Models\InvoiceItem, App\Services\BillingService
 * Daftar Fungsi Utama: create(), store()
 * Side Effect: Write/update DB tabel kloter_registrations, registration_paxes, penyesuaian invoice bulan berjalan jika kloter aktif
 */

namespace App\Http\Controllers\Jamaah;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

        // Dapatkan data pendaftaran aktif & pending milik user beserta paxes
        $userRegistrations = KloterRegistration::where('user_id', $user->id)
            ->whereIn('status', [KloterRegistration::STATUS_ACTIVE, KloterRegistration::STATUS_PENDING])
            ->with(['paxes' => fn ($q) => $q->where('status', 'active')])
            ->get();

        // Map: [kloter_id => [family_member_id, ...]]
        $kloterRegisteredMemberIds = [];
        foreach ($userRegistrations as $reg) {
            $kloterRegisteredMemberIds[$reg->kloter_id] = $reg->paxes->pluck('family_member_id')->toArray();
        }

        return view('jamaah.registrations.create', compact('kloters', 'familyMembers', 'kloterRegisteredMemberIds'));
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

        // Cek pendaftaran kloter sebelumnya untuk akun ini
        $existingRegistration = KloterRegistration::where('user_id', $user->id)
            ->where('kloter_id', $kloter->id)
            ->first();

        // Dapatkan ID anggota yang SUDAH terdaftar aktif/pending di kloter ini
        $alreadyRegisteredPaxIds = [];
        if ($existingRegistration) {
            $alreadyRegisteredPaxIds = $existingRegistration->paxes()
                ->where('status', 'active')
                ->pluck('family_member_id')
                ->toArray();
        }

        // Filter hanya anggota keluarga yang BELUM terdaftar di kloter ini
        $membersToRegister = $validMembers->reject(function ($m) use ($alreadyRegisteredPaxIds) {
            return in_array($m->id, $alreadyRegisteredPaxIds);
        });

        if ($membersToRegister->isEmpty()) {
            return back()->with('error', 'Semua anggota keluarga yang dipilih sudah terdaftar pada kloter ini.');
        }

        // Superadmin & Admin Keuangan tidak perlu approval; Jamaah biasa wajib approval
        $isAutoApproved = $user->isStaff();
        $status = $isAutoApproved ? KloterRegistration::STATUS_ACTIVE : KloterRegistration::STATUS_PENDING;
        $approvedBy = $isAutoApproved ? $user->id : null;
        $approvedAt = $isAutoApproved ? now() : null;

        DB::transaction(function () use ($user, $kloter, $membersToRegister, $billingService, $existingRegistration, $status, $approvedBy, $approvedAt, $isAutoApproved) {
            if ($existingRegistration) {
                // Tambahkan pax baru tanpa menghapus pax lama yang sudah terdaftar
                foreach ($membersToRegister as $member) {
                    RegistrationPax::firstOrCreate([
                        'registration_id' => $existingRegistration->id,
                        'family_member_id' => $member->id,
                    ], [
                        'status' => 'active',
                    ]);
                }

                // Update total_pax
                $totalActivePaxes = $existingRegistration->paxes()->where('status', 'active')->count();
                $updateData = ['total_pax' => $totalActivePaxes];

                // Jika sebelumnya ditolak, buka kembali
                if ($existingRegistration->isRejected()) {
                    $updateData['status'] = $status;
                    $updateData['approved_by'] = $approvedBy;
                    $updateData['approved_at'] = $approvedAt;
                    $updateData['admin_notes'] = null;
                }

                $existingRegistration->update($updateData);
                $registration = $existingRegistration;
            } else {
                $registration = KloterRegistration::create([
                    'kloter_id' => $kloter->id,
                    'user_id' => $user->id,
                    'total_pax' => $membersToRegister->count(),
                    'status' => $status,
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                ]);

                foreach ($membersToRegister as $member) {
                    RegistrationPax::create([
                        'registration_id' => $registration->id,
                        'family_member_id' => $member->id,
                        'status' => 'active',
                    ]);
                }
            }

            // Penyesuaian tagihan: Jika pendaftaran aktif dan periode kloter sedang berjalan
            if (($registration->isActive() || $isAutoApproved) && Carbon::now()->between($kloter->start_date, $kloter->end_date)) {
                $currentMonth = Carbon::now()->startOfMonth();
                $currentInvoice = Invoice::where('registration_id', $registration->id)
                    ->where('billing_year', $currentMonth->year)
                    ->where('billing_month', $currentMonth->month)
                    ->first();

                if ($currentInvoice && $currentInvoice->status === Invoice::STATUS_UNPAID) {
                    $monthlyPerPax = (float) $kloter->monthly_per_pax;
                    foreach ($membersToRegister as $member) {
                        $pax = RegistrationPax::where('registration_id', $registration->id)
                            ->where('family_member_id', $member->id)
                            ->first();

                        if ($pax && !InvoiceItem::where('invoice_id', $currentInvoice->id)->where('registration_pax_id', $pax->id)->exists()) {
                            InvoiceItem::create([
                                'invoice_id' => $currentInvoice->id,
                                'registration_pax_id' => $pax->id,
                                'amount' => $monthlyPerPax,
                                'description' => "Tabungan Umroh Bulan " . $currentMonth->locale('id')->translatedFormat('F Y') . " - " . ($member->full_name ?? 'Peserta'),
                            ]);
                            $currentInvoice->total_amount += $monthlyPerPax;
                        }
                    }
                    $currentInvoice->save();
                } else {
                    $billingService->generateMonthlyInvoices($currentMonth);
                }
            }
        });

        if ($isAutoApproved || ($existingRegistration && $existingRegistration->isActive())) {
            return redirect()->route('jamaah.dashboard')->with('success', 'Pendaftaran anggota keluarga ke kloter berhasil.');
        }

        return redirect()->route('jamaah.dashboard')->with('success', 'Pendaftaran kloter berhasil diajukan. Mohon menunggu persetujuan (approval) dari Administrator.');
    }
}

