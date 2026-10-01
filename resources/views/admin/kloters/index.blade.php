{{--
/**
 * File: resources/views/admin/kloters/index.blade.php
 * Tujuan: Halaman kelola master kloter tabungan umroh (daftar, tombol edit, buat baru) dan pemicu manual penagihan bulanan tanggal 1
 * Dipakai Oleh: App\Http\Controllers\Admin\KloterController@index
 * Dependensi Utama: Tailwind CSS CDN, Alpine.js, App\Models\Kloter
 * Daftar Komponen Utama: Kartu ringkasan kloter, tombol edit & detail kloter, tombol buat kloter, panel pemicu billing tanggal 1, tabel daftar kloter & status
 * Side Effect: POST form ke admin.kloters.trigger-billing untuk penerbitan invoice serentak
 */
--}}
@extends('layouts.app')

@section('title', 'Manajemen Kloter Umroh')

@section('content')
<div class="space-y-6" x-data="{ showTriggerModal: false }">
    <!-- Header Page & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Manajemen Kloter Umroh</h1>
            <p class="text-sm text-gray-500 mt-1">Atur paket keberangkatan, periode tabungan, dan penerbitan tagihan bulanan.</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" @click="showTriggerModal = true" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 shadow-sm transition">
                <svg class="w-4 h-4 text-haramain-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Generate Tagihan (Tgl 1)
            </button>
            <a href="{{ route('admin.kloters.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-md transition bg-[#346733] hover:bg-[#234622]">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Kloter Baru
            </a>
        </div>
    </div>

    <!-- Banner Info Sistem Penagihan Otomatis -->
    <div class="bg-gradient-to-r from-haramain-green to-haramain-teal rounded-2xl p-5 text-white shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-white/10 rounded-xl">
                <svg class="w-8 h-8 text-haramain-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <h3 class="font-bold text-base">Otomasi Penagihan Tanggal 1</h3>
                <p class="text-xs text-white/80 mt-0.5">Sistem scheduler artisan `billing:generate-monthly` berjalan otomatis setiap tanggal 1 jam 00:01 WIB untuk seluruh kloter aktif.</p>
            </div>
        </div>
        <button type="button" @click="showTriggerModal = true" class="px-3.5 py-1.5 rounded-lg bg-white/20 hover:bg-white/30 text-xs font-semibold backdrop-blur transition whitespace-nowrap">
            Jalankan Manual Sekarang
        </button>
    </div>

    <!-- Daftar Kloter -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($kloters as $kloter)
            @php
                $startDate = \Carbon\Carbon::parse($kloter->start_date);
                $endDate = \Carbon\Carbon::parse($kloter->end_date);
                $durationMonths = $startDate->diffInMonths($endDate);
                
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

    <!-- Modal Trigger Penagihan Manual -->
    <div x-show="showTriggerModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div x-show="showTriggerModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showTriggerModal = false"></div>

            <div x-show="showTriggerModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                <form action="{{ route('admin.kloters.trigger-billing') }}" method="POST">
                    @csrf
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-haramain-green">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-base">Generate Tagihan Tanggal 1</h3>
                                <p class="text-xs text-gray-500">Penerbitan invoice serentak untuk semua peserta kloter aktif</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Periode Penagihan (Bulan / Tahun)</label>
                                <input type="month" name="billing_date" value="{{ date('Y-m') }}" class="w-full px-4 py-2.5 sm:py-3 text-sm rounded-xl border border-slate-300 shadow-sm focus:border-haramain-green focus:ring-2 focus:ring-haramain-green outline-none" required>
                                <p class="text-[11px] text-gray-400 mt-1">Sistem idempoten: Tagihan yang sudah terbit di bulan ini tidak akan terduplikasi.</p>
                            </div>

                            <div class="p-3 bg-amber-50 rounded-xl text-xs text-amber-800 border border-amber-200">
                                <strong>Catatan:</strong> Tagihan hanya diterbitkan bagi pendaftaran kloter dengan status <em>active</em> dan berada di dalam rentang tanggal periode kloter.
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-3 flex justify-end gap-2">
                        <button type="button" @click="showTriggerModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-[#346733] hover:bg-[#234622] shadow cursor-pointer">
                            Jalankan Generate Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
