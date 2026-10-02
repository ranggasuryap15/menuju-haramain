{{--
/**
 * File: resources/views/admin/kloters/index.blade.php
 * Tujuan: Halaman kelola master kloter tabungan umroh (daftar kloter, tombol edit, pembuatan kloter baru, dan status)
 * Dipakai Oleh: App\Http\Controllers\Admin\KloterController@index
 * Dependensi Utama: Tailwind CSS CDN, Alpine.js, App\Models\Kloter
 * Daftar Komponen Utama: Kartu ringkasan kloter, tombol edit & detail kloter, tombol buat kloter, grid daftar kloter & metrik
 * Side Effect: Navigasi ke detail kloter, edit kloter, dan form create kloter
 */
--}}
@extends('layouts.app')

@section('title', 'Manajemen Kloter Umroh')

@section('content')
<div class="space-y-6">
    <!-- Header Page & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Manajemen Kloter Umroh</h1>
            <p class="text-sm text-gray-500 mt-1">Atur paket keberangkatan, periode tabungan, dan detail masing-masing kloter.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.kloters.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-md transition bg-[#346733] hover:bg-[#234622]">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Kloter Baru
            </a>
        </div>
    </div>

    <!-- Daftar Kloter -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($kloters as $kloter)
            @php
                $startDate = \Carbon\Carbon::parse($kloter->start_date);
                $endDate = \Carbon\Carbon::parse($kloter->end_date);
                $durationMonths = (int) round($startDate->diffInMonths($endDate));
                
                // Hitung total invoice dan nominal dana terkumpul riil
                $totalInvoicesCount = $kloter->registrations->flatMap->invoices->count();
                $totalPaidAmount = $kloter->total_paid;
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col justify-between hover:shadow-md transition">
                <div>
                    <!-- Header Card -->
                    <div class="p-5 border-b border-gray-100 flex items-start justify-between">
                        <div>
                            <span class="text-xs font-mono font-bold text-haramain-teal uppercase tracking-wider block mb-1">{{ $kloter->code }}</span>
                            <h3 class="font-bold text-gray-900 text-lg leading-tight">{{ $kloter->name }}</h3>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                            {{ $kloter->status === 'active' ? 'bg-green-100 text-green-800' : ($kloter->status === 'draft' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                            {{ ucfirst($kloter->status) }}
                        </span>
                    </div>

                    <!-- Body Card -->
                    <div class="p-5 space-y-4 text-xs">
                        <div class="grid grid-cols-2 gap-3 p-3 bg-gray-50 rounded-xl">
                            <div>
                                <span class="text-gray-500 block">Target / Pax:</span>
                                <span class="font-bold text-gray-900 text-sm">Rp {{ number_format($kloter->target_per_pax, 0, ',', '.') }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Cicilan / Bln:</span>
                                <span class="font-bold text-haramain-green text-sm">Rp {{ number_format($kloter->monthly_per_pax, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="space-y-1.5 text-gray-600">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Rentang Periode:</span>
                                <span class="font-medium text-gray-800">{{ $startDate->format('M Y') }} - {{ $endDate->format('M Y') }} ({{ $durationMonths }} Bln)</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Keluarga Terdaftar:</span>
                                <span class="font-semibold text-gray-900">{{ $kloter->registrations_count }} Akun</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Dana Terkumpul:</span>
                                <span class="font-semibold text-haramain-green">Rp {{ number_format($totalPaidAmount, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        @if($kloter->description)
                            <p class="text-gray-500 italic line-clamp-2 pt-2 border-t border-gray-100">
                                "{{ $kloter->description }}"
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Footer Card -->
                <div class="p-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                    <a href="{{ route('admin.kloters.edit', $kloter) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 transition">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit</span>
                    </a>
                    <a href="{{ route('admin.kloters.show', $kloter) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-haramain-green hover:text-haramain-teal transition">
                        <span>Lihat Detail & Peserta</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-gray-200">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <h3 class="font-semibold text-gray-700 text-base">Belum Ada Kloter Umroh</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">Mulai dengan menambahkan master paket kloter keberangkatan baru.</p>
                <a href="{{ route('admin.kloters.create') }}" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-haramain-green shadow transition">
                    + Buat Kloter Pertama
                </a>
            </div>
        @endforelse
    </div>
</div>
@endsection
