<?php
/**
 * File: app/Http/Controllers/Admin/KloterController.php
 * Tujuan: Manajemen master kloter tabungan umroh (pembuatan kloter, pengubahan detail kloter, normalisasi kode kloter selalu UPPERCASE, asosiasi multi-rekening bank per kloter, rentang periode, tarif per pax, detail peserta kloter, rekapitulasi dana terkumpul riil, dan penagihan spesifik per-kloter)
 * Dipakai Oleh: routes/web.php (/admin/kloters, /admin/kloters/create, /admin/kloters/{kloter}, /admin/kloters/{kloter}/edit, /admin/kloters/{kloter}/trigger-billing)
 * Dependensi Utama: App\Models\Kloter, App\Models\BankAccount, App\Models\Payment, App\Services\BillingService, Request, Illuminate\Validation\Rule
 * Daftar Fungsi Utama: index(), create(), store(), show(), edit(), update(), triggerBilling(), triggerKloterBilling()
 * Side Effect: Write DB tabel kloters (insert/update uppercase code), pivot kloter_bank_account (sync), eksekusi pembuatan tagihan bulanan
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
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
                'bankAccounts',
                'registrations.invoices.payments' => fn ($q) => $q->where('status', Payment::STATUS_APPROVED),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.kloters.index', compact('kloters'));
    }

    public function create(): View
    {
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('bank_name')->get();

        return view('admin.kloters.create', compact('bankAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:kloters,code'],
            'target_per_pax' => ['required', 'numeric', 'min:1000000'],
            'monthly_per_pax' => ['required', 'numeric', 'min:100000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,active,closed,completed'],
            'bank_account_ids' => ['nullable', 'array'],
            'bank_account_ids.*' => ['exists:bank_accounts,id'],
            'new_bank_name' => ['nullable', 'string', 'max:100'],
            'new_account_number' => ['nullable', 'string', 'max:50'],
            'new_account_holder' => ['nullable', 'string', 'max:150'],
        ]);

        $kloterData = collect($validated)->except([
            'bank_account_ids',
            'new_bank_name',
            'new_account_number',
            'new_account_holder',
        ])->toArray();

        $kloter = Kloter::create($kloterData);

        $bankAccountIds = $validated['bank_account_ids'] ?? [];

        // Tambah rekening bank baru langsung bila field diisi lengkap
        if (!empty($validated['new_bank_name']) && !empty($validated['new_account_number']) && !empty($validated['new_account_holder'])) {
            $newBank = BankAccount::create([
                'bank_name' => $validated['new_bank_name'],
                'account_number' => $validated['new_account_number'],
                'account_holder' => $validated['new_account_holder'],
                'is_active' => true,
            ]);
            $bankAccountIds[] = $newBank->id;
        }

        if (!empty($bankAccountIds)) {
            $kloter->bankAccounts()->sync(array_unique($bankAccountIds));
        }

        return redirect()->route('admin.kloters.index')->with('success', 'Kloter baru berhasil dibuat.');
    }

    public function show(Kloter $kloter): View
    {
        $kloter->load([
            'bankAccounts',
            'registrations.user',
            'registrations.paxes.familyMember',
            'registrations.invoices.payments' => fn ($q) => $q->where('status', Payment::STATUS_APPROVED),
        ]);

        return view('admin.kloters.show', compact('kloter'));
    }

    public function edit(Kloter $kloter): View
    {
        $kloter->load('bankAccounts');
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('bank_name')->get();

        return view('admin.kloters.edit', compact('kloter', 'bankAccounts'));
    }

    public function update(Request $request, Kloter $kloter): RedirectResponse
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('kloters', 'code')->ignore($kloter->id)],
            'target_per_pax' => ['required', 'numeric', 'min:1000000'],
            'monthly_per_pax' => ['required', 'numeric', 'min:100000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,active,closed,completed'],
            'bank_account_ids' => ['nullable', 'array'],
            'bank_account_ids.*' => ['exists:bank_accounts,id'],
            'new_bank_name' => ['nullable', 'string', 'max:100'],
            'new_account_number' => ['nullable', 'string', 'max:50'],
            'new_account_holder' => ['nullable', 'string', 'max:150'],
        ]);

        $kloterData = collect($validated)->except([
            'bank_account_ids',
            'new_bank_name',
            'new_account_number',
            'new_account_holder',
        ])->toArray();

        $kloter->update($kloterData);

        $bankAccountIds = $validated['bank_account_ids'] ?? [];

        // Tambah rekening bank baru langsung bila field diisi lengkap
        if (!empty($validated['new_bank_name']) && !empty($validated['new_account_number']) && !empty($validated['new_account_holder'])) {
            $newBank = BankAccount::create([
                'bank_name' => $validated['new_bank_name'],
                'account_number' => $validated['new_account_number'],
                'account_holder' => $validated['new_account_holder'],
                'is_active' => true,
            ]);
            $bankAccountIds[] = $newBank->id;
        }

        $kloter->bankAccounts()->sync(array_unique($bankAccountIds));

        return redirect()->route('admin.kloters.show', $kloter)->with('success', 'Detail kloter berhasil diperbarui.');
    }

    /**
     * Trigger manual untuk menerbitkan tagihan bulan ini serentak
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

    /**
     * Trigger manual untuk menerbitkan tagihan khusus pada kloter ini
     */
    public function triggerKloterBilling(Request $request, Kloter $kloter, BillingService $billingService): RedirectResponse
    {
        $dateParam = $request->input('billing_date');
        $billingDate = $dateParam ? Carbon::parse($dateParam)->startOfMonth() : Carbon::now()->startOfMonth();

        $stats = $billingService->generateKloterInvoices($kloter, $billingDate);

        return back()->with('success', sprintf(
            'Generate tagihan kloter "%s" periode %s selesai. Dibuat: %d invoice, Dilewati/Sudah Ada: %d.',
            $kloter->name,
            $billingDate->locale('id')->translatedFormat('F Y'),
            $stats['created'],
            $stats['skipped']
        ));
    }
}
