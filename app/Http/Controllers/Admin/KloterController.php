<?php
/**
 * File: app/Http/Controllers/Admin/KloterController.php
 * Tujuan: Manajemen master kloter tabungan umroh (pembuatan kloter, pengubahan detail kloter, rentang periode, tarif per pax, detail peserta kloter, rekapitulasi dana terkumpul riil, trigger billing)
 * Dipakai Oleh: routes/web.php (/admin/kloters, /admin/kloters/create, /admin/kloters/{kloter}, /admin/kloters/{kloter}/edit, /admin/kloters/trigger-billing)
 * Dependensi Utama: App\Models\Kloter, App\Models\Payment, App\Services\BillingService, Request, Illuminate\Validation\Rule
 * Daftar Fungsi Utama: index(), create(), store(), show(), edit(), update(), triggerBilling()
 * Side Effect: Write DB tabel kloters (insert/update), eksekusi pembuatan tagihan bulanan
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kloter;
use App\Models\Payment;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KloterController extends Controller
{
    public function index(): View
    {
        $kloters = Kloter::query()
            ->withCount(['registrations'])
            ->with([
                'registrations.invoices.payments' => fn ($q) => $q->where('status', Payment::STATUS_APPROVED),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.kloters.index', compact('kloters'));
    }

    public function create(): View
    {
        return view('admin.kloters.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:kloters,code'],
            'target_per_pax' => ['required', 'numeric', 'min:1000000'],
            'monthly_per_pax' => ['required', 'numeric', 'min:100000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,active,closed,completed'],
        ]);

        Kloter::create($validated);

        return redirect()->route('admin.kloters.index')->with('success', 'Kloter baru berhasil dibuat.');
    }

    public function show(Kloter $kloter): View
    {
        $kloter->load([
            'registrations.user',
            'registrations.paxes.familyMember',
            'registrations.invoices.payments' => fn ($q) => $q->where('status', Payment::STATUS_APPROVED),
        ]);

        return view('admin.kloters.show', compact('kloter'));
    }

    public function edit(Kloter $kloter): View
    {
        return view('admin.kloters.edit', compact('kloter'));
    }

    public function update(Request $request, Kloter $kloter): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('kloters', 'code')->ignore($kloter->id)],
            'target_per_pax' => ['required', 'numeric', 'min:1000000'],
            'monthly_per_pax' => ['required', 'numeric', 'min:100000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,active,closed,completed'],
        ]);

        $kloter->update($validated);

        return redirect()->route('admin.kloters.show', $kloter)->with('success', 'Detail kloter berhasil diperbarui.');
    }

    /**
     * Trigger manual untuk menerbitkan tagihan bulan ini
     */
    public function triggerBilling(Request $request, BillingService $billingService): RedirectResponse
    {
        $dateParam = $request->input('billing_date');
        $billingDate = $dateParam ? Carbon::parse($dateParam)->startOfMonth() : Carbon::now()->startOfMonth();

        $stats = $billingService->generateMonthlyInvoices($billingDate);

        return back()->with('success', sprintf(
            'Generate tagihan periode %s selesai. Dibuat: %d, Dilewati/Sudah Ada: %d.',
            $billingDate->locale('id')->translatedFormat('F Y'),
            $stats['created'],
            $stats['skipped']
        ));
    }
}
