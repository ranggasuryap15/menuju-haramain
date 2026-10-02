<?php

/**
 * File: app/Http/Controllers/Admin/KloterController.php
 * Tujuan: Manajemen master kloter tabungan umroh (pembuatan kloter, link WhatsApp grup kloter, pengubahan detail kloter, normalisasi kode kloter selalu UPPERCASE, asosiasi multi-rekening bank per kloter, rentang periode, tarif per pax, detail peserta kloter, rekapitulasi dana terkumpul riil, riwayat transaksi pembayaran jamaah kloter berpaginasi descending, serta penerbitan tagihan manual massal untuk setiap orang di kloter maupun spesifik per orang/keluarga)
 * Dipakai Oleh: routes/web.php (/admin/kloters, /admin/kloters/create, /admin/kloters/{kloter}, /admin/kloters/{kloter}/edit, /admin/kloters/{kloter}/trigger-billing, /admin/registrations/{registration}/trigger-billing)
 * Dependensi Utama: App\Models\Kloter, App\Models\KloterRegistration, App\Models\BankAccount, App\Models\Payment, App\Services\BillingService, Request, Illuminate\Validation\Rule
 * Daftar Fungsi Utama: index(), create(), store(), show(), edit(), update(), triggerBilling(), triggerKloterBilling(), triggerRegistrationBilling()
 * Side Effect: Write DB tabel kloters (insert/update uppercase code, whatsapp_group_url), pivot kloter_bank_account (sync), eksekusi pembuatan tagihan bulanan invoices & invoice_items
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Kloter;
use App\Models\KloterRegistration;
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
                'registrations.invoices.payments' => fn($q) => $q->where('status', Payment::STATUS_APPROVED),
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
            'whatsapp_group_url' => ['nullable', 'url', 'max:255'],
            'bank_account_ids' => ['nullable', 'array'],
            'bank_account_ids.*' => ['exists:bank_accounts,id'],
            'new_bank_name' => ['nullable', 'string', 'max:100'],
            'new_account_number' => ['nullable', 'string', 'max:50'],
            'new_account_holder' => ['nullable', 'string', 'max:100'],
        ]);

        $kloter = Kloter::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'target_per_pax' => $validated['target_per_pax'],
            'monthly_per_pax' => $validated['monthly_per_pax'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'] ?? null,
            'whatsapp_group_url' => $validated['whatsapp_group_url'] ?? null,
            'status' => 'active',
        ]);

        $bankAccountIds = $request->input('bank_account_ids', []);

        if ($request->filled('new_bank_name') && $request->filled('new_account_number') && $request->filled('new_account_holder')) {
            $newBank = BankAccount::create([
                'bank_name' => $request->input('new_bank_name'),
                'account_number' => $request->input('new_account_number'),
                'account_holder' => $request->input('new_account_holder'),
                'is_active' => true,
            ]);
            $bankAccountIds[] = $newBank->id;
        }

        if (!empty($bankAccountIds)) {
            $kloter->bankAccounts()->sync(array_unique($bankAccountIds));
        }

        return redirect()->route('admin.kloters.index')->with('success', "Kloter {$kloter->name} ({$kloter->code}) berhasil dibuat.");
    }

    public function show(Kloter $kloter): View
    {
        $kloter->load([
            'bankAccounts',
            'registrations.user',
            'registrations.paxes.familyMember',
            'registrations.invoices.payments' => fn($q) => $q->where('status', Payment::STATUS_APPROVED),
        ]);

        $payments = Payment::query()
            ->with(['user', 'invoice.registration', 'bankAccount', 'verifier'])
            ->whereHas('invoice.registration', function ($query) use ($kloter) {
                $query->where('kloter_id', $kloter->id);
            })
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10);

        return view('admin.kloters.show', compact('kloter', 'payments'));
    }

    public function edit(Kloter $kloter): View
    {
        $kloter->load('bankAccounts');
        $bankAccounts = BankAccount::orderBy('bank_name')->get();

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
            'whatsapp_group_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', 'in:draft,active,closed,completed'],
            'bank_account_ids' => ['nullable', 'array'],
            'bank_account_ids.*' => ['exists:bank_accounts,id'],
            'new_bank_name' => ['nullable', 'string', 'max:100'],
            'new_account_number' => ['nullable', 'string', 'max:50'],
            'new_account_holder' => ['nullable', 'string', 'max:100'],
        ]);

        $kloter->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'target_per_pax' => $validated['target_per_pax'],
            'monthly_per_pax' => $validated['monthly_per_pax'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'] ?? null,
            'whatsapp_group_url' => $validated['whatsapp_group_url'] ?? null,
            'status' => $validated['status'],
        ]);

        $bankAccountIds = $request->input('bank_account_ids', []);

        if ($request->filled('new_bank_name') && $request->filled('new_account_number') && $request->filled('new_account_holder')) {
            $newBank = BankAccount::create([
                'bank_name' => $request->input('new_bank_name'),
                'account_number' => $request->input('new_account_number'),
                'account_holder' => $request->input('new_account_holder'),
                'is_active' => true,
            ]);
            $bankAccountIds[] = $newBank->id;
        }

        $kloter->bankAccounts()->sync(array_unique($bankAccountIds));

        return redirect()->route('admin.kloters.show', $kloter)->with('success', 'Detail kloter berhasil diperbarui.');
    }

    /**
     * Trigger manual untuk menerbitkan tagihan bulan ini serentak (seluruh sistem)
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
     * Trigger manual untuk menerbitkan tagihan khusus pada kloter ini:
     * - Bisa untuk SEMUA anggota kloter (setiap orang)
     * - Bisa untuk SPESIFIK 1 anggota keluarga (per orang) jika registration_id disertakan
     * - Bisa 1 bulan tertentu atau seluruh periode tertunggak sejak awal bergabung
     */
    public function triggerKloterBilling(Request $request, Kloter $kloter, BillingService $billingService): RedirectResponse
    {
        $dateParam = $request->input('billing_date');
        $billingDate = $dateParam ? Carbon::parse($dateParam)->startOfMonth() : Carbon::now()->startOfMonth();
        $generateAllPending = $request->boolean('generate_all_pending');
        $registrationId = $request->input('registration_id');

        // Jika dipilih per orang / pendaftar tertentu
        if ($registrationId && $registrationId !== 'all') {
            $registration = KloterRegistration::with(['user', 'kloter', 'paxes'])->where('kloter_id', $kloter->id)->findOrFail($registrationId);
            $userName = $registration->user?->name ?? 'Jamaah';

            if ($generateAllPending) {
                $stats = $billingService->generateRegistrationAllPendingInvoices($registration, $billingDate);

                return back()->with('success', sprintf(
                    'Generate tagihan per orang untuk "%s" dari periode %s s/d %s selesai (%d bulan). Dibuat: %d invoice baru, Dilewati/Sudah Ada: %d.',
                    $userName,
                    $stats['start_period'],
                    $stats['end_period'],
                    $stats['months'],
                    $stats['created'],
                    $stats['skipped']
                ));
            }

            $stats = $billingService->generateRegistrationInvoice($registration, $billingDate);

            return back()->with('success', sprintf(
                'Generate tagihan per orang untuk "%s" periode %s selesai. Dibuat: %d invoice, Dilewati/Sudah Ada: %d.',
                $userName,
                $stats['period'],
                $stats['created'],
                $stats['skipped']
            ));
        }

        // Untuk setiap orang / semua anggota kloter
        if ($generateAllPending) {
            $stats = $billingService->generateKloterAllPendingInvoices($kloter, $billingDate);

            return back()->with('success', sprintf(
                'Generate tagihan kloter "%s" (semua jamaah) dari periode %s s/d %s selesai (%d bulan). Dibuat: %d invoice baru, Dilewati/Sudah Ada: %d.',
                $kloter->name,
                $stats['start_period'],
                $stats['end_period'],
                $stats['months'],
                $stats['created'],
                $stats['skipped']
            ));
        }

        $stats = $billingService->generateKloterInvoices($kloter, $billingDate);

        return back()->with('success', sprintf(
            'Generate tagihan kloter "%s" (semua jamaah) periode %s selesai. Dibuat: %d invoice, Dilewati/Sudah Ada: %d.',
            $kloter->name,
            $billingDate->locale('id')->translatedFormat('F Y'),
            $stats['created'],
            $stats['skipped']
        ));
    }

    /**
     * Trigger manual untuk menerbitkan tagihan khusus 1 pendaftaran jama'ah (per orang)
     */
    public function triggerRegistrationBilling(Request $request, KloterRegistration $registration, BillingService $billingService): RedirectResponse
    {
        $dateParam = $request->input('billing_date');
        $billingDate = $dateParam ? Carbon::parse($dateParam)->startOfMonth() : Carbon::now()->startOfMonth();
        $generateAllPending = $request->boolean('generate_all_pending');

        $userName = $registration->user?->name ?? 'Jamaah';

        if ($generateAllPending) {
            $stats = $billingService->generateRegistrationAllPendingInvoices($registration, $billingDate);

            return back()->with('success', sprintf(
                'Generate tagihan per orang untuk "%s" dari periode %s s/d %s selesai (%d bulan). Dibuat: %d invoice baru, Dilewati/Sudah Ada: %d.',
                $userName,
                $stats['start_period'],
                $stats['end_period'],
                $stats['months'],
                $stats['created'],
                $stats['skipped']
            ));
        }

        $stats = $billingService->generateRegistrationInvoice($registration, $billingDate);

        return back()->with('success', sprintf(
            'Generate tagihan per orang untuk "%s" periode %s selesai. Dibuat: %d invoice, Dilewati/Sudah Ada: %d.',
            $userName,
            $stats['period'],
            $stats['created'],
            $stats['skipped']
        ));
    }
}
