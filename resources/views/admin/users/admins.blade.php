<!--
File: resources/views/admin/users/admins.blade.php
Tujuan: Halaman manajemen staf administrator oleh Superadmin (melihat daftar admin, pembuatan admin baru, edit data, reset password, dan pelepasan hak akses menjadi jamaah)
Dipakai Oleh: App\Http\Controllers\Admin\UserController@adminIndex (GET /admin/users/admins)
Dependensi Utama: layouts.app, User, Alpine.js
Daftar Komponen Utama: Kartu Statistik Staf, Filter Pencarian, Tabel Data Admin, Modal Buat Admin, Modal Edit Profil, Modal Reset Password, Modal Pelepasan Akses
Side Effect: Form submit POST /admin/users/admin, PUT /admin/users/{user}, PUT /admin/users/{user}/reset-password, POST /admin/users/{user}/role
-->
@extends('layouts.app')

@section('title', 'Data Admin & Staf')

@section('content')
<div class="space-y-6" x-data="{
    createModalOpen: false,
    editModalOpen: false,
    resetPasswordModalOpen: false,
    demoteModalOpen: false,
    selectedUser: { id: null, name: '', email: '', phone: '', role: '' },
    openEdit(user) {
        this.selectedUser = { ...user };
        this.editModalOpen = true;
    },
    openResetPassword(user) {
        this.selectedUser = { ...user };
        this.resetPasswordModalOpen = true;
    },
    openDemote(user) {
        this.selectedUser = { ...user };
        this.demoteModalOpen = true;
    }
}">

    <!-- Header & Keterangan -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">Data Staf Administrator</h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Kelola hak akses administrator sistem, buat akun admin baru, atur ulang kata sandi, atau kembalikan menjadi jama'ah biasa.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.users.jamaah') }}" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 border border-slate-200">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Lihat Data Jama'ah &rarr;</span>
            </a>
            <button type="button" @click="createModalOpen = true" class="px-4 py-2 rounded-xl bg-[#007C6A] hover:bg-[#006052] text-white text-xs font-bold shadow transition flex items-center gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Buat Akun Admin</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs font-semibold space-y-1">
            <div class="font-bold flex items-center gap-1">
                <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Terdapat kesalahan pengisian form:</span>
            </div>
            <ul class="list-disc list-inside pl-5 space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Kartu Statistik Singkat -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 font-semibold block">Total Staf Admin</span>
                <span class="text-2xl font-black text-slate-900 mt-1 block">{{ number_format($stats['total_admin'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-slate-400">Pengelola sistem aktif</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 font-semibold block">Superadmin</span>
                <span class="text-2xl font-black text-amber-600 mt-1 block">{{ number_format($stats['superadmin_count'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-slate-400">Hak akses tertinggi</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 font-semibold block">Admin Keuangan</span>
                <span class="text-2xl font-black text-[#007C6A] mt-1 block">{{ number_format($stats['admin_keuangan_count'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-slate-400">Verifikator transaksi & kloter</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-teal-50 text-[#007C6A] flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    <!-- Filter & Pencarian -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-3 items-center justify-between">
        <form action="{{ route('admin.users.admins') }}" method="GET" class="w-full sm:max-w-md flex items-center gap-2">
            <div class="relative w-full">
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, email, atau no. telepon..."
                       class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-[#007C6A] focus:border-transparent outline-none">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition">
                Cari
            </button>
            @if($search)
                <a href="{{ route('admin.users.admins') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition">
                    Reset
                </a>
            @endif
        </form>

        <span class="text-xs text-slate-500 self-end sm:self-center">
            Menampilkan {{ $admins->total() }} staf
        </span>
    </div>

    <!-- Tabel Data Admin Desktop & Card Mobile -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Desktop Table (hidden di mobile) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4">Nama Administrator</th>
                        <th class="py-3 px-4">Kontak</th>
                        <th class="py-3 px-4 text-center">Peran (Role)</th>
                        <th class="py-3 px-4">Terdaftar</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($admins as $admin)
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Nama & Avatar -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-xl {{ $admin->isSuperAdmin() ? 'bg-amber-100 text-amber-900' : 'bg-teal-100 text-teal-800' }} font-black flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($admin->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-slate-900">{{ $admin->name }}</span>
                                            @if($admin->id === auth()->id())
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-blue-100 text-blue-800 border border-blue-200">Anda</span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-slate-400">ID: #{{ $admin->id }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Kontak -->
                            <td class="py-3.5 px-4">
                                <span class="block text-slate-800 font-medium">{{ $admin->email }}</span>
                                <span class="block text-[11px] text-slate-500">{{ $admin->phone ?? '-' }}</span>
                            </td>

                            <!-- Peran / Role Badge -->
                            <td class="py-3.5 px-4 text-center">
                                @if($admin->isSuperAdmin())
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                        👑 Superadmin
                                    </span>
                                @elseif($admin->isAdminKeuangan())
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-teal-100 text-teal-900 border border-teal-300">
                                        💼 Admin Keuangan
                                    </span>
                                @endif
                            </td>

                            <!-- Tanggal Terdaftar -->
                            <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                {{ $admin->created_at->format('Y-m-d') }}
                                <span class="block text-[10px] text-slate-400">{{ $admin->created_at->diffForHumans() }}</span>
                            </td>

                            <!-- Tombol Aksi -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <!-- Edit Profil -->
                                    <button type="button"
                                            @click="openEdit({ id: {{ $admin->id }}, name: '{{ addslashes($admin->name) }}', email: '{{ addslashes($admin->email) }}', phone: '{{ addslashes($admin->phone ?? '') }}' })"
                                            class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 font-semibold text-[11px] transition cursor-pointer"
                                            title="Ubah data profil">
                                        Edit
                                    </button>

                                    <!-- Reset Password -->
                                    <button type="button"
                                            @click="openResetPassword({ id: {{ $admin->id }}, name: '{{ addslashes($admin->name) }}' })"
                                            class="px-2.5 py-1.5 rounded-lg border border-amber-300 text-amber-900 bg-amber-50 hover:bg-amber-100 font-semibold text-[11px] transition cursor-pointer"
                                            title="Atur ulang kata sandi">
                                        Reset Password
                                    </button>

                                    <!-- Lepas Role Admin menjadi Jamaah (khusus admin_keuangan dan bukan diri sendiri) -->
                                    @if($admin->isAdminKeuangan() && $admin->id !== auth()->id())
                                        <button type="button"
                                                @click="openDemote({ id: {{ $admin->id }}, name: '{{ addslashes($admin->name) }}' })"
                                                class="px-2.5 py-1.5 rounded-lg border border-red-200 text-red-700 bg-red-50 hover:bg-red-100 font-semibold text-[11px] transition cursor-pointer"
                                                title="Lepas hak akses admin">
                                            Lepas Admin &rarr;
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500">
                                Tidak ada data staf administrator.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List (tampil di mobile) -->
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($admins as $admin)
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-9 h-9 rounded-xl {{ $admin->isSuperAdmin() ? 'bg-amber-100 text-amber-900' : 'bg-teal-100 text-teal-800' }} font-black flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($admin->name, 0, 1)) }}
                            </div>
                            <div>
                                <span class="font-bold text-slate-900 block text-sm">{{ $admin->name }}</span>
                                <span class="text-[11px] text-slate-500 block">{{ $admin->email }} &bull; {{ $admin->phone ?? '-' }}</span>
                            </div>
                        </div>

                        <div>
                            @if($admin->isSuperAdmin())
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">Superadmin</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-100 text-teal-900 border border-teal-300">Keuangan</span>
                            @endif
                        </div>
                    </div>

                    <!-- Tombol Aksi Mobile -->
                    <div class="flex items-center gap-1.5 pt-1">
                        <button type="button"
                                @click="openEdit({ id: {{ $admin->id }}, name: '{{ addslashes($admin->name) }}', email: '{{ addslashes($admin->email) }}', phone: '{{ addslashes($admin->phone ?? '') }}' })"
                                class="flex-1 py-1.5 text-center rounded-lg border border-slate-200 text-slate-700 bg-white font-bold text-[10px]">
                            Edit
                        </button>
                        <button type="button"
                                @click="openResetPassword({ id: {{ $admin->id }}, name: '{{ addslashes($admin->name) }}' })"
                                class="flex-1 py-1.5 text-center rounded-lg border border-amber-300 text-amber-900 bg-amber-50 font-bold text-[10px]">
                            Reset Pass
                        </button>
                        @if($admin->isAdminKeuangan() && $admin->id !== auth()->id())
                            <button type="button"
                                    @click="openDemote({ id: {{ $admin->id }}, name: '{{ addslashes($admin->name) }}' })"
                                    class="flex-1 py-1.5 text-center rounded-lg border border-red-200 text-red-700 bg-red-50 font-bold text-[10px]">
                                Lepas Admin
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-slate-500 text-xs">
                    Tidak ada data administrator.
                </div>
            @endforelse
        </div>

        @if($admins->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $admins->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: BUAT AKUN ADMIN BARU                                            -->
    <!-- ========================================================================= -->
    <div x-show="createModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div x-show="createModalOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm cursor-pointer" @click="createModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="createModalOpen" x-transition.scale class="relative w-full max-w-lg bg-white rounded-2xl p-6 shadow-2xl space-y-4 border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-base font-extrabold text-slate-900">Buat Akun Administrator Baru</h2>
                    <button type="button" @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form action="{{ route('admin.users.admin.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Administrator <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required placeholder="Contoh: Muhammad Ilham, S.E."
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#007C6A] outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alamat Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required placeholder="admin@domain.com"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#007C6A] outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nomor WhatsApp / HP <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" required placeholder="081234567890"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#007C6A] outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tingkat Hak Akses (Role) <span class="text-red-500">*</span></label>
                        <select name="role" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#007C6A] outline-none bg-white">
                            <option value="admin_keuangan" selected>Admin Keuangan (Verifikasi Transaksi & Kloter)</option>
                            <option value="superadmin">Superadmin (Akses Penuh Seluruh Sistem & Akun)</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kata Sandi <span class="text-red-500">*</span></label>
                            <input type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter"
                                   class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#007C6A] outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Konfirmasi Sandi <span class="text-red-500">*</span></label>
                            <input type="password" name="password_confirmation" required minlength="8" placeholder="Ulangi kata sandi"
                                   class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#007C6A] outline-none">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[#007C6A] hover:bg-[#006052] text-white font-bold transition shadow cursor-pointer">
                            Simpan Akun Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: EDIT DATA ADMIN                                                 -->
    <!-- ========================================================================= -->
    <div x-show="editModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div x-show="editModalOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm cursor-pointer" @click="editModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="editModalOpen" x-transition.scale class="relative w-full max-w-lg bg-white rounded-2xl p-6 shadow-2xl space-y-4 border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-base font-extrabold text-slate-900">Ubah Data Administrator</h2>
                    <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form :action="'{{ url('/admin/users') }}/' + selectedUser.id" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="selectedUser.name" required
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#007C6A] outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alamat Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" x-model="selectedUser.email" required
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#007C6A] outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nomor WhatsApp / HP <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" x-model="selectedUser.phone" required
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#007C6A] outline-none">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[#007C6A] hover:bg-[#006052] text-white font-bold transition shadow cursor-pointer">
                            Perbarui Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: RESET PASSWORD ADMIN                                            -->
    <!-- ========================================================================= -->
    <div x-show="resetPasswordModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div x-show="resetPasswordModalOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm cursor-pointer" @click="resetPasswordModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="resetPasswordModalOpen" x-transition.scale class="relative w-full max-w-md bg-white rounded-2xl p-6 shadow-2xl space-y-4 border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-base font-extrabold text-slate-900">Atur Ulang Kata Sandi</h2>
                    <button type="button" @click="resetPasswordModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <p class="text-xs text-slate-600">
                    Masukkan kata sandi baru untuk <strong class="text-slate-900" x-text="selectedUser.name"></strong>.
                </p>

                <form :action="'{{ url('/admin/users') }}/' + selectedUser.id + '/reset-password'" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kata Sandi Baru <span class="text-red-500">*</span></label>
                        <input type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-500 outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Konfirmasi Kata Sandi Baru <span class="text-red-500">*</span></label>
                        <input type="password" name="password_confirmation" required minlength="8" placeholder="Ulangi kata sandi baru"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-amber-500 outline-none">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="resetPasswordModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold transition shadow cursor-pointer">
                            Simpan Sandi Baru
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: LEPAS ADMIN JADI JAMAAH (DEMOTE)                                -->
    <!-- ========================================================================= -->
    <div x-show="demoteModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div x-show="demoteModalOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm cursor-pointer" @click="demoteModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="demoteModalOpen" x-transition.scale class="relative w-full max-w-md bg-white rounded-2xl p-6 shadow-2xl space-y-4 border border-slate-200 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6"/></svg>
                </div>

                <div class="space-y-1">
                    <h2 class="text-base font-extrabold text-slate-900">Lepas Hak Akses Admin?</h2>
                    <p class="text-xs text-slate-600">
                        Hak akses staf admin untuk <strong class="text-slate-900" x-text="selectedUser.name"></strong> akan dicabut dan akun akan dialihkan menjadi <strong>Jama'ah biasa</strong>.
                    </p>
                </div>

                <form :action="'{{ url('/admin/users') }}/' + selectedUser.id + '/role'" method="POST" class="pt-2">
                    @csrf
                    <input type="hidden" name="target_role" value="jamaah">

                    <div class="flex items-center justify-center gap-2">
                        <button type="button" @click="demoteModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition cursor-pointer text-xs">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold transition shadow cursor-pointer text-xs">
                            Ya, Lepas Hak Akses
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
