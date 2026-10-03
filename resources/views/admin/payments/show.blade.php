{{--
/**
 * File: resources/views/admin/payments/show.blade.php
 * Tujuan: Halaman detail verifikasi bukti transfer manual oleh admin keuangan dengan visual preview, modal konfirmasi approval, modal penolakan rejection, modal pembatalan/revisi verifikasi, dan breakdown alokasi tagihan/carry-over
 * Dipakai Oleh: App\Http\Controllers\Admin\PaymentApprovalController@show
 * Dependensi Utama: Tailwind CSS CDN, Alpine.js, App\Models\Payment, App\Models\Invoice
 * Daftar Komponen Utama: Preview bukti transfer, status badge, audit trail verifikator, breakdown tagihan terkait & carry-over credit, modal konfirmasi approval, modal penolakan rejection, modal revisi/pembatalan verifikasi, anti-self-approval alert
 * Side Effect: POST form approval, rejection, dan revert/revisi status ke admin payment routes
 */
--}}
@extends('layouts.app')

@section('title', 'Verifikasi Pembayaran #' . $payment->id)

@section('content')
<div class="space-y-6" x-data="{ showRejectModal: false, showApproveModal: false, showRevertModal: false }">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center space-x-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('admin.payments.index') }}" class="hover:text-haramain-green flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Daftar Pembayaran
                </a>
                <span>/</span>
                <span class="text-gray-700 font-medium">Verifikasi #{{ $payment->id }}</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                Verifikasi Bukti Transfer
                @if($payment->status === 'approved')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                        ✓ Disetujui
                    </span>
                @elseif($payment->status === 'pending')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 animate-pulse">
                        Menunggu Verifikasi
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                        ✕ Ditolak
                    </span>
                @endif
            </h1>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.payments.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                Kembali
            </a>
        </div>
    </div>

    <!-- Alert Anti Self-Approval -->
    @if($payment->user_id === auth()->id())
        <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-semibold text-amber-800">Perhatian: Kebijakan Integritas & Anti Self-Approval</h3>
                    <div class="mt-1 text-xs text-amber-700">
                        <p>Pembayaran ini diajukan oleh akun Anda sendiri. Sesuai prinsip <strong>Separation of Duty (SOP Keuangan Menuju Haramain)</strong>, Anda dilarang memverifikasi pembayaran Anda sendiri. Mohon minta Administrator Keuangan atau Superadmin lain untuk melakukan verifikasi bukti transfer ini.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Kolom Kiri: Preview Bukti Transfer (7 Kolom) -->
        <div class="lg:col-span-7 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                        <svg class="w-4 h-4 text-haramain-green" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Bukti Struk Transfer
                    </h3>
                    @if($payment->proof_path)
                        <a href="{{ $payment->proof_url }}" 
                           @click.prevent="$dispatch('open-proof-modal', { url: '{{ $payment->proof_url }}', title: 'Bukti Transfer #{{ $payment->id }}' })"
                           target="_blank" 
                           class="text-xs text-haramain-teal hover:underline flex items-center font-medium cursor-pointer">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            Buka Popup Zoom
                        </a>
                    @endif
                </div>

                <div class="p-6 bg-gray-100 flex items-center justify-center min-h-[420px]">
                    @if($payment->proof_path)
                        <div class="relative max-w-full">
                            <img src="{{ $payment->proof_url }}" 
                                 alt="Bukti Transfer" 
                                 @click="$dispatch('open-proof-modal', { url: '{{ $payment->proof_url }}', title: 'Bukti Transfer #{{ $payment->id }}' })"
                                 class="max-h-[600px] w-auto rounded-lg shadow border border-gray-300 object-contain cursor-zoom-in hover:brightness-105 transition"
                                 title="Klik untuk membuka popup zoom detail">
                        </div>
                    @else
                        <div class="text-center py-12 text-gray-400">
                            <svg class="w-16 h-16 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <p class="text-sm font-medium">File bukti transfer tidak ditemukan</p>
                        </div>
                    @endif
                </div>

                @if($payment->notes)
                    <div class="p-4 bg-gray-50 border-t border-gray-200">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Catatan Pengirim:</p>
                        <p class="text-sm text-gray-800 italic">"{{ $payment->notes }}"</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Kolom Kanan: Rincian Tagihan & Aksi Verifikasi (5 Kolom) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Kartu Detail Transaksi -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-5 border-b border-gray-200">
                    <h3 class="font-bold text-gray-900 text-base">Rincian Pembayaran</h3>
                </div>
                <div class="p-5 divide-y divide-gray-100 text-sm">
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Nominal Transfer</span>
                        <span class="font-extrabold text-haramain-green text-lg">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Tanggal Transfer</span>
                        <span class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($payment->payment_date)->format('Y-m-d') }}</span>
                    </div>
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Jamaah / Pembayar</span>
                        <div class="text-right">
                            <span class="font-semibold text-gray-900 block">{{ $payment->user->name }}</span>
                            <span class="text-xs text-gray-500">{{ $payment->user->email }} • {{ $payment->user->phone ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Rekening Tujuan</span>
                        <div class="text-right">
                            <span class="font-medium text-gray-900 block">{{ $payment->bankAccount->bank_name ?? 'Transfer Bank' }}</span>
                            <span class="text-xs text-gray-500">{{ $payment->bankAccount->account_number ?? '-' }} a.n {{ $payment->bankAccount->account_name ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Tagihan Terkait</span>
                        <div class="text-right">
                            <span class="font-mono text-xs font-semibold text-haramain-teal block">#{{ $payment->invoice->invoice_number }}</span>
                            <span class="text-xs text-gray-500">Periode {{ $payment->invoice->period_label }}</span>
                        </div>
                    </div>
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Total Tagihan Periode Ini</span>
                        <span class="font-semibold text-gray-800">Rp {{ number_format($payment->invoice->total_amount, 0, ',', '.') }}</span>
                    </div>
                    @if($payment->invoice->carry_over_credit > 0)
                        <div class="py-2.5 flex justify-between items-center text-teal-800 bg-teal-50 px-2 rounded-lg">
                            <span class="text-xs">Kredit Periode Lalu</span>
                            <span class="text-xs font-bold">- Rp {{ number_format($payment->invoice->carry_over_credit, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Telah Teralokasi</span>
                        <span class="font-semibold text-emerald-700">Rp {{ number_format($payment->invoice->paid_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Sisa Kewajiban Tagihan</span>
                        <span class="font-extrabold {{ $payment->invoice->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                            Rp {{ number_format($payment->invoice->remaining_amount, 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Kloter Umroh</span>
                        <span class="font-medium text-gray-900 text-right">{{ $payment->invoice->registration->kloter->name ?? '-' }}</span>
                    </div>
                    <div class="py-3 flex justify-between items-center">
                        <span class="text-gray-500">Waktu Kirim Konfirmasi</span>
                        <span class="text-xs text-gray-600">{{ $payment->created_at->format('Y-m-d H:i') }} WIB</span>
                    </div>
                </div>
            </div>

            <!-- Kartu Status Verifikasi / Audit Log -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-3">
                <h4 class="font-semibold text-gray-900 text-sm mb-1">Status Verifikasi</h4>
                
                @if($payment->status === 'approved')
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 space-y-1">
                        <div class="flex items-center gap-2 text-green-800 font-semibold">
                            <svg class="w-5 h-5 text-green-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Telah Diverifikasi & Disetujui</span>
                        </div>
                        <p class="text-xs text-green-700">Oleh: <strong>{{ $payment->verifier->name ?? 'Admin Keuangan' }}</strong></p>
                        <p class="text-xs text-green-600">Waktu: {{ $payment->verified_at ? \Carbon\Carbon::parse($payment->verified_at)->format('Y-m-d H:i') . ' WIB' : '-' }}</p>
                    </div>

                    @if($payment->user_id !== auth()->id())
                        <!-- Tombol Revisi untuk Pembayaran yang Sudah Approved -->
                        <div class="pt-2 border-t border-slate-100 flex flex-col sm:flex-row gap-2">
                            <button type="button" @click="showRevertModal = true" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-900 font-bold text-xs transition cursor-pointer">
                                <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Revisi & Tinjau Ulang</span>
                            </button>
                            <button type="button" @click="showRejectModal = true" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-red-50 hover:bg-red-100 border border-red-300 text-red-700 font-bold text-xs transition cursor-pointer">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                <span>Ubah Jadi Ditolak</span>
                            </button>
                        </div>
                    @endif
                @elseif($payment->status === 'rejected')
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 space-y-1">
                        <div class="flex items-center gap-2 text-red-800 font-semibold">
                            <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Pembayaran Ditolak</span>
                        </div>
                        <p class="text-xs text-red-700">Oleh: <strong>{{ $payment->verifier->name ?? 'Admin Keuangan' }}</strong></p>
                        <p class="text-xs text-red-600">Waktu: {{ $payment->verified_at ? \Carbon\Carbon::parse($payment->verified_at)->format('Y-m-d H:i') . ' WIB' : '-' }}</p>
                        @if($payment->admin_notes)
                            <div class="mt-2 pt-2 border-t border-red-200 text-xs text-red-800">
                                <strong>Alasan Penolakan:</strong>
                                <p class="mt-0.5 italic">"{{ $payment->admin_notes }}"</p>
                            </div>
                        @endif
                    </div>

                    @if($payment->user_id !== auth()->id())
                        <!-- Tombol Revisi untuk Pembayaran yang Ditolak -->
                        <div class="pt-2 border-t border-slate-100 flex flex-col sm:flex-row gap-2">
                            <button type="button" @click="showRevertModal = true" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-900 font-bold text-xs transition cursor-pointer">
                                <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Revisi & Tinjau Ulang</span>
                            </button>
                            <button type="button" @click="showApproveModal = true" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-800 font-bold text-xs transition cursor-pointer">
                                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Ubah Jadi Disetujui</span>
                            </button>
                        </div>
                    @endif
                @else
                    <!-- Status Pending: Form Verifikasi -->
                    @if($payment->user_id === auth()->id())
                        <div class="text-center py-4 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                            <svg class="w-10 h-10 mx-auto text-amber-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <p class="text-sm font-semibold text-gray-700">Verifikasi Terkunci</p>
                            <p class="text-xs text-gray-500 mt-1 max-w-xs mx-auto">Anda tidak dapat menyetujui kiriman pembayaran Anda sendiri demi menjaga integritas data keuangan.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            <button type="button" 
                                    @click="showApproveModal = true" 
                                    class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-white font-bold text-sm bg-[#346733] hover:bg-[#234622] active:scale-[0.99] shadow-md transition cursor-pointer">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                <span>Setujui Pembayaran (Approve)</span>
                            </button>

                            <button type="button" @click="showRejectModal = true" class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl border-2 border-red-300 text-red-600 font-semibold hover:bg-red-50 active:scale-[0.99] transition cursor-pointer">
                                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                <span>Tolak Pembayaran (Reject)</span>
                            </button>
                        </div>
                    @endif
                @endif
            </div>

            <!-- Rincian Pax Tagihan -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="font-semibold text-gray-900 text-sm mb-3">Peserta yang Ditagih</h4>
                <div class="space-y-2">
                    @foreach($payment->invoice->items as $item)
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-gray-50 text-xs">
                            <div>
                                <span class="font-semibold text-gray-900 block">{{ $item->registrationPax->familyMember->name ?? 'Jamaah' }}</span>
                                <span class="text-gray-500 capitalize">{{ $item->registrationPax->familyMember->relationship ?? '-' }}</span>
                            </div>
                            <span class="font-bold text-gray-700">Rp {{ number_format($item->amount, 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Penolakan Pembayaran -->
    <div x-show="showRejectModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showRejectModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showRejectModal = false"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showRejectModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form action="{{ route('admin.payments.reject', $payment) }}" method="POST">
                    @csrf
                    <div class="bg-white px-6 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Tolak Bukti Transfer</h3>
                                <div class="mt-2">
                                    <p class="text-xs text-gray-500 mb-3">
                                        Mohon berikan alasan penolakan secara jelas agar jamaah dapat memperbaiki transfer atau mengunggah ulang bukti yang valid.
                                    </p>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Alasan Penolakan <span class="text-red-500">*</span></label>
                                    <textarea name="admin_notes" rows="4" required minlength="5" maxlength="500" placeholder="Contoh: Foto bukti transfer buram / nominal tidak sesuai / nama rekening pengirim tidak jelas" class="w-full p-3.5 text-sm rounded-xl border border-slate-300 shadow-sm focus:border-red-500 focus:ring-2 focus:ring-red-500 outline-none"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="submit" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-sm font-medium text-white hover:bg-red-700 sm:w-auto">
                            Konfirmasi Penolakan
                        </button>
                        <button type="button" @click="showRejectModal = false" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Persetujuan Pembayaran (Approve Modal) -->
    <div x-show="showApproveModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-approve-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop Gelap -->
            <div x-show="showApproveModal" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity" 
                 @click="showApproveModal = false"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Dialog Modal Card -->
            <div x-show="showApproveModal" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100">
                
                <form action="{{ route('admin.payments.approve', $payment) }}" method="POST">
                    @csrf
                    <div class="p-6 sm:p-7">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-2xl bg-emerald-100 flex items-center justify-center text-[#346733] shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-extrabold text-slate-900 leading-tight" id="modal-approve-title">
                                    Konfirmasi Persetujuan Pembayaran
                                </h3>
                                <p class="text-xs text-slate-500 mt-1">
                                    Pastikan mutasi dana pada rekening yayasan telah diperiksa dan jumlahnya sesuai.
                                </p>
                            </div>
                        </div>

                        <!-- Ringkasan Box Transaksi -->
                        <div class="mt-5 p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs">
                            <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                                <span class="text-slate-500">Nominal Transfer:</span>
                                <span class="font-black text-base text-[#346733]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Nama Jama'ah:</span>
                                <span class="font-bold text-slate-800">{{ $payment->user->name }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Tagihan:</span>
                                <span class="font-mono font-semibold text-teal-800">#{{ $payment->invoice->invoice_number }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Rekening Tujuan:</span>
                                <span class="font-medium text-slate-700">{{ $payment->bankAccount->bank_name ?? 'Bank Tujuan' }}</span>
                            </div>
                        </div>

                        <div class="mt-4 p-3 rounded-xl bg-emerald-50 text-[11px] text-emerald-800 border border-emerald-200/80 flex items-start gap-2">
                            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Setelah disetujui, status pembayaran akan menjadi <strong>Disetujui (Approved)</strong> dan akumulasi tabungan jamaah akan langsung tercatat.</span>
                        </div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="bg-slate-50/90 px-6 py-4 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                        <button type="button" 
                                @click="showApproveModal = false" 
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" 
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-[#346733] hover:bg-[#234622] shadow-md transition cursor-pointer">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Ya, Setujui Pembayaran</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Pembatalan / Revisi Verifikasi (Revert Modal) -->
    <div x-show="showRevertModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-revert-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showRevertModal" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity" 
                 @click="showRevertModal = false"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showRevertModal" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="inline-block align-bottom bg-white rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100">
                
                <form action="{{ route('admin.payments.revert', $payment) }}" method="POST">
                    @csrf
                    <div class="p-6 sm:p-7">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-2xl bg-amber-100 flex items-center justify-center text-amber-700 shadow-sm">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-extrabold text-slate-900 leading-tight" id="modal-revert-title">
                                    Revisi & Batalkan Status Verifikasi
                                </h3>
                                <p class="text-xs text-slate-500 mt-1">
                                    Status pembayaran akan dikembalikan ke <strong>Menunggu Verifikasi (Pending)</strong> untuk ditinjau ulang. Saldo tagihan invoice akan disinkronkan secara otomatis.
                                </p>
                            </div>
                        </div>

                        <!-- Ringkasan Box Transaksi -->
                        <div class="mt-5 p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs">
                            <div class="flex justify-between items-center pb-2 border-b border-slate-200">
                                <span class="text-slate-500">Nominal Transfer:</span>
                                <span class="font-black text-base text-[#346733]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Nama Jama'ah:</span>
                                <span class="font-bold text-slate-800">{{ $payment->user->name }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-slate-500">Status Saat Ini:</span>
                                <span class="font-bold {{ $payment->isApproved() ? 'text-emerald-700' : 'text-red-700' }} uppercase">{{ $payment->status }}</span>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Catatan / Alasan Revisi <span class="text-slate-400 font-normal">(Opsional)</span>
                            </label>
                            <textarea name="reason" rows="3" maxlength="500" placeholder="Contoh: Kesalahan klik verifikasi / rekonsiliasi ulang mutasi bank" class="w-full p-3 text-xs rounded-xl border border-slate-300 shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500 outline-none"></textarea>
                        </div>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="bg-slate-50/90 px-6 py-4 border-t border-slate-100 flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                        <button type="button" 
                                @click="showRevertModal = false" 
                                class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" 
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 shadow-md transition cursor-pointer">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>Ya, Kembalikan ke Pending</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
