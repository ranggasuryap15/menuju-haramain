<!--
File: resources/views/admin/dashboard.blade.php
Tujuan: Dasbor utama pengelola dan admin keuangan untuk memantau penerimaan kas, antrean verifikasi bukti transfer, dan status kloter
Dipakai Oleh: Admin\DashboardController@index (GET /admin/dashboard)
Dependensi Utama: layouts.app, Payment, Kloter, User
Daftar Komponen Utama: Metrik keuangan (Kas Masuk, Antrean Pending, Jamaah Aktif), Antrean Verifikasi Cepat, Daftar Kloter Aktif
Side Effect: Menampilkan agregasi data keuangan dan navigasi approval
-->
@extends('layouts.app')

@section('title', 'Dasbor Administrator & Keuangan')

@section('content')
<div class="space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#346733] text-white">Administrator</span>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900">Dasbor Keuangan & Operasional</h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Kelola approval bukti transfer manual, master kloter umroh, dan penerbitan tagihan bulanan.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Shortcut Trigger Generate Billing Tanggal 1 -->
            <form action="{{ route('admin.kloters.trigger-billing') }}" method="POST" onsubmit="return confirm('Jalankan generator tagihan bulanan untuk seluruh kloter aktif sekarang?')">
                @csrf
                <button type="submit" class="inline-flex items-center px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-sm transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Terbitkan Tagihan Bulan Ini
                </button>
            </form>

            <a href="{{ route('admin.payments.index') }}" class="inline-flex items-center px-3.5 py-2 rounded-xl bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold shadow-sm transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Antrean Approval ({{ $pendingApprovalsCount }})
            </a>
        </div>
    </div>

    <!-- 4 Metrik Utama -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Total Kas Terverifikasi -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-2">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total Kas Terverifikasi</span>
            <span class="text-xl sm:text-2xl font-black text-[#346733] block">Rp {{ number_format($totalApprovedFunds, 0, ',', '.') }}</span>
            <span class="text-[11px] text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-semibold inline-block">Dana riil masuk mutasi</span>
        </div>

        <!-- Antrean Pending Approval -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-2">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Menunggu Approval</span>
            <div class="flex items-baseline space-x-2">
                <span class="text-xl sm:text-2xl font-black {{ $pendingApprovalsCount > 0 ? 'text-amber-600' : 'text-slate-800' }}">
                    {{ $pendingApprovalsCount }}
                </span>
                <span class="text-xs text-slate-500">Bukti Transfer</span>
            </div>
            <a href="{{ route('admin.payments.index') }}" class="text-[11px] text-[#007C6A] hover:underline font-bold block">
                Buka antrean verifikasi &rarr;
            </a>
        </div>

        <!-- Total Calon Jamaah -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-2">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total Akun Jama'ah</span>
            <div class="flex items-baseline space-x-2">
                <span class="text-xl sm:text-2xl font-black text-slate-900">{{ $totalActiveJamaah }}</span>
                <span class="text-xs text-slate-500">Kepala Akun</span>
            </div>
            <span class="text-[11px] text-slate-400 block">Termasuk keluarga di dalamnya</span>
        </div>

        <!-- Kloter Berjalan -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-2">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Kloter Umroh Aktif</span>
            <div class="flex items-baseline space-x-2">
                <span class="text-xl sm:text-2xl font-black text-[#007C6A]">{{ $totalActiveKloters }}</span>
                <span class="text-xs text-slate-500">Kloter Berjalan</span>
            </div>
            <a href="{{ route('admin.kloters.index') }}" class="text-[11px] text-[#007C6A] hover:underline font-bold block">
                Kelola master kloter &rarr;
            </a>
        </div>

    </div>

    <!-- Antrean Bukti Transfer yang Menunggu Approval -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">Antrean Verifikasi Bukti Transfer Terbaru</h2>
                <p class="text-xs text-slate-500">Cek kecocokan nominal transfer dan mutasi bank</p>
            </div>
            <a href="{{ route('admin.payments.index') }}" class="text-xs font-bold text-[#346733] hover:underline">
                Lihat Semua Antrean &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <!-- Mobile Cards -->
            <div class="sm:hidden divide-y divide-slate-100 p-4 space-y-3">
                @forelse($pendingPayments as $p)
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-900">{{ $p->user->name }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Pending</span>
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ $p->invoice?->invoice_number }} &bull; {{ $p->invoice?->registration?->kloter?->name }}
                        </div>
                        <div class="text-sm font-extrabold text-[#346733]">
                            Rp {{ number_format($p->amount, 0, ',', '.') }}
                        </div>
                        <div class="pt-2 flex items-center justify-between border-t border-slate-200">
                            <a href="{{ $p->proof_url }}" 
                               @click.prevent="$dispatch('open-proof-modal', { url: '{{ $p->proof_url }}', title: 'Bukti Transfer - {{ $p->user->name }}' })"
                               target="_blank" 
                               class="text-xs text-teal-700 font-bold underline cursor-pointer">
                                Buka Bukti
                            </a>
                            <a href="{{ route('admin.payments.show', $p) }}" class="px-3 py-1.5 rounded-lg bg-[#346733] text-white text-xs font-bold">
                                Periksa & Verifikasi
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-center py-6 text-xs text-slate-400">Tidak ada antrean bukti pembayaran yang menunggu approval.</p>
                @endforelse
            </div>

            <!-- Desktop Table -->
            <table class="w-full text-left text-xs hidden sm:table">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Tanggal Masuk</th>
                        <th class="py-3 px-4">Nama Akun Jamaah</th>
                        <th class="py-3 px-4">Tagihan & Kloter</th>
                        <th class="py-3 px-4">Bank Tujuan</th>
                        <th class="py-3 px-4">Nominal Transfer</th>
                        <th class="py-3 px-4">Bukti</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pendingPayments as $p)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-semibold text-slate-800">{{ $p->payment_date->format('d/m/Y') }}</td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-900 block">{{ $p->user->name }}</span>
                                <span class="text-[11px] text-slate-500">{{ $p->user->email }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                <span class="font-medium text-slate-800 block">{{ $p->invoice?->invoice_number }}</span>
                                <span class="text-[11px] text-slate-400">{{ $p->invoice?->registration?->kloter?->name }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">{{ $p->bankAccount?->bank_name }}</td>
                            <td class="py-3 px-4 font-extrabold text-[#346733] text-sm">
                                Rp {{ number_format($p->amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ $p->proof_url }}" 
                                   @click.prevent="$dispatch('open-proof-modal', { url: '{{ $p->proof_url }}', title: 'Bukti Transfer - {{ $p->user->name }}' })"
                                   target="_blank" 
                                   class="inline-flex items-center px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-[#007C6A] font-semibold text-[11px] transition-colors cursor-pointer">
                                    Lihat Berkas &rarr;
                                </a>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('admin.payments.show', $p) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold shadow-sm transition-colors">
                                    Verifikasi
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 text-xs">Semua bukti transfer telah diverifikasi. Antrean bersih.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ringkasan Kloter Umroh Aktif -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Daftar Kloter Berjalan</h2>
                <p class="text-xs text-slate-500">Periode menabung dan total pendaftar yang terdata</p>
            </div>
            <a href="{{ route('admin.kloters.create') }}" class="px-3 py-1.5 rounded-xl bg-teal-50 text-[#007C6A] border border-teal-200 text-xs font-bold hover:bg-teal-100 transition-colors">
                + Buat Kloter Baru
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($activeKloters as $k)
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black text-slate-600 bg-slate-200 px-2 py-0.5 rounded">{{ $k->code }}</span>
                        <span class="text-xs font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded">Aktif</span>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">{{ $k->name }}</h3>
                    <div class="text-xs text-slate-500 space-y-1">
                        <div>Periode: {{ $k->start_date->format('d M Y') }} s/d {{ $k->end_date->format('d M Y') }}</div>
                        <div>Target / Pax: <strong>Rp {{ number_format($k->target_per_pax, 0, ',', '.') }}</strong> (Tagihan: Rp {{ number_format($k->monthly_per_pax, 0, ',', '.') }}/bln)</div>
                    </div>
                    <div class="pt-2 border-t border-slate-200 flex items-center justify-between text-xs">
                        <span class="text-slate-600">Terdaftar: <strong>{{ $k->registrations_count }} Akun Keluarga</strong></span>
                        <a href="{{ route('admin.kloters.show', $k) }}" class="text-[#007C6A] font-bold hover:underline">
                            Detail Kloter &rarr;
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</div>
@endsection
