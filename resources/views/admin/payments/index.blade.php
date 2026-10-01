<!--
File: resources/views/admin/payments/index.blade.php
Tujuan: Halaman antrean verifikasi bukti transfer pembayaran tabungan umroh oleh admin keuangan & superadmin
Dipakai Oleh: Admin\PaymentApprovalController@index (GET /admin/payments)
Dependensi Utama: layouts.app, Payment, User
Daftar Komponen Utama: Tab status (Pending, Disetujui, Ditolak), Tabel responsif dengan kartu mobile, Badge Self-Approval Alert
Side Effect: Menampilkan daftar transaksi dan tautan verifikasi
-->
@extends('layouts.app')

@section('title', 'Antrean Approval Pembayaran')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">Verifikasi & Approval Bukti Transfer</h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Pemeriksaan bukti struk transfer manual dari jamaah dan pencocokan mutasi kas masuk.
            </p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="text-xs font-bold text-[#346733] hover:underline">
            &larr; Dashboard Admin
        </a>
    </div>

    <!-- Status Tabs -->
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
        <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}"
            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center space-x-1.5 {{ $status === 'pending' ? 'bg-amber-500 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
            <span>Menunggu Approval</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $status === 'pending' ? 'bg-white text-amber-700' : 'bg-amber-100 text-amber-800' }}">
                {{ $counts['pending'] }}
            </span>
        </a>

        <a href="{{ route('admin.payments.index', ['status' => 'approved']) }}"
            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center space-x-1.5 {{ $status === 'approved' ? 'bg-[#346733] text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
            <span>Telah Disetujui</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $status === 'approved' ? 'bg-white text-emerald-800' : 'bg-emerald-100 text-emerald-800' }}">
                {{ $counts['approved'] }}
            </span>
        </a>

        <a href="{{ route('admin.payments.index', ['status' => 'rejected']) }}"
            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center space-x-1.5 {{ $status === 'rejected' ? 'bg-red-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
            <span>Ditolak</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $status === 'rejected' ? 'bg-white text-red-800' : 'bg-red-100 text-red-800' }}">
                {{ $counts['rejected'] }}
            </span>
        </a>

        <a href="{{ route('admin.payments.index', ['status' => 'all']) }}"
            class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $status === 'all' ? 'bg-slate-800 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
            Semua Transaksi
        </a>
    </div>

    <!-- Main Container -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <!-- Mobile Cards Format -->
        <div class="sm:hidden divide-y divide-slate-100 p-4 space-y-3">
            @forelse($payments as $p)
                @php
                    $isSelfPayment = ($p->user_id === auth()->id());
                @endphp
                <div class="p-4 bg-slate-50 rounded-xl border {{ $isSelfPayment ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200' }} space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-900">{{ $p->user->name }}</span>
                        @if($p->isApproved())
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Approved</span>
                        @elseif($p->isPending())
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Pending</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800">Rejected</span>
                        @endif
                    </div>

                    @if($isSelfPayment)
                        <div class="p-2 rounded-lg bg-amber-100 border border-amber-300 text-[11px] text-amber-900 font-semibold">
                            &bull; Pembayaran akun Anda sendiri (Dilarang Self-Approval)
                        </div>
                    @endif

                    <div class="text-xs text-slate-500">
                        Tagihan: {{ $p->invoice?->invoice_number }} &bull; {{ $p->invoice?->registration?->kloter?->name }}
                    </div>

                    <div class="flex items-baseline justify-between pt-1 border-t border-slate-200">
                        <span class="text-xs text-slate-500">Nominal:</span>
                        <span class="text-base font-black text-[#346733]">Rp {{ number_format($p->amount, 0, ',', '.') }}</span>
                    </div>

                    <div class="pt-2 flex items-center justify-between">
                        <a href="{{ $p->proof_url }}" 
                           @click.prevent="$dispatch('open-proof-modal', { url: '{{ $p->proof_url }}', title: 'Bukti Transfer - {{ $p->user->name }}' })"
                           target="_blank" 
                           class="text-xs text-teal-700 font-bold underline cursor-pointer">
                            Lihat File Bukti
                        </a>
                        <a href="{{ route('admin.payments.show', $p) }}" class="px-3 py-1.5 rounded-lg bg-[#346733] text-white text-xs font-bold">
                            Periksa Detail &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <p class="text-center py-8 text-xs text-slate-400">Tidak ada transaksi pada kategori ini.</p>
            @endforelse
        </div>

        <!-- Desktop Table Format -->
        <table class="w-full text-left text-xs hidden sm:table">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                <tr>
                    <th class="py-3 px-4">Tanggal Transfer</th>
                    <th class="py-3 px-4">Akun Jama'ah</th>
                    <th class="py-3 px-4">Tagihan & Kloter</th>
                    <th class="py-3 px-4">Rekening Tujuan</th>
                    <th class="py-3 px-4">Nominal Transfer</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($payments as $p)
                    @php
                        $isSelfPayment = ($p->user_id === auth()->id());
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors {{ $isSelfPayment ? 'bg-amber-50/30' : '' }}">
                        <td class="py-3 px-4 font-semibold text-slate-800">{{ $p->payment_date->format('d/m/Y') }}</td>
                        <td class="py-3 px-4">
                            <span class="font-bold text-slate-900 block">{{ $p->user->name }}</span>
                            <span class="text-[11px] text-slate-500">{{ $p->user->email }}</span>
                            @if($isSelfPayment)
                                <span class="inline-block mt-0.5 text-[9px] font-black uppercase tracking-wider text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded border border-amber-300">
                                    Transaksi Pribadi
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-slate-600">
                            <span class="font-semibold text-slate-800 block">{{ $p->invoice?->invoice_number }}</span>
                            <span class="text-[11px] text-slate-400">{{ $p->invoice?->registration?->kloter?->name }}</span>
                        </td>
                        <td class="py-3 px-4 text-slate-600">{{ $p->bankAccount?->bank_name }}</td>
                        <td class="py-3 px-4 font-extrabold text-[#346733] text-sm">
                            Rp {{ number_format($p->amount, 0, ',', '.') }}
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if($p->isApproved())
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Approved</span>
                            @elseif($p->isPending())
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Pending</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800">Rejected</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-center">
                            <a href="{{ route('admin.payments.show', $p) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition-colors">
                                Periksa
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-400 text-xs">Tidak ada data pembayaran.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Paginasi -->
        @if($payments->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $payments->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

