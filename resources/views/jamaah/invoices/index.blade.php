<!--
File: resources/views/jamaah/invoices/index.blade.php
Tujuan: Halaman daftar seluruh tagihan bulanan (invoices) akun jamaah per periode dengan indikator status terkunci kronologis, kredit saldo, & surplus
Dipakai Oleh: Jamaah\InvoiceController@index (GET /jamaah/invoices)
Dependensi Utama: layouts.app, Invoice
Daftar Komponen Utama: Daftar tagihan responsif (Mobile Card / Desktop Table), Status pelunasan & indikator terkunci, Alokasi pembayaran & kredit, Paginasi
Side Effect: Menampilkan list tagihan dan navigasi ke halaman detail tagihan
-->
@extends('layouts.app')

@section('title', 'Daftar Tagihan Bulanan')

@section('content')
<div class="space-y-6">

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

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <!-- Mobile Cards Format -->
        <div class="sm:hidden divide-y divide-slate-100 p-4 space-y-3">
            @forelse($invoices as $inv)
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
                                Detail & Transfer &rarr;
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-center py-8 text-xs text-slate-400">Belum ada tagihan yang diterbitkan.</p>
            @endforelse
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
                @forelse($invoices as $inv)
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
                                    Rincian & Bayar
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-400 text-xs">Belum ada tagihan bulanan yang diterbitkan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Paginasi -->
        @if($invoices->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

