<?php
/**
 * File: app/Http/Controllers/Jamaah/FamilyMemberController.php
 * Tujuan: Manajemen data anggota keluarga/peserta (suami, istri, anak) di dalam akun jamaah (tambah, edit, hapus)
 * Dipakai Oleh: routes/web.php (/jamaah/family, /jamaah/family/{familyMember})
 * Dependensi Utama: App\Models\FamilyMember, Auth, Request
 * Daftar Fungsi Utama: index(), store(), update(), destroy()
 * Side Effect: Write / Update / Delete DB tabel family_members
 */

namespace App\Http\Controllers\Jamaah;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FamilyMemberController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $members = FamilyMember::where('user_id', $user->id)
            ->withCount('registrationPaxes')
            ->latest()
            ->get();

        return view('jamaah.family.index', compact('members'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'in:Kepala Keluarga,Istri,Suami,Anak,Orang Tua,Lainnya'],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:L,P'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $validated['user_id'] = Auth::id();

        FamilyMember::create($validated);

        return redirect()->route('jamaah.family.index')->with('success', 'Data anggota keluarga berhasil ditambahkan.');
    }

    public function update(Request $request, FamilyMember $familyMember): RedirectResponse
    {
        if ($familyMember->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'in:Kepala Keluarga,Istri,Suami,Anak,Orang Tua,Lainnya'],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:L,P'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $familyMember->update($validated);

        return redirect()->route('jamaah.family.index')->with('success', 'Data anggota keluarga berhasil diperbarui.');
    }

    public function destroy(FamilyMember $familyMember): RedirectResponse
    {
        if ($familyMember->user_id !== Auth::id()) {
            abort(403);
        }

        if ($familyMember->registrationPaxes()->exists()) {
            return back()->with('error', 'Anggota keluarga tidak dapat dihapus karena sudah terdaftar dalam kloter aktif.');
        }

        $familyMember->delete();

        return redirect()->route('jamaah.family.index')->with('success', 'Anggota keluarga berhasil dihapus.');
    }
}

