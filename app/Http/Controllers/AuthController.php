<?php
/**
 * File: app/Http/Controllers/AuthController.php
 * Tujuan: Manajemen autentikasi pengguna (login, registrasi calon jamaah baru, logout) dengan guard redirect jika sudah login
 * Dipakai Oleh: routes/web.php (/login, /register, /logout)
 * Dependensi Utama: Illuminate\Support\Facades\Auth, App\Models\User, App\Models\FamilyMember, Hash
 * Daftar Fungsi Utama: showLoginForm(), login(), showRegisterForm(), register(), logout()
 * Side Effect: Sesi login dibuat/dihapus, session regeneration, insert data user baru
 */

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            return $user->isStaff()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('jamaah.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            /** @var User $user */
            $user = Auth::user();
            if ($user->isStaff()) {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('jamaah.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    public function showRegisterForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            return $user->isStaff()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('jamaah.dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $newUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'role' => User::ROLE_JAMAAH,
                'password' => Hash::make($validated['password']),
            ]);

            // Otomatis daftarkan diri sendiri sebagai Kepala Keluarga pada data anggota
            FamilyMember::create([
                'user_id' => $newUser->id,
                'full_name' => $validated['name'],
                'relationship' => 'Kepala Keluarga',
                'phone' => $validated['phone'],
            ]);

            return $newUser;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('jamaah.dashboard')->with('success', 'Selamat datang! Akun tabungan umroh Anda berhasil dibuat.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Anda telah berhasil keluar.');
    }
}
