{{--
/**
 * File: resources/views/admin/kloters/show.blade.php
 * Tujuan: Menampilkan rincian detail master kloter umroh, panel pemicu penagihan khusus kloter ini (per bulan atau sekaligus sejak awal kloter), agregat keuangan, daftar rekening bank penampung kloter, tombol navigasi edit kloter, dan daftar pendaftar keluarga beserta anggota pax & status awal penagihan
 * Dipakai Oleh: App\Http\Controllers\Admin\KloterController@show
 * Dependensi Utama: Tailwind CSS CDN, Alpine.js, App\Models\Kloter, App\Models\BankAccount
 * Daftar Komponen Utama: Breadcrumb, Tombol aksi edit & tombol generate tagihan kloter, Panel penagihan kloter terpisah, Metrik keuangan & agregasi pax kloter, informasi paket, kartu rekening bank tujuan kloter, tabel pendaftar keluarga dengan status awal tagihan, modal generate tagihan kloter per bulan / catch-up all pending
 * Side Effect: Tampilan detail kloter, POST form ke admin.kloters.trigger-kloter-billing untuk penerbitan invoice per-kloter
 */
--}}
@extends('layouts.app')

@section('title', 'Detail Kloter - ' . $kloter->name)

@section('content')
<div class="space-y-6" x-data="{ showTriggerModal: false }">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center space-x-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('admin.kloters.index') }}" class="hover:text-haramain-green flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Daftar Kloter
                </a>
                <span>/</span>
                <span class="text-gray-700 font-medium">{{ $kloter->code }}</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                {{ $kloter->name }}
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                    {{ $kloter->status === 'active' ? 'bg-green-100 text-green-800' : ($kloter->status === 'draft' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700') }}">
                    {{ ucfirst($kloter->status) }}
                </span>
            </h1>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" @click="showTriggerModal = true" class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 shadow-sm transition cursor-pointer">
                <svg class="w-4 h-4 text-[#007C6A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>Generate Tagihan Kloter</span>
            </button>
            <a href="{{ route('admin.kloters.edit', $kloter) }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#346733] hover:bg-[#234622] text-white rounded-xl text-sm font-semibold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span>Edit Kloter</span>
            </a>
            <a href="{{ route('admin.kloters.index') }}" class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                Kembali
            </a>
        </div>
    </div>

    @php
        $startDate = \Carbon\Carbon::parse($kloter->start_date);
        $endDate = \Carbon\Carbon::parse($kloter->end_date);
        $durationMonths = (int) round($startDate->diffInMonths($endDate));

        $totalFamilyCount = $kloter->registrations->count();
        $totalPaxCount = $kloter->registrations->flatMap->paxes->count();
        
        $totalBilled = $kloter->total_billed;
        $totalPaid = $kloter->total_paid;
        $targetTotalFund = $totalPaxCount * $kloter->target_per_pax;
        $fundProgress = $targetTotalFund > 0 ? round(($totalPaid / $targetTotalFund) * 100, 1) : 0;
    @endphp

    <!-- Grid Ringkasan Keuangan & Peserta -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Keluarga & Jama'ah</span>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-gray-900">{{ $totalFamilyCount }} <span class="text-sm font-normal text-gray-500">Akun</span></span>
                <span class="text-sm font-bold text-haramain-green">({{ $totalPaxCount }} Jiwa)</span>
            </div>
            <p class="text-xs text-gray-400 mt-1">Terdaftar dalam kloter ini</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Biaya Bulanan / Jiwa</span>
            <div class="mt-2 text-2xl font-black text-haramain-green">
                Rp {{ number_format($kloter->monthly_per_pax, 0, ',', '.') }}
            </div>
            <p class="text-xs text-gray-400 mt-1">Target: Rp {{ number_format($kloter->target_per_pax, 0, ',', '.') }}/pax</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Total Terkumpul</span>
            <div class="mt-2 text-2xl font-black text-haramain-teal">
                Rp {{ number_format($totalPaid, 0, ',', '.') }}
            </div>
            <p class="text-xs text-gray-400 mt-1">Dari tagihan Rp {{ number_format($totalBilled, 0, ',', '.') }}</p>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block">Capaian Tabungan</span>
            <div class="mt-2 text-2xl font-black text-haramain-gold">
                {{ $fundProgress }}%
            </div>
            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                <div class="h-1.5 rounded-full bg-[#D4AF37]" style="width: {{ min(100, $fundProgress) }}%"></div>
            </div>
        </div>
    </div>

    <!-- Banner Penagihan Khusus Kloter Ini -->
    <div class="bg-gradient-to-r from-[#346733] to-[#007C6A] rounded-2xl p-5 text-white shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-white/10 rounded-xl shrink-0">
                <svg class="w-8 h-8 text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <h3 class="font-bold text-base">Penagihan Bulanan Khusus: {{ $kloter->name }}</h3>
                <p class="text-xs text-white/80 mt-0.5">
                    Tarif Rp {{ number_format($kloter->monthly_per_pax, 0, ',', '.') }}/pax per bulan &bull; Periode: {{ $startDate->isoFormat('MMMM Y') }} s/d {{ $endDate->isoFormat('MMMM Y') }}.
                    Penerbitan tagihan dikelola tersendiri per kloter sesuai kesiapan masing-masing kloter.
                </p>
            </div>
        </div>
        <button type="button" @click="showTriggerModal = true" class="px-4 py-2 rounded-xl bg-white/20 hover:bg-white/30 text-xs font-bold backdrop-blur transition whitespace-nowrap cursor-pointer shadow-sm">
            Generate Tagihan Kloter Ini &rarr;
        </button>
    </div>

    <!-- Informasi Detail Paket Kloter -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-bold text-gray-900 text-base mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-haramain-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Informasi Paket & Jadwal Keberangkatan
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div class="p-3 bg-gray-50 rounded-xl">
                <span class="text-gray-500 block">Kode Kloter:</span>
                <span class="font-mono font-bold text-gray-900 text-sm">{{ $kloter->code }}</span>
            </div>
            <div class="p-3 bg-gray-50 rounded-xl">
                <span class="text-gray-500 block">Tanggal Mulai:</span>
                <span class="font-semibold text-gray-900 text-sm">{{ $startDate->isoFormat('D MMMM Y') }}</span>
            </div>
            <div class="p-3 bg-gray-50 rounded-xl">
                <span class="text-gray-500 block">Tanggal Selesai / Berangkat:</span>
                <span class="font-semibold text-gray-900 text-sm">{{ $endDate->isoFormat('D MMMM Y') }}</span>
            </div>
            <div class="p-3 bg-gray-50 rounded-xl">
                <span class="text-gray-500 block">Durasi Menabung:</span>
                <span class="font-bold text-haramain-teal text-sm">{{ $durationMonths }} Bulan</span>
            </div>
        </div>

        @if($kloter->description)
            <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-600">
                <span class="font-semibold text-gray-800 block mb-1">Catatan / Deskripsi Fasilitas:</span>
                <p>{{ $kloter->description }}</p>
            </div>
        @endif
    </div>

    <!-- Rekening Bank Tujuan Transfer Kloter -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                    <svg class="w-5 h-5 text-haramain-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Rekening Bank Tujuan Pembayaran Kloter
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Rekening penampung dana tabungan umroh yang ditautkan khusus untuk kloter ini</p>
            </div>
            <a href="{{ route('admin.kloters.edit', $kloter) }}" class="text-xs font-bold text-[#346733] hover:underline flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                Kelola Rekening
            </a>
        </div>

        @if($kloter->bankAccounts->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($kloter->bankAccounts as $bank)
                    <div class="p-4 rounded-xl border border-gray-200 bg-gray-50/70 hover:bg-emerald-50/30 hover:border-emerald-200 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-900">{{ $bank->bank_name }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $bank->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $bank->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <div class="font-mono text-base font-extrabold text-[#346733] mt-2">
                            {{ $bank->account_number }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            a/n {{ $bank->account_holder }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800 flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <span class="font-bold block">Belum Ada Rekening Bank Khusus yang Ditautkan</span>
                    <p class="mt-0.5 text-amber-700">Secara otomatis, jamaah pada kloter ini akan disajikan semua rekening penampung aktif umum. Anda dapat memilih rekening khusus kloter ini melalui tombol <a href="{{ route('admin.kloters.edit', $kloter) }}" class="underline font-bold">Edit Kloter</a>.</p>
                </div>
            </div>
        @endif
    </div>

    <!-- Tabel Daftar Keluarga & Anggota Pax -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-gray-900 text-base">Daftar Pendaftar & Jama'ah Terdaftar</h3>
                <p class="text-xs text-gray-500 mt-0.5">Daftar keluarga yang tergabung dalam kloter ini beserta rincian anggotanya</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-gray-100 text-gray-700 rounded-full">
                {{ $totalFamilyCount }} Akun Pendaftar
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4">No</th>
                        <th class="px-6 py-4">Kepala Keluarga / Akun</th>
                        <th class="px-6 py-4">Daftar Jiwa (Pax)</th>
                        <th class="px-6 py-4">Tagihan Terbit</th>
                        <th class="px-6 py-4">Total Terbayar</th>
                        <th class="px-6 py-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($kloter->registrations as $index => $registration)
                        @php
                            $regPaid = $registration->total_saved;
                            $regTotal = $registration->invoices->sum('total_amount');
                            $regPaxCount = $registration->paxes->count();
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-xs text-gray-400 font-mono">{{ $index + 1 }}</td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900">{{ $registration->user->name }}</div>
                                <div class="text-xs text-gray-500">{{ $registration->user->email }} • {{ $registration->user->phone ?? '-' }}</div>
                                <div class="text-[11px] text-gray-400 mt-0.5">Daftar: {{ $registration->created_at->isoFormat('D MMM Y') }}</div>
                                @if($registration->isLateJoiner())
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            Susulan: Mulai {{ $registration->getEffectiveStartBillingDate()->locale('id')->translatedFormat('F Y') }}
                                        </span>
                                    </div>
                                @else
                                    <div class="text-[10px] text-emerald-700 font-medium mt-0.5">
                                        Tagihan dari awal kloter ({{ $kloter->start_date->locale('id')->translatedFormat('M Y') }})
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1.5 max-w-xs">
                                    @foreach($registration->paxes as $pax)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-50 text-haramain-green border border-emerald-200">
                                            {{ $pax->familyMember->name }}
                                            <span class="text-[10px] text-gray-500 ml-1">({{ ucfirst($pax->familyMember->relationship) }})</span>
                                        </span>
                                    @endforeach
                                </div>
                                <div class="text-xs font-bold text-gray-700 mt-1">Total: {{ $regPaxCount }} Orang</div>
                            </td>
                            <td class="px-6 py-4 text-xs font-semibold text-gray-900">
                                Rp {{ number_format($regTotal, 0, ',', '.') }}
                                <div class="text-[11px] font-normal text-gray-500">{{ $registration->invoices->count() }} Invoice</div>
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-haramain-green">
                                Rp {{ number_format($regPaid, 0, ',', '.') }}
                                @if($regTotal > 0 && $regPaid >= $regTotal)
                                    <span class="text-[10px] text-green-700 bg-green-100 px-1.5 py-0.5 rounded ml-1 font-semibold">Lunas</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ $registration->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($registration->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-xs text-gray-500">
                                Belum ada keluarga atau jama'ah yang mendaftar pada kloter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Trigger Penagihan Khusus Kloter Ini -->
    <div x-show="showTriggerModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div x-show="showTriggerModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showTriggerModal = false"></div>

            <div x-show="showTriggerModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                <form action="{{ route('admin.kloters.trigger-kloter-billing', $kloter) }}" method="POST">
                    @csrf
                    <div class="p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-[#346733]">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-base">Generate Tagihan Kloter</h3>
                                <p class="text-xs text-gray-500">{{ $kloter->name }} ({{ $kloter->code }})</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Periode Penagihan (Bulan / Tahun)</label>
                                <input type="month" name="billing_date" value="{{ date('Y-m') }}" class="w-full px-4 py-2.5 sm:py-3 text-sm rounded-xl border border-slate-300 shadow-sm focus:border-[#346733] focus:ring-2 focus:ring-[#346733] outline-none" required>
                                <p class="text-[11px] text-gray-400 mt-1">Sistem idempoten: Tagihan yang sudah terbit pada bulan ini untuk kloter ini tidak akan terduplikasi.</p>
                            </div>

                            <div class="pt-2 border-t border-slate-100">
                                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                                    <input type="checkbox" name="generate_all_pending" value="1" class="mt-0.5 w-4 h-4 rounded text-[#346733] border-slate-300 focus:ring-[#346733]">
                                    <div class="text-xs">
                                        <span class="font-bold text-gray-800">Terbitkan Sekaligus Seluruh Periode Tertunggak</span>
                                        <p class="text-[11px] text-gray-500 mt-0.5">
                                            Generate bertahap dari awal kloter ({{ $kloter->start_date->locale('id')->translatedFormat('F Y') }}) sampai periode yang dipilih di atas untuk seluruh bulan yang belum pernah diterbitkan tagihannya.
                                        </p>
                                    </div>
                                </label>
                            </div>

                            <div class="p-3 bg-emerald-50 rounded-xl text-xs text-emerald-900 border border-emerald-200 space-y-1">
                                <div class="font-bold">Informasi Tagihan Kloter:</div>
                                <ul class="list-disc list-inside space-y-0.5 text-[11px]">
                                    <li>Biaya bulanan: Rp {{ number_format($kloter->monthly_per_pax, 0, ',', '.') }} / orang</li>
                                    <li>Target total: Rp {{ number_format($kloter->target_per_pax, 0, ',', '.') }} / orang</li>
                                    <li>Jumlah jama'ah terdaftar: {{ $totalPaxCount }} jiwa ({{ $totalFamilyCount }} akun keluarga)</li>
                                    <li>Jama'ah susulan hanya ditagih sejak bulan bergabung, sisa bulan awal ditagihkan pada bulan akhir.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-3 flex justify-end gap-2">
                        <button type="button" @click="showTriggerModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-[#346733] hover:bg-[#234622] shadow cursor-pointer">
                            Jalankan Generate Kloter Ini
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
