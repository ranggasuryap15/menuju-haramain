<!--
File: resources/views/auth/register.blade.php
Tujuan: Halaman pendaftaran akun mandiri untuk calon peserta/jamaah umroh
Dipakai Oleh: AuthController@showRegisterForm (GET /register)
Dependensi Utama: layouts.app, AuthController
Daftar Komponen Utama: Formulir registrasi calon jamaah (Nama Lengkap, Email, No. HP/WA, Kata Sandi)
Side Effect: POST ke /register
-->
@extends('layouts.app')

@section('title', 'Pendaftaran Calon Jama\'ah')

@section('content')
<div class="max-w-md mx-auto my-6 sm:my-10">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden">
        
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-[#346733] to-[#007C6A] p-6 text-white text-center relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full bg-[#D4AF37]/20 pointer-events-none"></div>
            <div class="w-14 h-14 mx-auto rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center text-[#D4AF37] mb-3 shadow-inner">
                <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24">
                    <path d="M12 2L3 8v12h18V8L12 2zm0 3.2L18.4 9H5.6L12 5.2zM5 11h14v7H5v-7zm7 1.5c-1.38 0-2.5 1.12-2.5 2.5s1.12 2.5 2.5 2.5 2.5-1.12 2.5-2.5-1.12-2.5-2.5-2.5z"/>
                </svg>
            </div>
            <h1 class="text-xl font-extrabold tracking-tight">Daftar Akun Tabungan</h1>
            <p class="text-xs text-emerald-100/90 mt-1">Mulai rencanakan ibadah umroh keluarga Anda</p>
        </div>

        <div class="p-6 sm:p-8">
            <form action="{{ route('register') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap (Kepala Akun) *</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                        class="w-full px-4 py-2.5 sm:py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#346733] focus:border-[#346733] text-sm transition-all"
                        placeholder="Contoh: Budi Santoso">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alamat Email *</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                        class="w-full px-4 py-2.5 sm:py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#346733] focus:border-[#346733] text-sm transition-all"
                        placeholder="nama@email.com">
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp / HP *</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone') }}" required
                        class="w-full px-4 py-2.5 sm:py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#346733] focus:border-[#346733] text-sm transition-all"
                        placeholder="08xxxxxxxxxx">
                    @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kata Sandi (Minimal 8 Karakter) *</label>
                    <input type="password" name="password" id="password" required
                        class="w-full px-4 py-2.5 sm:py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#346733] focus:border-[#346733] text-sm transition-all"
                        placeholder="••••••••">
                    @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Konfirmasi Kata Sandi *</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                        class="w-full px-4 py-2.5 sm:py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#346733] focus:border-[#346733] text-sm transition-all"
                        placeholder="••••••••">
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-[#346733] hover:bg-[#234622] text-white font-bold text-xs uppercase tracking-wider shadow-md transition-all">
                    Daftar Akun Sekarang
                </button>
            </form>

            <div class="mt-6 text-center border-t border-slate-100 pt-4">
                <span class="text-xs text-slate-500">Sudah memiliki akun?</span>
                <a href="{{ route('login') }}" class="text-xs font-bold text-[#346733] hover:underline ml-1">Masuk di Sini</a>
            </div>
        </div>
    </div>
</div>
@endsection
