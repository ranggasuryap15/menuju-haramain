<?php

/**
 * File: app/Http/Controllers/Admin/BankAccountController.php
 * Tujuan: Manajemen rekening bank penampung tabungan umroh (penambahan rekening baru dan pengubahan detail rekening yang sudah ada) bagi Superadmin dan Admin Keuangan
 * Dipakai Oleh: routes/web.php (/admin/bank-accounts, /admin/bank-accounts/{bankAccount})
 * Dependensi Utama: App\Models\BankAccount, App\Models\Kloter, Request, RedirectResponse
 * Daftar Fungsi Utama: store(), update()
 * Side Effect: Write DB tabel bank_accounts (insert/update) dan tabel pivot kloter_bank_account (syncWithoutDetaching)
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Kloter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    /**
     * Menyimpan rekening bank penampung baru dan menautkannya ke kloter bila kloter_id disediakan
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'kloter_id' => ['nullable', 'exists:kloters,id'],
        ]);

        $bankAccount = BankAccount::create([
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_holder' => $validated['account_holder'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        if (!empty($validated['kloter_id'])) {
            $kloter = Kloter::find($validated['kloter_id']);
            if ($kloter) {
                $kloter->bankAccounts()->syncWithoutDetaching([$bankAccount->id]);
            }
        }

        return back()->with('success', "Rekening {$bankAccount->bank_name} ({$bankAccount->account_number}) berhasil ditambahkan.");
    }

    /**
     * Memperbarui informasi detail rekening bank yang sudah ada
     */
    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $bankAccount->update([
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_holder' => $validated['account_holder'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Detail rekening {$bankAccount->bank_name} ({$bankAccount->account_number}) berhasil diperbarui.");
    }
}
