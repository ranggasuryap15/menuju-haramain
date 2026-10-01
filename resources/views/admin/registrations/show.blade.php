<!--
File: resources/views/admin/registrations/show.blade.php
Tujuan: Halaman detail satu pendaftaran kloter jamaah untuk diverifikasi dan disetujui/ditolak oleh Admin/Superadmin
Dipakai Oleh: App\Http\Controllers\Admin\RegistrationApprovalController@show (GET /admin/registrations/{registration})
Dependensi Utama: layouts.app, KloterRegistration, Alpine.js
Daftar Komponen Utama: Rincian pemohon, Detail paket kloter, Rincian seluruh peserta keluarga, Audit trail verifikator, Modal approval & rejection
Side Effect: POST ke /admin/registrations/{registration}/approve dan reject
-->
@extends('layouts.app')

@section('title', 'Detail Pendaftaran Kloter #' . $registration->id)

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    approveModalOpen: false,
    rejectModalOpen: false
}">

    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <div class="flex items-center space-x-2">
                <span class="text-xs font-black text-slate-500 bg-slate-100 px-2 py-0.5 rounded">ID #{{ $registration->id }}</span>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900">Pendaftaran Kloter {{ $registration->kloter->code }}</h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Diajukan pada: <strong>{{ $registration->created_at->isoFormat('dddd, D MMMM Y • HH:mm') }} WIB</strong>
            </p>
        </div>

        <div class="flex items-center space-x-3">
            @if($registration->isPending())
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">
                    &bull; Menunggu Approval Admin
                </span>
            @elseif($registration->isActive())
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                    &check; Aktif & Disetujui
                </span>
            @elseif($registration->isRejected())
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-300">
                    &times; Pendaftaran Ditolak
                </span>
            @else
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">
                    {{ $registration->status }}
                </span>
            @endif

            <a href="{{ route('admin.registrations.index') }}" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-100 transition">
                &larr; Kembali
            </a>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Kolom Kiri (2 Kolom): Rincian Peserta & Paket Kloter -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Data Pemohon Jamaah -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
                    Data Jamaah Pendaftar
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block">Nama Lengkap:</span>
                        <span class="font-bold text-slate-900 text-sm">{{ $registration->user->name }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Alamat Email:</span>
                        <span class="font-semibold text-slate-800">{{ $registration->user->email }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Nomor Telepon / WA:</span>
                        <span class="font-semibold text-slate-800">{{ $registration->user->phone ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Peran Akun:</span>
                        <span class="font-semibold text-slate-800 capitalize">{{ $registration->user->role }}</span>
                    </div>
                </div>
            </div>

            <!-- Rincian Seluruh Peserta (Pax) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                        Daftar Anggota Keluarga ({{ $registration->paxes->count() }} Orang)
                    </h2>
                    <span class="text-xs font-bold text-[#346733]">Total {{ $registration->total_pax }} Pax</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($registration->paxes as $pax)
                        <div class="py-3 flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-slate-900 block text-sm">
                                    {{ $pax->familyMember->full_name ?? 'Peserta' }}
                                </span>
                                <span class="text-slate-500">
                                    Hubungan: <strong>{{ $pax->familyMember->relationship ?? '-' }}</strong> &bull;
                                    Gender: <strong>{{ $pax->familyMember->gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</strong>
                                    @if($pax->familyMember->identity_number)
                                        &bull; NIK: {{ $pax->familyMember->identity_number }}
                                    @endif
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-slate-800 block">
                                    Rp {{ number_format($registration->kloter->monthly_per_pax, 0, ',', '.') }} / bln
                                </span>
                                <span class="text-[11px] text-slate-400">Target Rp {{ number_format($registration->kloter->target_per_pax, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Tagihan yang sudah terbit (jika ada) -->
            @if($registration->invoices->count() > 0)
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
                        Tagihan Terbit Pada Kloter Ini ({{ $registration->invoices->count() }})
                    </h2>
                    <div class="divide-y divide-slate-100 text-xs">
                        @foreach($registration->invoices as $inv)
                            <div class="py-2.5 flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-[#346733]">{{ $inv->invoice_number }}</span>
                                    <span class="text-slate-500 block">Periode {{ $inv->period_label }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-slate-800">Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</span>
                                    <span class="text-[10px] block {{ $inv->isPaid() ? 'text-emerald-700 font-bold' : 'text-amber-700' }}">
                                        {{ $inv->isPaid() ? 'Lunas' : 'Sisa Rp ' . number_format($inv->remaining_amount, 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

        <!-- Kolom Kanan (1 Kolom): Rincian Kloter & Tombol Aksi Approval -->
        <div class="space-y-6">

            <!-- Detail Kloter -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-3 text-xs">
                <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-2 uppercase tracking-wider">
                    Informasi Kloter
                </h3>
                <div class="space-y-2">
                    <div>
                        <span class="text-slate-400 block">Nama Kloter:</span>
                        <span class="font-bold text-slate-900">{{ $registration->kloter->name }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Kode Kloter:</span>
                        <span class="font-bold text-[#346733]">{{ $registration->kloter->code }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Biaya Menabung / Pax:</span>
                        <span class="font-semibold text-slate-800">Rp {{ number_format($registration->kloter->monthly_per_pax, 0, ',', '.') }} / bulan</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Total Target Seluruh Pax:</span>
                        <span class="font-extrabold text-slate-900 text-sm">Rp {{ number_format($registration->target_total, 0, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Jadwal Menabung:</span>
                        <span class="font-medium text-slate-700">{{ $registration->kloter->start_date->format('d M Y') }} s/d {{ $registration->kloter->end_date->format('d M Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Panel Status & Aksi Verifikasi -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <h3 class="font-bold text-slate-900 text-sm border-b border-slate-100 pb-2">
                    Status Verifikasi
                </h3>

                @if($registration->isActive())
                    <div class="p-3.5 bg-emerald-50 rounded-xl border border-emerald-200 text-xs space-y-1 text-emerald-900">
                        <div class="flex items-center space-x-1.5 font-bold text-emerald-800">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>Pendaftaran Disetujui (Aktif)</span>
                        </div>
                        <p class="text-[11px] text-emerald-700">
                            Disetujui oleh: <strong>{{ $registration->approver->name ?? 'Admin' }}</strong><br>
                            Waktu: {{ $registration->approved_at ? $registration->approved_at->isoFormat('D MMMM Y • HH:mm') . ' WIB' : '-' }}
                        </p>
                    </div>
                @elseif($registration->isRejected())
                    <div class="p-3.5 bg-red-50 rounded-xl border border-red-200 text-xs space-y-1 text-red-900">
                        <div class="flex items-center space-x-1.5 font-bold text-red-800">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Pendaftaran Ditolak</span>
                        </div>
                        <p class="text-[11px] text-red-700">
                            Ditolak oleh: <strong>{{ $registration->approver->name ?? 'Admin' }}</strong><br>
                            Waktu: {{ $registration->approved_at ? $registration->approved_at->isoFormat('D MMMM Y • HH:mm') . ' WIB' : '-' }}
                        </p>
                        @if($registration->admin_notes)
                            <div class="mt-2 pt-2 border-t border-red-200 text-[11px]">
                                <strong>Alasan Penolakan:</strong> {{ $registration->admin_notes }}
                            </div>
                        @endif
                    </div>
                @else
                    <!-- Status Pending: Tombol Aksi Verifikasi -->
                    <div class="space-y-3">
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Pendaftaran ini sedang menunggu persetujuan Admin/Superadmin. Silakan pastikan kuota kloter tersedia sebelum menyetujui.
                        </p>

                        <button type="button" @click="approveModalOpen = true"
                                class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-white font-bold text-xs bg-[#346733] hover:bg-[#234622] shadow-md transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>Setujui Pendaftaran (Approve)</span>
                        </button>

                        <button type="button" @click="rejectModalOpen = true"
                                class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl border-2 border-red-300 text-red-600 font-bold text-xs hover:bg-red-50 transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Tolak Pendaftaran (Reject)</span>
                        </button>
                    </div>
                @endif
            </div>

        </div>

    </div>

    <!-- ===================================================================== -->
    <!-- MODAL KONFIRMASI APPROVAL                                             -->
    <!-- ===================================================================== -->
    <div x-show="approveModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center sm:p-0">
            <div x-show="approveModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="approveModalOpen = false"></div>

            <div x-show="approveModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0"
                 class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-200">
                
                <form action="{{ route('admin.registrations.approve', $registration) }}" method="POST">
                    @csrf
                    <div class="bg-white p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-emerald-100 text-[#346733] flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900">Setujui Pendaftaran Kloter</h3>
                                <p class="text-xs text-slate-500">Konfirmasi penerimaan jamaah ke kloter.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Jamaah:</span>
                                <span class="font-bold text-slate-900">{{ $registration->user->name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Kloter:</span>
                                <span class="font-bold text-[#346733]">{{ $registration->kloter->name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Jumlah Peserta:</span>
                                <span class="font-semibold text-slate-800">{{ $registration->total_pax }} Orang</span>
                            </div>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed">
                            Apakah Anda yakin ingin menyetujui pendaftaran ini? Jamaah akan resmi terdaftar dan jadwal tagihan tabungan akan otomatis diaktifkan.
                        </p>
                    </div>

                    <div class="bg-slate-50 px-6 py-4 flex flex-row-reverse gap-2 border-t border-slate-100">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition shadow-sm cursor-pointer">
                            Ya, Setujui Sekarang
                        </button>
                        <button type="button" @click="approveModalOpen = false" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 transition cursor-pointer">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL PENOLAKAN PENDAFTARAN                                           -->
    <!-- ===================================================================== -->
    <div x-show="rejectModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center sm:p-0">
            <div x-show="rejectModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="rejectModalOpen = false"></div>

            <div x-show="rejectModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0"
                 class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-200">
                
                <form action="{{ route('admin.registrations.reject', $registration) }}" method="POST">
                    @csrf
                    <div class="bg-white p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900">Tolak Pendaftaran Kloter</h3>
                                <p class="text-xs text-slate-500">Mohon sertakan alasan penolakan untuk jamaah.</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Alasan Penolakan <span class="text-red-500">*</span>
                            </label>
                            <textarea name="admin_notes" rows="3" required minlength="5" maxlength="500"
                                      placeholder="Contoh: Kuota kloter ini sudah penuh / Dokumen identitas belum sesuai"
                                      class="w-full px-4 py-3 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"></textarea>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-6 py-4 flex flex-row-reverse gap-2 border-t border-slate-100">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition shadow-sm cursor-pointer">
                            Konfirmasi Tolak
                        </button>
                        <button type="button" @click="rejectModalOpen = false" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 transition cursor-pointer">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
