<!--
File: resources/views/auth/login.blade.php
Tujuan: Halaman login pengguna (email dan kata sandi) untuk masuk ke portal sistem
Dipakai Oleh: AuthController@showLoginForm (GET /login)
Dependensi Utama: layouts.app, AuthController
Daftar Komponen Utama: Form kredensial login (email, password), tombol submit, link registrasi
Side Effect: POST ke /login
-->
@extends('layouts.app')

@section('title', 'Masuk Akun')

@section('content')
<div class="max-w-md mx-auto my-6 sm:my-10">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden">
        
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-[#346733] to-[#234622] p-6 text-white text-center relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full bg-[#D4AF37]/20 pointer-events-none"></div>
            <div class="w-14 h-14 mx-auto rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center text-[#D4AF37] mb-3 shadow-inner">
                <svg class="w-8 h-8 fill-current" viewBox="0 0 24 24">
                    <path d="M12 2L3 8v12h18V8L12 2zm0 3.2L18.4 9H5.6L12 5.2zM5 11h14v7H5v-7zm7 1.5c-1.38 0-2.5 1.12-2.5 2.5s1.12 2.5 2.5 2.5 2.5-1.12 2.5-2.5-1.12-2.5-2.5-2.5z"/>
                </svg>
            </div>
            <h1 class="text-xl font-extrabold tracking-tight">Menuju Haramain</h1>
            <p class="text-xs text-emerald-100/90 mt-1">Portal Tabungan & Angsuran Umroh Keluarga</p>
        </div>

        <!-- Login Form -->
        <div class="p-6 sm:p-8">
            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alamat Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                        class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#346733] focus:border-[#346733] text-sm transition-all"
                        placeholder="nama@email.com">
                    @error('email')
                        <p class="text-xs text-red-600 mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Kata Sandi</label>
                        <span class="text-[11px] text-slate-400">Default: password</span>
                    </div>
                    <input type="password" name="password" id="password" required
                        class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-[#346733] focus:border-[#346733] text-sm transition-all"
                        placeholder="••••••••">
                    @error('password')
                        <p class="text-xs text-red-600 mt-1 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" class="w-4 h-4 text-[#346733] rounded border-slate-300 focus:ring-[#346733]">
                    <label for="remember" class="ml-2 text-xs text-slate-600 font-medium">Ingat sesi saya</label>
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-[#346733] hover:bg-[#234622] text-white font-bold text-sm tracking-wide shadow-md hover:shadow-lg transition-all transform active:scale-[0.99]">
                    Masuk ke Portal
                </button>
            </form>

            <div class="mt-4 text-center">
                <span class="text-xs text-slate-500">Belum punya akun tabungan?</span>
                <a href="{{ route('register') }}" class="text-xs font-bold text-[#007C6A] hover:underline ml-1">Daftar Sekarang</a>
            </div>

        </div>
    </div>
</div>
@endsection
