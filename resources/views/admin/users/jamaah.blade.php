<!--
File: resources/views/admin/users/jamaah.blade.php
Tujuan: Halaman manajemen data seluruh jama'ah untuk Superadmin (daftar, pencarian, pembuatan akun baru, edit data, reset password, dan promosi menjadi admin)
Dipakai Oleh: App\Http\Controllers\Admin\UserController@jamaahIndex (GET /admin/users/jamaah)
Dependensi Utama: layouts.app, User, Alpine.js
Daftar Komponen Utama: Kartu Statistik Ringkas, Filter Pencarian, Tabel Data Jama'ah, Modal Buat Jama'ah, Modal Edit Profil, Modal Reset Password, Modal Promosi Admin
Side Effect: Form submit POST /admin/users/jamaah, PUT /admin/users/{user}, PUT /admin/users/{user}/reset-password, POST /admin/users/{user}/role
-->
@extends('layouts.app')

@section('title', 'Data Jama\'ah')

@section('content')
<div class="space-y-6" x-data="{
    createModalOpen: false,
    editModalOpen: false,
    resetPasswordModalOpen: false,
    promoteModalOpen: false,
    selectedUser: { id: null, name: '', email: '', phone: '' },
    openEdit(user) {
        this.selectedUser = { ...user };
        this.editModalOpen = true;
    },
    openResetPassword(user) {
        this.selectedUser = { ...user };
        this.resetPasswordModalOpen = true;
    },
    openPromote(user) {
        this.selectedUser = { ...user };
        this.promoteModalOpen = true;
    }
}">

    <!-- Header & Keterangan -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">Data Jamaah Umroh</h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Kelola seluruh data jama'ah terdaftar, buat akun baru, atur ulang kata sandi, dan kelola hak akses.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.users.admins') }}" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5 border border-slate-200">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>Lihat Data Admin &rarr;</span>
            </a>
            <button type="button" @click="createModalOpen = true" class="px-4 py-2 rounded-xl bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold shadow transition flex items-center gap-1.5 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Buat Akun Jama'ah</span>
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
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 font-semibold block">Total Akun Jama'ah</span>
                <span class="text-2xl font-black text-slate-900 mt-1 block">{{ number_format($stats['total_jamaah'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-slate-400">Akun terdaftar di sistem</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#346733] flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs text-slate-500 font-semibold block">Jama'ah Terdaftar Kloter Aktif</span>
                <span class="text-2xl font-black text-[#007C6A] mt-1 block">{{ number_format($stats['total_registered_kloter'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-slate-400">Sudah bergabung dalam kloter aktif</span>
            </div>
            <div class="w-12 h-12 rounded-xl bg-teal-50 text-[#007C6A] flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
        </div>
    </div>

    <!-- Filter & Pencarian -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-3 items-center justify-between">
        <form action="{{ route('admin.users.jamaah') }}" method="GET" class="w-full sm:max-w-md flex items-center gap-2">
            <div class="relative w-full">
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama, email, atau no. telepon..."
                       class="w-full pl-9 pr-4 py-2 border border-slate-300 rounded-xl text-xs focus:ring-2 focus:ring-[#346733] focus:border-transparent outline-none">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition">
                Cari
            </button>
            @if($search)
                <a href="{{ route('admin.users.jamaah') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition">
                    Reset
                </a>
            @endif
        </form>

        <span class="text-xs text-slate-500 self-end sm:self-center">
            Menampilkan {{ $jamaahs->total() }} jama'ah
        </span>
    </div>

    <!-- Tabel Data Jama'ah Desktop & Card Mobile -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Desktop Table (hidden di mobile) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4">Nama Jama'ah</th>
                        <th class="py-3 px-4">Kontak</th>
                        <th class="py-3 px-4">Kloter Aktif</th>
                        <th class="py-3 px-4 text-center">Anggota Keluarga</th>
                        <th class="py-3 px-4">Bergabung</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($jamaahs as $jamaah)
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Nama & Avatar -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-xl bg-emerald-100 text-[#346733] font-black flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($jamaah->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-900 block">{{ $jamaah->name }}</span>
                                        <span class="text-[11px] text-slate-400">ID: #{{ $jamaah->id }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Kontak -->
                            <td class="py-3.5 px-4">
                                <span class="block text-slate-800 font-medium">{{ $jamaah->email }}</span>
                                <span class="block text-[11px] text-slate-500">{{ $jamaah->phone ?? '-' }}</span>
                            </td>

                            <!-- Kloter Aktif -->
                            <td class="py-3.5 px-4">
                                @if($jamaah->kloterRegistrations->isNotEmpty())
                                    <div class="flex flex-col gap-1">
                                        @foreach($jamaah->kloterRegistrations as $reg)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 max-w-fit">
                                                {{ $reg->kloter->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-slate-400 text-[11px] italic">Belum daftar kloter</span>
                                @endif
                            </td>

                            <!-- Anggota Keluarga -->
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                                    {{ $jamaah->family_members_count }} Orang
                                </span>
                            </td>

                            <!-- Tanggal Bergabung -->
                            <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                {{ $jamaah->created_at->format('d M Y') }}
                                <span class="block text-[10px] text-slate-400">{{ $jamaah->created_at->diffForHumans() }}</span>
                            </td>

                            <!-- Tombol Aksi -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5 justify-end">
                                    <!-- Edit -->
                                    <button type="button"
                                            @click="openEdit({ id: {{ $jamaah->id }}, name: '{{ addslashes($jamaah->name) }}', email: '{{ addslashes($jamaah->email) }}', phone: '{{ addslashes($jamaah->phone ?? '') }}' })"
                                            class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 font-semibold text-[11px] transition cursor-pointer"
                                            title="Ubah data profil">
                                        Edit
                                    </button>

                                    <!-- Reset Password -->
                                    <button type="button"
                                            @click="openResetPassword({ id: {{ $jamaah->id }}, name: '{{ addslashes($jamaah->name) }}' })"
                                            class="px-2.5 py-1.5 rounded-lg border border-amber-300 text-amber-900 bg-amber-50 hover:bg-amber-100 font-semibold text-[11px] transition cursor-pointer"
                                            title="Atur ulang kata sandi">
                                        Reset Password
                                    </button>

                                    <!-- Jadikan Admin -->
                                    <button type="button"
                                            @click="openPromote({ id: {{ $jamaah->id }}, name: '{{ addslashes($jamaah->name) }}' })"
                                            class="px-2.5 py-1.5 rounded-lg border border-teal-300 text-[#007C6A] bg-teal-50 hover:bg-teal-100 font-semibold text-[11px] transition cursor-pointer"
                                            title="Jadikan staf Admin Keuangan">
                                        Jadikan Admin &rarr;
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">
                                Tidak ada data jama'ah yang sesuai dengan pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List (tampil di mobile) -->
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($jamaahs as $jamaah)
                <div class="p-4 space-y-3">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-9 h-9 rounded-xl bg-emerald-100 text-[#346733] font-black flex items-center justify-center shrink-0">
                                {{ strtoupper(substr($jamaah->name, 0, 1)) }}
                            </div>
                            <div>
                                <span class="font-bold text-slate-900 block text-sm">{{ $jamaah->name }}</span>
                                <span class="text-[11px] text-slate-500 block">{{ $jamaah->email }} &bull; {{ $jamaah->phone ?? '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="text-xs text-slate-600 space-y-1 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Keluarga:</span>
                            <span class="font-bold text-slate-700">{{ $jamaah->family_members_count }} Orang</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Kloter Aktif:</span>
                            <span class="font-semibold text-slate-800">
                                @if($jamaah->kloterRegistrations->isNotEmpty())
                                    {{ $jamaah->kloterRegistrations->first()->kloter->name }}
                                @else
                                    <span class="italic text-slate-400">Belum ada</span>
                                @endif
                            </span>
                        </div>
                    </div>

                    <!-- Tombol Aksi Mobile -->
                    <div class="grid grid-cols-3 gap-1.5 pt-1">
                        <button type="button"
                                @click="openEdit({ id: {{ $jamaah->id }}, name: '{{ addslashes($jamaah->name) }}', email: '{{ addslashes($jamaah->email) }}', phone: '{{ addslashes($jamaah->phone ?? '') }}' })"
                                class="py-1.5 text-center rounded-lg border border-slate-200 text-slate-700 bg-white font-bold text-[10px]">
                            Edit
                        </button>
                        <button type="button"
                                @click="openResetPassword({ id: {{ $jamaah->id }}, name: '{{ addslashes($jamaah->name) }}' })"
                                class="py-1.5 text-center rounded-lg border border-amber-300 text-amber-900 bg-amber-50 font-bold text-[10px]">
                            Reset Pass
                        </button>
                        <button type="button"
                                @click="openPromote({ id: {{ $jamaah->id }}, name: '{{ addslashes($jamaah->name) }}' })"
                                class="py-1.5 text-center rounded-lg border border-teal-300 text-[#007C6A] bg-teal-50 font-bold text-[10px]">
                            Jadi Admin
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-slate-500 text-xs">
                    Tidak ada data jama'ah.
                </div>
            @endforelse
        </div>

        @if($jamaahs->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $jamaahs->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: BUAT AKUN JAMA'AH BARU                                          -->
    <!-- ========================================================================= -->
    <div x-show="createModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div x-show="createModalOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm cursor-pointer" @click="createModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="createModalOpen" x-transition.scale class="relative w-full max-w-lg bg-white rounded-2xl p-6 shadow-2xl space-y-4 border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-base font-extrabold text-slate-900">Buat Akun Jama'ah Baru</h2>
                    <button type="button" @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form action="{{ route('admin.users.jamaah.store') }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Jama'ah <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required placeholder="Contoh: H. Ahmad Subardjo"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#346733] outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alamat Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required placeholder="nama@domain.com"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#346733] outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nomor WhatsApp / HP <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" required placeholder="081234567890"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#346733] outline-none">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Kata Sandi <span class="text-red-500">*</span></label>
                            <input type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter"
                                   class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#346733] outline-none">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Konfirmasi Sandi <span class="text-red-500">*</span></label>
                            <input type="password" name="password_confirmation" required minlength="8" placeholder="Ulangi kata sandi"
                                   class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#346733] outline-none">
                        </div>
                    </div>

                    <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-emerald-900 text-[11px]">
                        ℹ️ Sistem akan otomatis mendaftarkan profil keluarga utama "Kepala Keluarga" untuk jama'ah ini.
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[#346733] hover:bg-[#234622] text-white font-bold transition shadow cursor-pointer">
                            Simpan Akun Jama'ah
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: EDIT DATA JAMA'AH                                               -->
    <!-- ========================================================================= -->
    <div x-show="editModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div x-show="editModalOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm cursor-pointer" @click="editModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="editModalOpen" x-transition.scale class="relative w-full max-w-lg bg-white rounded-2xl p-6 shadow-2xl space-y-4 border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-base font-extrabold text-slate-900">Ubah Data Jama'ah</h2>
                    <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
                </div>

                <form :action="'{{ url('/admin/users') }}/' + selectedUser.id" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="selectedUser.name" required
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#346733] outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alamat Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" x-model="selectedUser.email" required
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#346733] outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nomor WhatsApp / HP <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" x-model="selectedUser.phone" required
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#346733] outline-none">
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[#346733] hover:bg-[#234622] text-white font-bold transition shadow cursor-pointer">
                            Perbarui Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: RESET PASSWORD USER                                             -->
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
    <!-- MODAL 4: JADIKAN ADMIN (PROMOTE)                                         -->
    <!-- ========================================================================= -->
    <div x-show="promoteModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div x-show="promoteModalOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm cursor-pointer" @click="promoteModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="promoteModalOpen" x-transition.scale class="relative w-full max-w-md bg-white rounded-2xl p-6 shadow-2xl space-y-4 border border-slate-200 text-center">
                <div class="w-12 h-12 rounded-full bg-teal-100 text-[#007C6A] mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>

                <div class="space-y-1">
                    <h2 class="text-base font-extrabold text-slate-900">Jadikan Admin Keuangan?</h2>
                    <p class="text-xs text-slate-600">
                        Pengguna <strong class="text-slate-900" x-text="selectedUser.name"></strong> akan diberikan hak akses staf <strong>Admin Keuangan</strong> untuk memverifikasi pembayaran dan pendaftaran kloter.
                    </p>
                </div>

                <form :action="'{{ url('/admin/users') }}/' + selectedUser.id + '/role'" method="POST" class="pt-2">
                    @csrf
                    <input type="hidden" name="target_role" value="admin_keuangan">

                    <div class="flex items-center justify-center gap-2">
                        <button type="button" @click="promoteModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition cursor-pointer text-xs">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-[#007C6A] hover:bg-[#006052] text-white font-bold transition shadow cursor-pointer text-xs">
                            Ya, Jadikan Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
