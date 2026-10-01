<?php
/**
 * File: app/Http/Controllers/ProfileController.php
 * Tujuan: Manajemen profil pengguna (mengubah nama, email, no telepon, dan kata sandi)
 * Dipakai Oleh: routes/web.php (/profile, /profile/password)
 * Dependensi Utama: App\Models\User, Auth, Hash, Rule, Request
 * Daftar Fungsi Utama: edit(), update(), updatePassword()
 * Side Effect: Update DB tabel users kolom name, email, phone, password
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update($validated);

        return back()->with('status_profile', 'Profil akun Anda berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status_password', 'Kata sandi berhasil diperbarui dengan aman.');
    }
}
