<!--
File: resources/views/profile/edit.blade.php
Tujuan: Halaman pengaturan profil lengkap pengguna (ubah nama, email, no WhatsApp, dan ubah kata sandi)
Dipakai Oleh: App\Http\Controllers\ProfileController@edit (GET /profile)
Dependensi Utama: layouts.app, User, Alpine.js
Daftar Komponen Utama: Formulir biodata akun, Formulir ubah kata sandi, Kartu ringkasan aktivitas akun
Side Effect: PUT ke /profile (update biodata) dan PUT ke /profile/password (update password)
-->
@extends('layouts.app')

@section('title', 'Profil Pengguna')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header & Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">Pengaturan Akun & Profil</h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Kelola informasi pribadi dan keamanan kata sandi akun Anda.
            </p>
        </div>
        <a href="{{ auth()->user()->isStaff() ? route('admin.dashboard') : route('jamaah.dashboard') }}"
           class="text-xs font-bold text-[#346733] hover:underline flex items-center space-x-1">
            <span>&larr; Kembali ke Dasbor</span>
        </a>
    </div>

    <!-- Alert Notifikasi Global -->
    @if(session('status_profile'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('status_profile') }}</span>
        </div>
    @endif

    @if(session('status_password'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('status_password') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Kolom Kiri (2 Kolom): Formulir Profil & Kata Sandi -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Formulir Biodata Akun -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                        <span class="p-1.5 rounded-lg bg-emerald-100 text-[#346733]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        <span>Informasi Pribadi</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Perbarui nama lengkap, email, dan nomor kontak yang dapat dihubungi.</p>
                </div>

                <form action="{{ route('profile.update') }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <!-- Nama Lengkap -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" required value="{{ old('name', $user->name) }}"
                               class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none transition-all">
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Alamat Email -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Alamat Email <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="email" required value="{{ old('email', $user->email) }}"
                                   class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none transition-all">
                            @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <!-- No Telepon / WA -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                No. Telepon / WhatsApp
                            </label>
                            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Contoh: 081234567890"
                                   class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none transition-all">
                            @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold shadow transition cursor-pointer">
                            Simpan Perubahan Profil
                        </button>
                    </div>
                </form>
            </div>

            <!-- Formulir Ubah Kata Sandi -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                        <span class="p-1.5 rounded-lg bg-teal-100 text-[#007C6A]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <span>Perbarui Kata Sandi</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Gunakan kata sandi yang panjang dan kuat untuk melindungi akun tabungan umroh Anda.</p>
                </div>

                <form action="{{ route('profile.password.update') }}" method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <!-- Kata Sandi Saat Ini -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Kata Sandi Saat Ini <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="current_password" required
                               placeholder="Masukkan kata sandi lama Anda"
                               class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#007C6A] focus:border-[#007C6A] outline-none transition-all">
                        @error('current_password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Kata Sandi Baru -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Kata Sandi Baru <span class="text-red-500">*</span>
                            </label>
                            <input type="password" name="password" required minlength="8"
                                   placeholder="Minimal 8 karakter"
                                   class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#007C6A] focus:border-[#007C6A] outline-none transition-all">
                            @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <!-- Konfirmasi Kata Sandi Baru -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Ulangi Kata Sandi Baru <span class="text-red-500">*</span>
                            </label>
                            <input type="password" name="password_confirmation" required minlength="8"
                                   placeholder="Ketik ulang kata sandi baru"
                                   class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#007C6A] focus:border-[#007C6A] outline-none transition-all">
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold shadow transition cursor-pointer">
                            Perbarui Kata Sandi
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <!-- Kolom Kanan (1 Kolom): Kartu Status Akun & Keamanan -->
        <div class="space-y-6">

            <!-- Ringkasan Akun -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <div class="flex items-center space-x-3 pb-3 border-b border-slate-100">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-[#346733] font-black text-lg flex items-center justify-center">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">{{ $user->name }}</h3>
                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold {{ $user->isStaff() ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-[#346733]' }}">
                            {{ $user->role === 'superadmin' ? 'Superadmin' : ($user->role === 'admin_keuangan' ? 'Admin Keuangan' : 'Jama\'ah Tabungan') }}
                        </span>
                    </div>
                </div>

                <div class="text-xs space-y-2 text-slate-600">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Terdaftar Sejak:</span>
                        <span class="font-medium text-slate-800">{{ $user->created_at->format('Y-m-d') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Anggota Keluarga:</span>
                        <span class="font-bold text-slate-800">{{ $user->familyMembers()->count() }} Orang</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Kloter Diikuti:</span>
                        <span class="font-bold text-[#346733]">{{ $user->kloterRegistrations()->count() }} Kloter</span>
                    </div>
                </div>
            </div>

            <!-- Petunjuk Keamanan -->
            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-5 space-y-2.5 text-xs text-slate-600">
                <div class="flex items-center space-x-1.5 font-bold text-slate-800">
                    <svg class="w-4 h-4 text-[#346733]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Informasi Akun</span>
                </div>
                <p class="leading-relaxed text-[11px]">
                    Pastikan nomor WhatsApp dan alamat email Anda aktif untuk menerima pemberitahuan jatuh tempo tagihan bulanan dan update status pembayaran.
                </p>
            </div>

        </div>

    </div>

</div>
@endsection
