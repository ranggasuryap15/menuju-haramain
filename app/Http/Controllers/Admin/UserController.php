<?php
/**
 * File: app/Http/Controllers/Admin/UserController.php
 * Tujuan: Pengelolaan akun pengguna oleh Superadmin (melihat data jamaah & admin, pembuatan akun baru, pengubahan role jamaah <-> admin, dan reset kata sandi)
 * Dipakai Oleh: Superadmin via routes/web.php (prefix /admin/users)
 * Dependensi Utama: App\Models\User, App\Models\FamilyMember, Illuminate\Support\Facades\Hash, DB
 * Daftar Fungsi Utama: jamaahIndex(), adminIndex(), storeJamaah(), storeAdmin(), update(), resetPassword(), changeRole()
 * Side Effect: Write DB tabel users & family_members (create/update/role switch)
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Menampilkan daftar seluruh data calon jama'ah
     */
    public function jamaahIndex(Request $request): View
    {
        $search = $request->input('search');

        $query = User::query()
            ->where('role', User::ROLE_JAMAAH)
            ->withCount(['familyMembers', 'kloterRegistrations' => function ($q) {
                $q->where('status', 'active');
            }])
            ->with(['kloterRegistrations' => function ($q) {
                $q->where('status', 'active')->with('kloter');
            }]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $jamaahs = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_jamaah' => User::where('role', User::ROLE_JAMAAH)->count(),
            'total_registered_kloter' => DB::table('kloter_registrations')
                ->where('status', 'active')
                ->distinct('user_id')
                ->count('user_id'),
        ];

        return view('admin.users.jamaah', compact('jamaahs', 'search', 'stats'));
    }

    /**
     * Menampilkan daftar seluruh data staf administrator (Superadmin & Admin Keuangan)
     */
    public function adminIndex(Request $request): View
    {
        $search = $request->input('search');

        $query = User::query()
            ->whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN_KEUANGAN]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Superadmin selalu ditampilkan di urutan paling atas
        $admins = $query->orderByRaw("CASE WHEN role = 'superadmin' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_admin' => User::whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN_KEUANGAN])->count(),
            'superadmin_count' => User::where('role', User::ROLE_SUPERADMIN)->count(),
            'admin_keuangan_count' => User::where('role', User::ROLE_ADMIN_KEUANGAN)->count(),
        ];

        return view('admin.users.admins', compact('admins', 'search', 'stats'));
    }

    /**
     * Membuat akun calon jama'ah baru beserta profil anggota keluarga utama
     */
    public function storeJamaah(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'role' => User::ROLE_JAMAAH,
                'password' => Hash::make($validated['password']),
            ]);

            // Buat otomatis profil keluarga utama "Kepala Keluarga"
            FamilyMember::create([
                'user_id' => $user->id,
                'full_name' => $validated['name'],
                'relationship' => 'Kepala Keluarga',
                'phone' => $validated['phone'],
            ]);
        });

        return redirect()->route('admin.users.jamaah')
            ->with('success', "Akun jama'ah atas nama {$validated['name']} berhasil dibuat.");
    }

    /**
     * Membuat akun staf admin baru
     */
    public function storeAdmin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN_KEUANGAN, User::ROLE_SUPERADMIN])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.users.admins')
            ->with('success', "Akun staf administrator ({$validated['name']}) berhasil dibuat.");
    }

    /**
     * Memperbarui informasi dasar pengguna (Nama, Email, No. Telepon)
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $user->update($validated);

        $redirectRoute = $user->role === User::ROLE_JAMAAH
            ? 'admin.users.jamaah'
            : 'admin.users.admins';

        return redirect()->route($redirectRoute)
            ->with('success', "Data pengguna {$user->name} berhasil diperbarui.");
    }

    /**
     * Mereset kata sandi pengguna oleh Superadmin
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = Hash::make($request->password);
        $user->save();

        $redirectRoute = $user->role === User::ROLE_JAMAAH
            ? 'admin.users.jamaah'
            : 'admin.users.admins';

        return redirect()->route($redirectRoute)
            ->with('success', "Kata sandi untuk pengguna {$user->name} berhasil diatur ulang.");
    }

    /**
     * Mengubah peran (role) pengguna:
     * - Menjadikan jama'ah menjadi Admin Keuangan
     * - Melepas Admin Keuangan menjadi Jama'ah biasa
     */
    public function changeRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'target_role' => ['required', Rule::in([User::ROLE_ADMIN_KEUANGAN, User::ROLE_JAMAAH])],
        ]);

        $currentUserId = auth()->id();

        // Keamanan 1: Cegah pengubahan peran akun sendiri yang sedang login
        if ($user->id === $currentUserId) {
            return back()->with('error', 'Anda tidak dapat mengubah hak akses akun Anda sendiri.');
        }

        // Keamanan 2: Akun Superadmin utama tidak dapat diubah perannya
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Hak akses akun Superadmin tidak dapat diubah.');
        }

        $targetRole = $validated['target_role'];
        $oldRole = $user->role;

        $user->role = $targetRole;
        $user->save();

        if ($targetRole === User::ROLE_ADMIN_KEUANGAN) {
            return redirect()->route('admin.users.admins')
                ->with('success', "Pengguna {$user->name} berhasil diangkat menjadi Admin Keuangan.");
        }

        return redirect()->route('admin.users.jamaah')
            ->with('success', "Hak akses admin untuk {$user->name} berhasil dilepas dan kembali menjadi Jama'ah biasa.");
    }
}
