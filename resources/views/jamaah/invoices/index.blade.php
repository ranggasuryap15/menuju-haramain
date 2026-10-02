{{--
/**
 * File: resources/views/jamaah/invoices/index.blade.php
 * Tujuan: Halaman daftar tagihan bulanan jama'ah dengan tab terpisah antara Belum Dibayar (urutan ASC / bulan pertama belum bayar teratas) dan Sudah Dibayar (urutan DESC / bulan terbaru teratas), indikator status terkunci kronologis, kredit saldo, dan surplus
 * Dipakai Oleh: App\Http\Controllers\Jamaah\InvoiceController@index (GET /jamaah/invoices)
 * Dependensi Utama: layouts.app, App\Models\Invoice
 * Daftar Komponen Utama: Tab switcher Belum Dibayar vs Sudah Dibayar dengan badge count, Daftar tagihan responsif (Mobile Cards / Desktop Table), Indikator status & terkunci kronologis, Empty state informatif, Paginasi
 * Side Effect: Navigasi tab via query parameter ?tab=unpaid / ?tab=paid dan navigasi ke detail tagihan
 */
--}}
@extends('layouts.app')

@section('title', 'Daftar Tagihan Bulanan')

@section('content')
<div class="space-y-6">

    <!-- Header & Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">Tagihan Bulanan Umroh</h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Tagihan terbit otomatis setiap tanggal 1 selama periode kloter menabung aktif.
            </p>
        </div>
        <a href="{{ route('jamaah.dashboard') }}" class="text-xs font-bold text-[#346733] hover:underline flex items-center space-x-1">
            <span>&larr; Dasbor Utama</span>
        </a>
    </div>

    <!-- Tabs Navigasi: Belum Dibayar vs Sudah Dibayar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-2xl max-w-md w-full border border-slate-200">
            <!-- Tab Belum Dibayar (ASC) -->
            <a href="{{ route('jamaah.invoices.index', ['tab' => 'unpaid']) }}"
               class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all {{ $tab === 'unpaid' ? 'bg-[#346733] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Belum Dibayar</span>
                @if($unpaidCount > 0)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'unpaid' ? 'bg-amber-400 text-amber-950' : 'bg-amber-100 text-amber-800' }}">
                        {{ $unpaidCount }}
                    </span>
                @endif
            </a>

            <!-- Tab Sudah Dibayar (DESC) -->
            <a href="{{ route('jamaah.invoices.index', ['tab' => 'paid']) }}"
               class="flex-1 flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl text-xs sm:text-sm font-bold transition-all {{ $tab === 'paid' ? 'bg-[#346733] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Sudah Dibayar</span>
                @if($paidCount > 0)
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'paid' ? 'bg-emerald-400 text-emerald-950' : 'bg-emerald-100 text-emerald-800' }}">
                        {{ $paidCount }}
                    </span>
                @endif
            </a>
        </div>

        <div class="text-xs text-slate-500">
            @if($tab === 'unpaid')
                <span class="inline-flex items-center gap-1 text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                    <span>Urutan: Bulan pertama yang belum dibayar &rarr; selanjutnya (Ascending)</span>
                </span>
            @else
                <span class="inline-flex items-center gap-1 text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/></svg>
                    <span>Urutan: Periode pembayaran terbaru &rarr; terlama (Descending)</span>
                </span>
            @endif
        </div>
    </div>

    <!-- Konten Daftar Tagihan -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        @if($invoices->isEmpty())
            <!-- Empty State -->
            <div class="text-center py-12 px-4 space-y-3">
                @if($tab === 'unpaid')
                    <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-slate-800">Alhamdulillah! Tidak Ada Tagihan Belum Lunas</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto">Seluruh kewajiban tabungan Anda saat ini sudah diselesaikan.</p>
                    </div>
                    @if($paidCount > 0)
                        <div class="pt-2">
                            <a href="{{ route('jamaah.invoices.index', ['tab' => 'paid']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                                <span>Lihat Riwayat Tagihan Lunas ({{ $paidCount }})</span> &rarr;
                            </a>
                        </div>
                    @endif
                @else
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-slate-800">Belum Ada Tagihan yang Lunas</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto">Riwayat tagihan yang telah disetujui pembayarannya akan tercatat di sini.</p>
                    </div>
                    @if($unpaidCount > 0)
                        <div class="pt-2">
                            <a href="{{ route('jamaah.invoices.index', ['tab' => 'unpaid']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold shadow-sm transition">
                                <span>Lihat Tagihan yang Belum Dibayar ({{ $unpaidCount }})</span> &rarr;
                            </a>
                        </div>
                    @endif
                @endif
            </div>
        @else
            <!-- Mobile Cards Format -->
            <div class="sm:hidden divide-y divide-slate-100 p-4 space-y-3">
                @foreach($invoices as $inv)
                    <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#346733]">{{ $inv->invoice_number }}</span>
                            @if($inv->isPaid())
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Lunas</span>
                            @elseif($unpaidPrev = $inv->getUnpaidPreviousInvoice())
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 flex items-center space-x-1" title="Menunggu pelunasan periode {{ $unpaidPrev->period_label }}">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <span>Terkunci</span>
                                </span>
                            @elseif($inv->status === 'partially_paid')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">Sebagian</span>
                            @elseif($inv->status === 'overdue')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800">Jatuh Tempo</span>
                            @else
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Belum Bayar</span>
                            @endif
                        </div>

                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Periode {{ $inv->period_label }}</h3>
                            <p class="text-xs text-slate-500">{{ $inv->registration->kloter->name }} ({{ $inv->items->count() }} Orang)</p>
                        </div>

                        <div class="flex items-baseline justify-between pt-1 border-t border-slate-200 text-xs">
                            <div>
                                <span class="text-slate-500 block">Total Tagihan:</span>
                                <span class="font-bold text-slate-800">Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</span>
                                @if($inv->carry_over_credit > 0)
                                    <span class="text-[10px] text-teal-700 font-semibold block">Kredit lalu: Rp {{ number_format($inv->carry_over_credit, 0, ',', '.') }}</span>
                                @endif
                                @if($inv->surplus_amount > 0)
                                    <span class="text-[10px] text-emerald-700 font-semibold block">Surplus: +Rp {{ number_format($inv->surplus_amount, 0, ',', '.') }}</span>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="text-slate-500 block">Sisa Pembayaran:</span>
                                <span class="font-extrabold {{ $inv->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                    Rp {{ number_format($inv->remaining_amount, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        <div class="pt-2 flex items-center justify-between">
                            <span class="text-[11px] text-slate-400">Jatuh tempo: {{ $inv->due_date->format('d/m/Y') }}</span>
                            @if($inv->isLockedByPreviousUnpaid())
                                <a href="{{ route('jamaah.invoices.show', $inv) }}" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-sm transition-colors flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <span>Terkunci &rarr;</span>
                                </a>
                            @else
                                <a href="{{ route('jamaah.invoices.show', $inv) }}" class="px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold shadow-sm transition-colors">
                                    {{ $inv->isPaid() ? 'Lihat Rincian &rarr;' : 'Detail & Transfer &rarr;' }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Desktop Table Format -->
            <table class="w-full text-left text-xs hidden sm:table">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Tagihan</th>
                        <th class="py-3 px-4">Periode</th>
                        <th class="py-3 px-4">Kloter & Pax</th>
                        <th class="py-3 px-4">Total</th>
                        <th class="py-3 px-4">Sudah Masuk</th>
                        <th class="py-3 px-4">Sisa Tagihan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($invoices as $inv)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="py-3 px-4 font-bold text-[#346733]">{{ $inv->invoice_number }}</td>
                            <td class="py-3 px-4 font-semibold text-slate-900">{{ $inv->period_label }}</td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $inv->registration->kloter->name }}
                                <span class="text-[11px] text-slate-400 block">({{ $inv->items->count() }} Peserta)</span>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-800">Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-emerald-700">Rp {{ number_format($inv->paid_amount, 0, ',', '.') }}</div>
                                @if($inv->carry_over_credit > 0)
                                    <span class="text-[10px] text-teal-700 font-semibold block">+ Kredit Rp {{ number_format($inv->carry_over_credit, 0, ',', '.') }}</span>
                                @endif
                                @if($inv->surplus_amount > 0)
                                    <span class="text-[10px] text-emerald-700 font-semibold block">Surplus +Rp {{ number_format($inv->surplus_amount, 0, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-extrabold {{ $inv->remaining_amount > 0 ? 'text-red-600' : 'text-slate-400' }}">
                                Rp {{ number_format($inv->remaining_amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($inv->isPaid())
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Lunas</span>
                                @elseif($unpaidPrev = $inv->getUnpaidPreviousInvoice())
                                    <span class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800" title="Menunggu pelunasan periode {{ $unpaidPrev->period_label }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        <span>Terkunci</span>
                                    </span>
                                @elseif($inv->status === 'partially_paid')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">Sebagian</span>
                                @elseif($inv->status === 'overdue')
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800">Jatuh Tempo</span>
                                @else
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Belum Bayar</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($inv->isLockedByPreviousUnpaid())
                                    <a href="{{ route('jamaah.invoices.show', $inv) }}" class="inline-flex items-center space-x-1 px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        <span>Terkunci</span>
                                    </a>
                                @else
                                    <a href="{{ route('jamaah.invoices.show', $inv) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition-colors">
                                        {{ $inv->isPaid() ? 'Rincian' : 'Rincian & Bayar' }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <!-- Paginasi -->
        @if($invoices->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
