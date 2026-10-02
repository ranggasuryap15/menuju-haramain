{{--
/**
 * File: resources/views/admin/invoices/index.blade.php
 * Tujuan: Monitoring daftar seluruh tagihan bulanan jama'ah bagi Superadmin & Admin Keuangan, difokuskan pada pemantauan piutang belum lunas, tunggakan jatuh tempo, filter kloter & periode, pencarian, dan tautan pengingat pembayaran
 * Dipakai Oleh: App\Http\Controllers\Admin\InvoiceController@index (GET /admin/invoices)
 * Dependensi Utama: Tailwind CSS CDN, Alpine.js, App\Models\Invoice, App\Models\Kloter
 * Daftar Komponen Utama: Breadcrumb & Title, Kartu statistik ringkasan tagihan belum lunas, Panel filter status & kloter & periode & pencarian, Format tampilan mobile cards & desktop table, Indikator jatuh tempo, Paginasi
 * Side Effect: Query filter GET request ke admin.invoices.index
 */
--}}
@extends('layouts.app')

@section('title', 'Tagihan Jama\'ah - Monitoring Piutang')

@section('content')
<div class="space-y-6">

    <!-- Header & Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-[#346733]">Dashboard</a>
                <span>/</span>
                <span class="text-slate-700 font-semibold">Tagihan Jama'ah</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">Monitoring Tagihan Jama'ah</h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Pantau seluruh tagihan bulanan jama'ah, piutang tertunggak, dan status pelunasan tabungan kloter umroh.
            </p>
        </div>
    </div>

    <!-- 1. Kartu Ringkasan Finansial Tagihan Belum Lunas -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Tagihan Belum Lunas -->
        <div class="p-5 bg-white rounded-2xl border border-red-200 shadow-xs relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-red-600 uppercase tracking-wider">Total Piutang Belum Lunas</span>
                <div class="w-8 h-8 rounded-full bg-red-50 text-red-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-black text-red-600">
                Rp {{ number_format($stats['total_unpaid_amount'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Sisa akumulasi seluruh tagihan belum lunas</p>
        </div>

        <!-- Jumlah Tagihan Belum Lunas -->
        <div class="p-5 bg-white rounded-2xl border border-amber-200 shadow-xs relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-amber-700 uppercase tracking-wider">Tagihan Belum Lunas</span>
                <div class="w-8 h-8 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-black text-amber-800">
                {{ $stats['unpaid_count'] }} <span class="text-xs font-normal text-slate-500">Invoice</span>
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Status belum bayar atau bayar sebagian</p>
        </div>

        <!-- Tagihan Jatuh Tempo (Overdue) -->
        <div class="p-5 bg-white rounded-2xl border border-rose-200 shadow-xs relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-rose-700 uppercase tracking-wider">Jatuh Tempo (Overdue)</span>
                <div class="w-8 h-8 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-black text-rose-700">
                {{ $stats['overdue_count'] }} <span class="text-xs font-normal text-slate-500">Invoice</span>
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Melewati tanggal 10 bulan berjalan</p>
        </div>

        <!-- Jumlah Jama'ah Belum Lunas -->
        <div class="p-5 bg-white rounded-2xl border border-blue-200 shadow-xs relative overflow-hidden">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-blue-700 uppercase tracking-wider">Jama'ah Belum Lunas</span>
                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <div class="text-2xl font-black text-blue-900">
                {{ $stats['unpaid_jamaah_count'] }} <span class="text-xs font-normal text-slate-500">Keluarga</span>
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Akun yang memiliki kewajiban tabungan</p>
        </div>
    </div>

    <!-- 2. Panel Filter & Pencarian -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5">
        <form action="{{ route('admin.invoices.index') }}" method="GET" class="space-y-4">
            <!-- Tabs Filter Status Cepat -->
            <div class="flex flex-wrap gap-2 pb-3 border-b border-slate-100">
                <a href="{{ route('admin.invoices.index', array_merge(request()->except(['status', 'page']), ['status' => 'all_unpaid'])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $status === 'all_unpaid' ? 'bg-[#346733] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Semua Belum Lunas
                </a>
                <a href="{{ route('admin.invoices.index', array_merge(request()->except(['status', 'page']), ['status' => 'unpaid'])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $status === 'unpaid' ? 'bg-amber-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Belum Bayar
                </a>
                <a href="{{ route('admin.invoices.index', array_merge(request()->except(['status', 'page']), ['status' => 'partially_paid'])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $status === 'partially_paid' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Bayar Sebagian
                </a>
                <a href="{{ route('admin.invoices.index', array_merge(request()->except(['status', 'page']), ['status' => 'overdue'])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $status === 'overdue' ? 'bg-red-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Jatuh Tempo (Overdue)
                </a>
                <a href="{{ route('admin.invoices.index', array_merge(request()->except(['status', 'page']), ['status' => 'paid'])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $status === 'paid' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Sudah Lunas
                </a>
                <a href="{{ route('admin.invoices.index', array_merge(request()->except(['status', 'page']), ['status' => 'all'])) }}"
                   class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $status === 'all' ? 'bg-slate-800 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                    Semua Tagihan
                </a>
            </div>

            <!-- Input Filter Form Grid -->
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Filter Kloter -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Pilih Kloter</label>
                    <select name="kloter_id" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:border-[#346733] focus:ring-1 focus:ring-[#346733] outline-none">
                        <option value="">Semua Kloter</option>
                        @foreach($kloters as $k)
                            <option value="{{ $k->id }}" {{ $kloterId == $k->id ? 'selected' : '' }}>
                                {{ $k->name }} ({{ $k->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Periode Bulan -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Periode Bulan</label>
                    <input type="month" name="period" value="{{ $period }}" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:border-[#346733] focus:ring-1 focus:ring-[#346733] outline-none">
                </div>

                <!-- Input Pencarian -->
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Cari Jama'ah / Invoice</label>
                    <div class="flex gap-2">
                        <input type="text" name="search" value="{{ $search }}" placeholder="Nama jamaah, email, telp, atau nomor tagihan..." class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:border-[#346733] focus:ring-1 focus:ring-[#346733] outline-none">
                        <button type="submit" class="px-4 py-2 bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold rounded-xl transition cursor-pointer shrink-0">
                            Cari
                        </button>
                        @if($kloterId || $period || $search || $status !== 'all_unpaid')
                            <a href="{{ route('admin.invoices.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition shrink-0 flex items-center">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- 3. Tabel Data Tagihan Jama'ah -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900">
                Daftar Tagihan ({{ $invoices->total() }} Data Ditemukan)
            </h2>
            <span class="text-xs text-slate-500">
                Halaman {{ $invoices->currentPage() }} dari {{ max(1, $invoices->lastPage()) }}
            </span>
        </div>

        <!-- Mobile Cards Format -->
        <div class="sm:hidden divide-y divide-slate-100 p-4 space-y-3">
            @forelse($invoices as $inv)
                @php
                    $isOverdue = ($inv->status !== 'paid' && $inv->due_date && $inv->due_date->isPast());
                    $remaining = $inv->remaining_amount;
                    $phone = $inv->registration?->user?->phone;
                    $waPhone = $phone ? preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $phone)) : null;
                @endphp
                <div class="p-4 bg-slate-50 rounded-xl border {{ $isOverdue ? 'border-red-200 bg-red-50/20' : 'border-slate-200' }} space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-[#346733]">{{ $inv->invoice_number }}</span>
                        @if($inv->isPaid())
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Lunas</span>
                        @elseif($isOverdue)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800">Jatuh Tempo</span>
                        @elseif($inv->status === 'partially_paid')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">Sebagian</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Belum Bayar</span>
                        @endif
                    </div>

                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ $inv->registration?->user?->name }}</h3>
                        <p class="text-xs text-slate-500">{{ $inv->registration?->kloter?->name }} &bull; Periode {{ $inv->period_label }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-200">
                        <div>
                            <span class="text-[11px] text-slate-400 block">Total Tagihan:</span>
                            <span class="font-bold text-slate-800">Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-[11px] text-slate-400 block">Sisa Pembayaran:</span>
                            <span class="font-black {{ $remaining > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                Rp {{ number_format($remaining, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <div class="text-[11px] text-slate-500 flex items-center justify-between pt-1">
                        <span>Jatuh Tempo: <strong>{{ $inv->due_date ? $inv->due_date->format('d/m/Y') : '-' }}</strong></span>
                        @if($inv->payments->where('status', 'pending')->isNotEmpty())
                            <span class="text-amber-700 font-bold bg-amber-100 px-1.5 py-0.5 rounded text-[10px]">Menunggu Approval</span>
                        @endif
                    </div>

                    <div class="pt-2 flex items-center justify-between border-t border-slate-200">
                        @if($waPhone && $remaining > 0)
                            <a href="https://wa.me/{{ $waPhone }}?text={{ urlencode('Assalamu\'alaikum Bapak/Ibu ' . ($inv->registration->user->name ?? 'Jamaah') . ', kami dari pengurus Tabungan Umroh Menuju Haramain menginfokan tagihan periode ' . $inv->period_label . ' sebesar Rp ' . number_format($remaining, 0, ',', '.') . ' pada ' . ($inv->registration->kloter->name ?? 'Kloter Umroh') . '. Mohon kesediaannya untuk melakukan pembayaran sebelum tanggal jatuh tempo. Terima kasih.') }}"
                               target="_blank"
                               class="inline-flex items-center gap-1 text-xs text-emerald-700 font-bold hover:underline">
                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.299.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.42-.101.825z"/></svg>
                                <span>Ingatkan WA</span>
                            </a>
                        @else
                            <span></span>
                        @endif
                        <a href="{{ route('admin.invoices.show', $inv) }}" class="px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition shadow-xs">
                            Detail Tagihan &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-xs text-slate-400">
                    Tidak ada tagihan yang sesuai dengan filter yang dipilih.
                </div>
            @endforelse
        </div>

        <!-- Desktop Table Format -->
        <div class="overflow-x-auto hidden sm:block">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-4">No</th>
                        <th class="py-3.5 px-4">No. Tagihan & Periode</th>
                        <th class="py-3.5 px-4">Jama'ah / Kepala Keluarga</th>
                        <th class="py-3.5 px-4">Kloter & Jiwa</th>
                        <th class="py-3.5 px-4">Total Tagihan</th>
                        <th class="py-3.5 px-4">Sudah Dibayar</th>
                        <th class="py-3.5 px-4">Sisa Tagihan</th>
                        <th class="py-3.5 px-4">Jatuh Tempo</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($invoices as $index => $inv)
                        @php
                            $isOverdue = ($inv->status !== 'paid' && $inv->due_date && $inv->due_date->isPast());
                            $remaining = $inv->remaining_amount;
                            $phone = $inv->registration?->user?->phone;
                            $waPhone = $phone ? preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $phone)) : null;
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition {{ $isOverdue ? 'bg-red-50/20' : '' }}">
                            <td class="py-3.5 px-4 text-slate-400 font-mono">
                                {{ $invoices->firstItem() + $index }}
                            </td>
                            <td class="py-3.5 px-4">
                                <a href="{{ route('admin.invoices.show', $inv) }}" class="font-bold text-[#346733] hover:underline block">
                                    {{ $inv->invoice_number }}
                                </a>
                                <span class="text-[11px] text-slate-500 font-medium">Periode {{ $inv->period_label }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900">{{ $inv->registration?->user?->name ?? 'Peserta' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $inv->registration?->user?->email }}</div>
                                @if($phone)
                                    <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                                        <span>{{ $phone }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-800">{{ $inv->registration?->kloter?->name }}</div>
                                <div class="text-[11px] text-slate-500">
                                    {{ $inv->items->count() ?: $inv->registration?->total_pax }} Pax (Jiwa)
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-slate-800">
                                Rp {{ number_format($inv->total_amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-emerald-700 font-medium">
                                Rp {{ number_format($inv->paid_amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-black text-sm {{ $remaining > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                    Rp {{ number_format($remaining, 0, ',', '.') }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="{{ $isOverdue ? 'text-red-700 font-bold' : 'text-slate-700 font-medium' }}">
                                    {{ $inv->due_date ? $inv->due_date->format('d/m/Y') : '-' }}
                                </div>
                                @if($isOverdue)
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-red-100 text-red-800 mt-0.5">
                                        Lewat Tempo
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($inv->isPaid())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        Lunas
                                    </span>
                                @elseif($isOverdue)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800">
                                        Jatuh Tempo
                                    </span>
                                @elseif($inv->status === 'partially_paid')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                                        Sebagian
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                        Belum Bayar
                                    </span>
                                @endif

                                @if($inv->payments->where('status', 'pending')->isNotEmpty())
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                            Ada Bukti Baru
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="{{ route('admin.invoices.show', $inv) }}" class="inline-flex items-center px-2.5 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition shadow-xs">
                                        Detail
                                    </a>
                                    @if($waPhone && $remaining > 0)
                                        <a href="https://wa.me/{{ $waPhone }}?text={{ urlencode('Assalamu\'alaikum Bapak/Ibu ' . ($inv->registration->user->name ?? 'Jamaah') . ', kami dari pengurus Tabungan Umroh Menuju Haramain menginfokan tagihan periode ' . $inv->period_label . ' sebesar Rp ' . number_format($remaining, 0, ',', '.') . ' pada ' . ($inv->registration->kloter->name ?? 'Kloter Umroh') . '. Mohon kesediaannya untuk melakukan pembayaran sebelum tanggal jatuh tempo. Terima kasih.') }}"
                                           target="_blank"
                                           title="Kirim pengingat WhatsApp ke {{ $phone }}"
                                           class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 transition">
                                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.299.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.42-.101.825z"/></svg>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center text-slate-400">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <p class="font-bold text-slate-700">Tidak ada data tagihan</p>
                                    <p class="text-xs text-slate-500">Tidak ditemukan tagihan yang sesuai dengan kriteria filter saat ini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginasi -->
        @if($invoices->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
