{{--
/**
 * File: resources/views/admin/kloters/show.blade.php
 * Tujuan: Menampilkan rincian detail master kloter umroh, link WhatsApp grup jama'ah, panel dan modal pemicu generate tagihan manual (baik serentak semua anggota kloter maupun khusus per orang), agregat keuangan, manajemen & edit detail rekening bank penampung kloter, tombol navigasi edit kloter, daftar pendaftar keluarga beserta aksi generate tagihan per orang, serta tabel riwayat transaksi pembayaran jamaah lengkap dengan paginasi descending
 * Dipakai Oleh: App\Http\Controllers\Admin\KloterController@show
 * Dependensi Utama: Tailwind CSS CDN, Alpine.js, App\Models\Kloter, App\Models\BankAccount, App\Models\Payment, App\Models\KloterRegistration
 * Daftar Komponen Utama: Breadcrumb, Tombol aksi simetris header, Panel penagihan, Metrik keuangan & agregasi pax kloter, informasi paket, tautan grup WA, kartu rekening bank kloter, tabel pendaftar keluarga dengan tombol aksi generate tagihan per orang, tabel riwayat transaksi pembayaran jamaah berpaginasi descending, modal generate tagihan manual (semua jamaah vs per orang), modal edit rekening bank, modal tambah rekening baru
 * Side Effect: Tampilan detail kloter, POST form ke admin.kloters.trigger-kloter-billing, PUT form ke admin.bank-accounts.update, POST form ke admin.bank-accounts.store
 */
--}}
@extends('layouts.app')

@section('title', 'Detail Kloter - ' . $kloter->name)

@section('content')
<div class="space-y-6" x-data="{ 
    showTriggerModal: false,
    showEditBankModal: false,
    showAddBankModal: false,
    targetType: 'all',
    selectedRegistrationId: 'all',
    selectedRegistrationName: '',
    editBank: { id: null, bank_name: '', account_number: '', account_holder: '', is_active: true },
    openTriggerFor(regId, regName) {
        this.targetType = 'individual';
        this.selectedRegistrationId = regId;
        this.selectedRegistrationName = regName;
        this.showTriggerModal = true;
    },
    openTriggerForAll() {
        this.targetType = 'all';
        this.selectedRegistrationId = 'all';
        this.selectedRegistrationName = '';
        this.showTriggerModal = true;
    }
}">
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

        <div class="flex items-center gap-2 sm:gap-3">
            <button type="button" @click="openTriggerForAll()" class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 sm:px-4 sm:py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-800 rounded-xl text-xs sm:text-sm font-bold shadow-xs transition cursor-pointer">
                <svg class="w-4 h-4 text-[#007C6A] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span class="whitespace-nowrap">Generate Tagihan</span>
            </button>
            <a href="{{ route('admin.kloters.edit', $kloter) }}" class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 sm:px-4 sm:py-2 bg-[#346733] hover:bg-[#234622] text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <span class="whitespace-nowrap">Edit Kloter</span>
            </a>
            <a href="{{ route('admin.kloters.index') }}" class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
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
        <button type="button" @click="openTriggerForAll()" class="px-4 py-2 rounded-xl bg-white/20 hover:bg-white/30 text-xs font-bold backdrop-blur transition whitespace-nowrap cursor-pointer shadow-sm">
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

        @if($kloter->whatsapp_group_url)
            <div class="mt-4 p-3.5 bg-emerald-50/60 border border-emerald-200 rounded-xl flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-gray-900 block">Link Grup WhatsApp Jama'ah</span>
                        <span class="text-xs text-gray-600 block truncate max-w-md font-mono">{{ $kloter->whatsapp_group_url }}</span>
                    </div>
                </div>
                <a href="{{ $kloter->whatsapp_group_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs transition">
                    <span>Buka Link Grup</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
        @endif
    </div>

    <!-- Rekening Bank Tujuan Transfer Kloter -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#346733]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Rekening Bank Tujuan Pembayaran Kloter
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Rekening penampung dana tabungan umroh yang ditautkan khusus untuk kloter ini</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="showAddBankModal = true" class="text-xs font-bold text-white bg-[#346733] hover:bg-[#234622] px-3 py-1.5 rounded-lg shadow-xs transition flex items-center gap-1 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>+ Rekening Baru</span>
                </button>
                <a href="{{ route('admin.kloters.edit', $kloter) }}" class="text-xs font-bold text-gray-700 bg-white hover:bg-gray-50 border border-gray-300 px-3 py-1.5 rounded-lg shadow-xs transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    <span>Kelola Pilihan Rekening</span>
                </a>
            </div>
        </div>

        @if($kloter->bankAccounts->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($kloter->bankAccounts as $bank)
                    <div class="p-4 rounded-xl border border-gray-200 bg-gray-50/70 hover:bg-emerald-50/30 hover:border-emerald-200 transition flex flex-col justify-between">
                        <div>
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
                        <div class="mt-3 pt-3 border-t border-gray-200/80 flex items-center justify-end">
                            <button type="button" 
                                @click="editBank = { id: {{ $bank->id }}, bank_name: '{{ addslashes($bank->bank_name) }}', account_number: '{{ addslashes($bank->account_number) }}', account_holder: '{{ addslashes($bank->account_holder) }}', is_active: {{ $bank->is_active ? 'true' : 'false' }} }; showEditBankModal = true;"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:text-emerald-900 bg-white hover:bg-emerald-50 border border-emerald-300 rounded-lg shadow-2xs transition cursor-pointer">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <span>Edit Detail</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800 flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="flex-1">
                    <span class="font-bold block">Belum Ada Rekening Bank Khusus yang Ditautkan</span>
                    <p class="mt-0.5 text-amber-700">Secara otomatis, jamaah pada kloter ini akan disajikan semua rekening penampung aktif umum. Anda dapat menambahkan rekening baru khusus atau memilih rekening yang ada.</p>
                    <div class="mt-2.5 flex items-center gap-2">
                        <button type="button" @click="showAddBankModal = true" class="px-3 py-1 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-lg shadow-xs text-xs cursor-pointer">
                            + Tambah Rekening Khusus
                        </button>
                        <a href="{{ route('admin.kloters.edit', $kloter) }}" class="px-3 py-1 bg-white hover:bg-gray-100 text-gray-800 font-bold border border-gray-300 rounded-lg shadow-xs text-xs">
                            Pilih Dari Rekening Ada
                        </a>
                    </div>
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
                        <th class="px-6 py-4 text-center">Aksi</th>
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
                                            {{ $pax->familyMember?->full_name ?? $pax->familyMember?->name }}
                                            <span class="text-[10px] text-gray-500 ml-1">({{ ucfirst($pax->familyMember?->relationship ?? 'Peserta') }})</span>
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
                            <td class="px-6 py-4 text-center">
                                <button type="button"
                                        @click="openTriggerFor({{ $registration->id }}, '{{ addslashes($registration->user->name) }}')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-teal-200 bg-teal-50 text-[#007C6A] hover:bg-teal-100 text-xs font-bold shadow-2xs transition cursor-pointer"
                                        title="Terbitkan tagihan khusus untuk {{ $registration->user->name }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    <span>Generate Tagihan</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-xs text-gray-500">
                                Belum ada keluarga atau jama'ah yang mendaftar pada kloter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Riwayat Transaksi Pembayaran Jamaah Pada Kloter Ini -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center text-[#346733]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </div>
                    <h2 class="text-base font-bold text-gray-900">Riwayat Transaksi Pembayaran Jama'ah</h2>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    Semua transaksi pembayaran tabungan umroh oleh jama'ah pada kloter ini, diurutkan dari yang terbaru.
                </p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                {{ $payments->total() }} Transaksi
            </span>
        </div>

        <!-- Mobile Cards Format -->
        <div class="sm:hidden divide-y divide-slate-100 p-4 space-y-3">
            @forelse($payments as $payment)
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-900">{{ $payment->user->name }}</span>
                        @if($payment->isApproved())
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Approved</span>
                        @elseif($payment->isPending())
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Pending</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800">Rejected</span>
                        @endif
                    </div>

                    <div class="text-xs text-slate-500">
                        Tagihan: <span class="font-bold text-slate-700">{{ $payment->invoice?->invoice_number }}</span>
                        @if($payment->invoice)
                            &bull; Periode {{ $payment->invoice->period_label }}
                        @endif
                    </div>

                    <div class="flex items-baseline justify-between pt-1 border-t border-slate-200">
                        <div>
                            <span class="text-[11px] text-slate-400 block">Nominal Transfer:</span>
                            <span class="text-base font-black text-[#346733]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                        </div>
                        <div class="text-right text-[11px] text-slate-500">
                            <span>Tgl Bayar:</span>
                            <span class="font-semibold text-slate-700 block">{{ $payment->payment_date ? $payment->payment_date->locale('id')->translatedFormat('d M Y') : '-' }}</span>
                        </div>
                    </div>

                    <div class="text-[11px] text-slate-500 space-y-0.5 pt-1 border-t border-slate-100">
                        <div>Tujuan: <strong class="text-slate-700">{{ $payment->bankAccount?->bank_name ?? 'BMT/Bank' }} - {{ $payment->bankAccount?->account_number ?? '-' }}</strong></div>
                        @if($payment->sender_bank || $payment->sender_account_name)
                            <div>Pengirim: <span class="text-slate-600">{{ $payment->sender_bank }} a/n {{ $payment->sender_account_name }}</span></div>
                        @endif
                    </div>

                    <div class="pt-2 flex items-center justify-between border-t border-slate-200">
                        <button type="button"
                                @click.prevent="$dispatch('open-proof-modal', { url: '{{ $payment->proof_url }}', title: 'Bukti Transfer - {{ $payment->user->name }}' })"
                                class="text-xs text-teal-700 font-bold underline cursor-pointer">
                            Lihat Bukti Transfer
                        </button>
                        <a href="{{ route('admin.payments.show', $payment) }}" class="px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition shadow-sm">
                            Periksa &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-xs text-slate-400">
                    Belum ada transaksi pembayaran yang tercatat pada kloter ini.
                </div>
            @endforelse
        </div>

        <!-- Desktop Table Format -->
        <div class="overflow-x-auto hidden sm:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-semibold border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3.5">No</th>
                        <th class="px-4 py-3.5">Tgl Bayar & Jam</th>
                        <th class="px-4 py-3.5">Jama'ah / Pembayar</th>
                        <th class="px-4 py-3.5">Tagihan & Periode</th>
                        <th class="px-4 py-3.5">Rekening Tujuan</th>
                        <th class="px-4 py-3.5">Nominal</th>
                        <th class="px-4 py-3.5 text-center">Bukti Transfer</th>
                        <th class="px-4 py-3.5 text-center">Status</th>
                        <th class="px-4 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs">
                    @forelse($payments as $index => $payment)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3.5 text-gray-400 font-mono">
                                {{ $payments->firstItem() ? ($payments->firstItem() + $index) : ($index + 1) }}
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-gray-900">
                                    {{ $payment->payment_date ? $payment->payment_date->locale('id')->translatedFormat('d M Y') : '-' }}
                                </div>
                                <div class="text-[11px] text-gray-400">
                                    Input: {{ $payment->created_at->format('d/m/Y H:i') }}
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-gray-900">{{ $payment->user->name }}</div>
                                <div class="text-[11px] text-gray-500">{{ $payment->user->email }}</div>
                                @if($payment->sender_bank || $payment->sender_account_name)
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        Dari: {{ $payment->sender_bank }} a/n {{ $payment->sender_account_name }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-gray-900">{{ $payment->invoice?->invoice_number }}</div>
                                <div class="text-[11px] text-emerald-700 font-medium">
                                    {{ $payment->invoice ? 'Periode ' . $payment->invoice->period_label : '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-gray-800">{{ $payment->bankAccount?->bank_name ?? 'Kas BMT' }}</div>
                                <div class="text-[11px] text-gray-500 font-mono">{{ $payment->bankAccount?->account_number ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3.5 font-bold text-sm text-[#346733]">
                                Rp {{ number_format($payment->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <button type="button"
                                        @click.prevent="$dispatch('open-proof-modal', { url: '{{ $payment->proof_url }}', title: 'Bukti Transfer - {{ $payment->user->name }}' })"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold text-teal-700 bg-teal-50 border border-teal-200 hover:bg-teal-100 transition cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Bukti</span>
                                </button>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if($payment->isApproved())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">
                                        Approved
                                    </span>
                                    @if($payment->verifier)
                                        <div class="text-[10px] text-gray-400 mt-0.5">Oleh: {{ $payment->verifier->name }}</div>
                                    @endif
                                @elseif($payment->isPending())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                        Pending
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800">
                                        Rejected
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition shadow-sm">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-xs text-gray-500">
                                Belum ada transaksi pembayaran yang tercatat pada kloter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginasi Transaksi -->
        @if($payments->hasPages())
            <div class="p-4 border-t border-slate-100 bg-gray-50">
                {{ $payments->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Trigger Penagihan (Semua Jamaah / Per Orang) -->
    <div x-show="showTriggerModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div x-show="showTriggerModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showTriggerModal = false"></div>

            <div x-show="showTriggerModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form action="{{ route('admin.kloters.trigger-kloter-billing', $kloter) }}" method="POST">
                    @csrf
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-[#346733]">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-base">Generate Tagihan Manual</h3>
                                    <p class="text-xs text-gray-500">{{ $kloter->name }} ({{ $kloter->code }})</p>
                                </div>
                            </div>
                            <button type="button" @click="showTriggerModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold leading-none cursor-pointer">&times;</button>
                        </div>

                        <div class="space-y-4">
                            <!-- 1. Pilihan Target: Semua Jamaah vs Per Orang -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Target Penerima Tagihan</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition select-none"
                                           :class="targetType === 'all' ? 'border-[#346733] bg-emerald-50 text-[#346733] font-bold' : 'border-slate-200 bg-white text-slate-700'">
                                        <input type="radio" name="target_selector" value="all" x-model="targetType" @change="selectedRegistrationId = 'all'" class="text-[#346733] focus:ring-[#346733]">
                                        <div class="text-xs">
                                            <span class="block">Semua Jama'ah</span>
                                            <span class="text-[10px] opacity-75 font-normal">({{ $totalFamilyCount }} Akun, {{ $totalPaxCount }} Jiwa)</span>
                                        </div>
                                    </label>

                                    <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition select-none"
                                           :class="targetType === 'individual' ? 'border-[#346733] bg-emerald-50 text-[#346733] font-bold' : 'border-slate-200 bg-white text-slate-700'">
                                        <input type="radio" name="target_selector" value="individual" x-model="targetType" class="text-[#346733] focus:ring-[#346733]">
                                        <div class="text-xs">
                                            <span class="block">Per Orang</span>
                                            <span class="text-[10px] opacity-75 font-normal">Pilih 1 pendaftar tertentu</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Dropdown Pilihan Pendaftar (Tampil jika Per Orang) -->
                            <div x-show="targetType === 'individual'" x-transition class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                                <label class="block text-xs font-semibold text-slate-800">Pilih Jama'ah / Akun Penanggung Jawab:</label>
                                <select name="registration_id" x-model="selectedRegistrationId" class="w-full text-xs font-medium rounded-xl border-slate-300 shadow-sm focus:border-[#346733] focus:ring-2 focus:ring-[#346733] py-2 px-3">
                                    <option value="all" disabled>-- Pilih Jama'ah --</option>
                                    @foreach($kloter->registrations as $r)
                                        <option value="{{ $r->id }}">
                                            {{ $r->user->name }} ({{ $r->paxes->count() }} Jiwa) &bull; Rp {{ number_format($r->paxes->count() * $kloter->monthly_per_pax, 0, ',', '.') }}/bln
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- 2. Periode Penagihan -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Periode Penagihan (Bulan / Tahun)</label>
                                <input type="month" name="billing_date" value="{{ date('Y-m') }}" class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 shadow-sm focus:border-[#346733] focus:ring-2 focus:ring-[#346733] outline-none" required>
                                <p class="text-[11px] text-gray-400 mt-1">Sistem idempoten: Tagihan yang sudah terbit pada bulan ini tidak akan terduplikasi.</p>
                            </div>

                            <!-- 3. Opsi Terbitkan Sekaligus Periode Tertunggak -->
                            <div class="pt-2 border-t border-slate-100">
                                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                                    <input type="checkbox" name="generate_all_pending" value="1" class="mt-0.5 w-4 h-4 rounded text-[#346733] border-slate-300 focus:ring-[#346733]">
                                    <div class="text-xs">
                                        <span class="font-bold text-gray-800">Terbitkan Sekaligus Seluruh Periode Tertunggak</span>
                                        <p class="text-[11px] text-gray-500 mt-0.5">
                                            Generate otomatis seluruh bulan yang belum pernah diterbitkan sejak awal kloter / bulan efektif bergabung sampai periode yang dipilih.
                                        </p>
                                    </div>
                                </label>
                            </div>

                            <!-- Info Kalkulasi Ringkas -->
                            <div class="p-3 bg-emerald-50 rounded-xl text-xs text-emerald-900 border border-emerald-200 space-y-1">
                                <div class="font-bold flex items-center gap-1">
                                    <svg class="w-4 h-4 text-[#346733]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Informasi Tarif & Aturan:</span>
                                </div>
                                <ul class="list-disc list-inside space-y-0.5 text-[11px] text-emerald-800">
                                    <li>Tarif kloter: <strong>Rp {{ number_format($kloter->monthly_per_pax, 0, ',', '.') }}</strong> / jiwa per bulan.</li>
                                    <li>Tagihan per keluarga = tarif bulanan &times; jumlah jiwa (pax).</li>
                                    <li>Jama'ah susulan (late joiner) dihitung otomatis mulai bulan efektif bergabung.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-3 flex justify-end gap-2 border-t border-gray-100">
                        <button type="button" @click="showTriggerModal = false" class="px-4 py-2 border border-gray-300 rounded-xl text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#346733] hover:bg-[#234622] shadow-sm transition cursor-pointer">
                            <span x-text="targetType === 'individual' ? 'Generate Tagihan Per Orang' : 'Generate Semua Jama\'ah Kloter'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Detail Rekening Bank -->
    <div x-show="showEditBankModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showEditBankModal" x-transition.opacity class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="showEditBankModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showEditBankModal" x-transition class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                <div class="flex items-center justify-between pb-3 border-b border-gray-200">
                    <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit Detail Rekening Bank</span>
                    </h3>
                    <button type="button" @click="showEditBankModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
                </div>

                <form :action="'/admin/bank-accounts/' + editBank.id" method="POST" class="mt-4 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Bank / Institusi</label>
                        <input type="text" name="bank_name" x-model="editBank.bank_name" required placeholder="Contoh: Bank Syariah Indonesia (BSI)" class="w-full text-sm rounded-xl border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Rekening</label>
                        <input type="text" name="account_number" x-model="editBank.account_number" required placeholder="Contoh: 7123456789" class="w-full text-sm font-mono rounded-xl border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Atas Nama (Pemilik Rekening)</label>
                        <input type="text" name="account_holder" x-model="editBank.account_holder" required placeholder="Contoh: Yayasan Tabungan Umroh" class="w-full text-sm rounded-xl border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 px-3 py-2">
                    </div>

                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" x-model="editBank.is_active" class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-gray-300">
                            <span class="text-xs font-semibold text-gray-800">Rekening Aktif (Dapat digunakan dan tampil pada tagihan)</span>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-2">
                        <button type="button" @click="showEditBankModal = false" class="px-4 py-2 rounded-xl border border-gray-300 text-xs font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white shadow-sm transition bg-[#346733] hover:bg-[#234622] cursor-pointer">
                            Simpan Perubahan Rekening
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Rekening Bank Baru -->
    <div x-show="showAddBankModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showAddBankModal" x-transition.opacity class="fixed inset-0 bg-gray-500/75 transition-opacity" @click="showAddBankModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showAddBankModal" x-transition class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full p-6">
                <div class="flex items-center justify-between pb-3 border-b border-gray-200">
                    <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Rekening Bank Baru</span>
                    </h3>
                    <button type="button" @click="showAddBankModal = false" class="text-gray-400 hover:text-gray-600 text-lg font-bold">&times;</button>
                </div>

                <form action="{{ route('admin.bank-accounts.store') }}" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="kloter_id" value="{{ $kloter->id }}">

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Bank / Institusi</label>
                        <input type="text" name="bank_name" required placeholder="Contoh: Bank Syariah Indonesia (BSI)" class="w-full text-sm rounded-xl border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nomor Rekening</label>
                        <input type="text" name="account_number" required placeholder="Contoh: 7123456789" class="w-full text-sm font-mono rounded-xl border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 px-3 py-2">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Atas Nama (Pemilik Rekening)</label>
                        <input type="text" name="account_holder" required placeholder="Contoh: Yayasan Tabungan Umroh" class="w-full text-sm rounded-xl border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 px-3 py-2">
                    </div>

                    <div class="pt-2">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-gray-300">
                            <span class="text-xs font-semibold text-gray-800">Langsung Aktifkan Rekening</span>
                        </label>
                    </div>

                    <p class="text-[11px] text-gray-500 bg-emerald-50 p-2.5 rounded-lg border border-emerald-100">
                        Rekening baru ini akan otomatis ditautkan ke kloter <strong>{{ $kloter->name }}</strong>.
                    </p>

                    <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-2">
                        <button type="button" @click="showAddBankModal = false" class="px-4 py-2 rounded-xl border border-gray-300 text-xs font-bold text-gray-700 hover:bg-gray-50 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white shadow-sm transition bg-[#346733] hover:bg-[#234622] cursor-pointer">
                            Simpan Rekening
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
