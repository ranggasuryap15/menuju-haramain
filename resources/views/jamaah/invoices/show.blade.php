{{--
/**
 * File: resources/views/jamaah/invoices/show.blade.php
 * Tujuan: Halaman detail rincian tagihan per pax, breakdown alokasi FIFO, daftar rekening bank spesifik kloter/fallback, banner reminder WhatsApp ke admin/superadmin setelah transfer, banner link grup WhatsApp kloter, penguncian kronologis, form upload bukti transfer, dan riwayat pembayaran
 * Dipakai Oleh: Jamaah\InvoiceController@show (GET /jamaah/invoices/{invoice})
 * Dependensi Utama: layouts.app, Invoice, BankAccount, Payment, Kloter, User
 * Daftar Komponen Utama: Rincian item per orang, Ringkasan alokasi & saldo kredit, Banner reminder WA konfirmasi pembayaran, Tombol gabung grup WA kloter, Widget rekening bank transfer kloter, Banner penguncian kronologis, Formulir upload bukti struk + preview, Status verifikasi admin
 * Side Effect: POST ke /jamaah/invoices/{invoice}/payments, direct link ke wa.me & tautan chat.whatsapp.com
 */
--}}
@extends('layouts.app')

@section('title', 'Detail Tagihan ' . $invoice->invoice_number)

@section('content')
@php
    $latestPayment = $invoice->payments->first();
    $amountFormatted = 'Rp ' . number_format($latestPayment ? $latestPayment->amount : $invoice->remaining_amount, 0, ',', '.');
    $bankName = $latestPayment?->bankAccount?->bank_name ?? ($latestPayment?->sender_bank ?? 'Rekening Bank Resmi');
    $paymentDateFormatted = $latestPayment ? $latestPayment->payment_date->format('Y-m-d') : now()->format('Y-m-d');
    $jamaahName = auth()->user()->name;
    $kloterName = $invoice->registration?->kloter?->name ?? '-';
    $invoiceNum = $invoice->invoice_number;
    $periodLabel = $invoice->period_label;

    $waReminderMessage = "Assalamualaikum Admin Menuju Haramain,\n\n"
        . "Saya *{$jamaahName}* telah mengunggah bukti transfer pembayaran tabungan umroh:\n"
        . "- *No. Invoice:* {$invoiceNum}\n"
        . "- *Kloter:* {$kloterName}\n"
        . "- *Periode:* {$periodLabel}\n"
        . "- *Nominal:* {$amountFormatted}\n"
        . "- *Bank Tujuan:* {$bankName}\n"
        . "- *Tanggal Transfer:* {$paymentDateFormatted}\n\n"
        . "Bukti transfer telah saya upload di aplikasi.\n"
        . "Alhamdulillah Jazakumullahu Khoiro.";

    $adminPhoneRaw = $adminContact?->phone ? preg_replace('/[^0-9]/', '', $adminContact->phone) : '';
    if ($adminPhoneRaw && str_starts_with($adminPhoneRaw, '0')) {
        $adminPhoneFormatted = '62' . substr($adminPhoneRaw, 1);
    } else {
        $adminPhoneFormatted = $adminPhoneRaw;
    }

    $waAdminReminderUrl = $adminPhoneFormatted ? 'https://wa.me/' . $adminPhoneFormatted . '?text=' . urlencode($waReminderMessage) : null;
    $kloterWaGroupUrl = $invoice->registration?->kloter?->whatsapp_group_url;
@endphp

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header & Back Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <div class="flex items-center space-x-2">
                <span class="text-xs font-black text-slate-500 bg-slate-100 px-2 py-0.5 rounded">{{ $invoice->registration->kloter->code }}</span>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900">{{ $invoice->invoice_number }}</h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Tagihan Periode: <strong>{{ $invoice->period_label }}</strong> &bull; Jatuh Tempo: <strong>{{ $invoice->due_date->format('Y-m-d') }}</strong>
            </p>
        </div>

        <div class="flex items-center space-x-3">
            @if($invoice->isPaid())
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                    &check; Lunas Terverifikasi
                </span>
            @elseif($unpaidPrevInvoice = $invoice->getUnpaidPreviousInvoice())
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300 flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>Terkunci (Bayar {{ $unpaidPrevInvoice->period_label }} Dulu)</span>
                </span>
            @elseif($invoice->hasPendingPayment())
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">
                    &bull; Bukti Menunggu Verifikasi Admin
                </span>
            @else
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-300">
                    Menunggu Pembayaran
                </span>
            @endif
        </div>
    </div>

    <!-- 2 Kolom Layout: Detail Tagihan & Rekening Pembayaran -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Kolom Kiri (2 Kolom): Rincian Invoice & Form Pembayaran -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Rincian Item Tagihan Per Pax -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                <h2 class="text-base font-bold text-slate-900 flex items-center justify-between">
                    <span>Rincian Tagihan Anggota Keluarga</span>
                    <span class="text-xs font-normal text-slate-500">{{ $invoice->items->count() }} Orang Peserta</span>
                </h2>

                <div class="divide-y divide-slate-100">
                    @foreach($invoice->items as $item)
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <span class="block text-xs font-bold text-slate-800">
                                    {{ $item->registrationPax?->familyMember?->full_name ?? 'Peserta' }}
                                </span>
                                <span class="block text-[11px] text-slate-500">
                                    {{ $item->registrationPax?->familyMember?->relationship }} &bull; {{ $item->description }}
                                </span>
                            </div>
                            <span class="text-xs font-extrabold text-slate-900">
                                Rp {{ number_format($item->amount, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="pt-3 border-t-2 border-slate-100 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Total Kewajiban Periode Ini</span>
                        <span class="font-bold text-slate-800">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</span>
                    </div>

                    @if($invoice->carry_over_credit > 0)
                        <div class="flex items-center justify-between text-teal-800 bg-teal-50 px-2.5 py-1.5 rounded-lg border border-teal-200">
                            <span class="flex items-center space-x-1.5">
                                <svg class="w-4 h-4 text-teal-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Kredit Kelebihan Bayar Periode Lalu:</span>
                            </span>
                            <span class="font-bold text-teal-800">- Rp {{ number_format($invoice->carry_over_credit, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    @if($invoice->direct_paid_amount > 0)
                        <div class="flex justify-between text-slate-600">
                            <span>Setoran Periode Ini Disetujui:</span>
                            <span class="font-bold text-slate-800">Rp {{ number_format($invoice->direct_paid_amount, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between text-emerald-700 font-semibold pt-1 border-t border-slate-100">
                        <span>Total Telah Teralokasi (Disetujui):</span>
                        <span class="font-bold">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
                    </div>

                    @if($invoice->surplus_amount > 0)
                        <div class="flex items-center justify-between text-emerald-800 bg-emerald-50 px-2.5 py-1.5 rounded-lg border border-emerald-200">
                            <span class="flex items-center space-x-1.5">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                <span>Kelebihan Saldo (Diteruskan):</span>
                            </span>
                            <span class="font-bold">+ Rp {{ number_format($invoice->surplus_amount, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between text-sm font-black pt-2 border-t border-slate-200">
                        <span class="text-slate-900">Sisa yang Wajib Ditransfer:</span>
                        <span class="{{ $invoice->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                            Rp {{ number_format($invoice->remaining_amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Form Upload Bukti Transfer (Hanya Tampil Jika Belum Lunas) -->
            @if(!$invoice->isPaid())
                @if($invoice->hasPendingPayment())
                    <!-- Banner Reminder WhatsApp Setelah Upload Bukti Pembayaran -->
                    <div class="bg-gradient-to-br from-emerald-50 via-teal-50 to-emerald-100 rounded-2xl border-2 border-emerald-300 shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-start space-x-3.5">
                            <div class="p-3 bg-emerald-600 text-white rounded-2xl shrink-0 mt-0.5 shadow-sm">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                </svg>
                            </div>
                            <div class="flex-1 space-y-2">
                                <div class="flex items-center justify-between flex-wrap gap-2">
                                    <h3 class="text-sm sm:text-base font-extrabold text-emerald-950">Bukti Transfer Sedang Diverifikasi Admin</h3>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-200 text-amber-900 border border-amber-300">
                                        Menunggu Approval
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-emerald-900 leading-relaxed">
                                    Bukti pembayaran Anda telah berhasil kami terima. Untuk mempercepat proses verifikasi, silakan kirim notifikasi/reminder langsung ke WhatsApp Admin atau kabari di grup kloter.
                                </p>

                                <div class="pt-2 flex items-center flex-wrap gap-2.5">
                                    @if($waAdminReminderUrl)
                                        <a href="{{ $waAdminReminderUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md transition-all active:scale-[0.98]">
                                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                            </svg>
                                            <span>Kirim Reminder ke WA Admin</span>
                                        </a>
                                    @endif

                                    @if($kloterWaGroupUrl)
                                        <a href="{{ $kloterWaGroupUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-emerald-50 text-emerald-800 font-bold text-xs border border-emerald-300 shadow-sm transition-all">
                                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                            <span>Kabari di Grup WA Kloter</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($unpaidPrevInvoice = $invoice->getUnpaidPreviousInvoice())
                    <!-- Banner Peringatan Pembayaran Terkunci Karena Tagihan Sebelumnya Belum Lunas -->
                    <div class="bg-amber-50 rounded-2xl border-2 border-amber-300 shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-start space-x-3.5">
                            <div class="p-2.5 bg-amber-200 text-amber-900 rounded-xl shrink-0 mt-0.5">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <div class="flex-1 space-y-2">
                                <h3 class="text-sm sm:text-base font-extrabold text-amber-950">Pembayaran Periode Ini Terkunci</h3>
                                <p class="text-xs sm:text-sm text-amber-900 leading-relaxed">
                                    Sistem tabungan menerapkan aturan pelunasan berurutan kronologis. Anda belum dapat melakukan transfer untuk tagihan periode <strong>{{ $invoice->period_label }}</strong> sebelum tagihan periode <strong>{{ $unpaidPrevInvoice->period_label }}</strong> lunas terverifikasi.
                                </p>
                                <p class="text-xs text-amber-800">
                                    Sisa kewajiban periode {{ $unpaidPrevInvoice->period_label }}: <strong>Rp {{ number_format($unpaidPrevInvoice->remaining_amount, 0, ',', '.') }}</strong>
                                </p>
                                <div class="pt-2">
                                    <a href="{{ route('jamaah.invoices.show', $unpaidPrevInvoice) }}" class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-xl bg-amber-700 hover:bg-amber-800 text-white font-bold text-xs shadow transition-all">
                                        <span>Buka & Lunasi Tagihan {{ $unpaidPrevInvoice->period_label }}</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4"
                    x-data="{
                        previewUrl: null,
                        handleFileChange(event) {
                            const file = event.target.files[0];
                            if (file) {
                                if (file.type.startsWith('image/')) {
                                    this.previewUrl = URL.createObjectURL(file);
                                } else {
                                    this.previewUrl = null;
                                }
                            }
                        }
                    }">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-base font-bold text-slate-900 flex items-center space-x-2">
                            <span class="p-1.5 rounded-lg bg-emerald-100 text-[#346733]">
                                <svg class="w-4 h-4 fill-none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            </span>
                            <span>Kirim Bukti Transfer Manual</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">Unggah foto struk ATM, tangkapan layar m-Banking, atau nota setoran bank.</p>
                    </div>

                    <form action="{{ route('jamaah.payments.store', $invoice) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <!-- Rekening Tujuan -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Ditransfer ke Rekening *</label>
                            <select name="bank_account_id" required class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none bg-white">
                                <option value="">-- Pilih Rekening Tujuan --</option>
                                @foreach($bankAccounts as $bank)
                                    <option value="{{ $bank->id }}" {{ old('bank_account_id') == $bank->id ? 'selected' : '' }}>
                                        {{ $bank->bank_name }} - {{ $bank->account_number }} (a/n {{ $bank->account_holder }})
                                    </option>
                                @endforeach
                            </select>
                            @error('bank_account_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Nominal Aktual (Mode Keuangan) -->
                            <div x-data="{
                                rawAmount: {{ old('amount', (int)$invoice->remaining_amount) }},
                                displayAmount: '{{ number_format(old('amount', (int)$invoice->remaining_amount), 0, ',', '.') }}',
                                formatRupiah(val) {
                                    if (val === null || val === undefined || val === '') return '';
                                    let clean = val.toString().replace(/[^0-9]/g, '');
                                    if (!clean) return '';
                                    let num = parseInt(clean, 10);
                                    return isNaN(num) ? '' : num.toLocaleString('id-ID');
                                },
                                updateRaw(event) {
                                    let clean = event.target.value.replace(/[^0-9]/g, '');
                                    this.rawAmount = clean ? parseInt(clean, 10) : 0;
                                    this.displayAmount = this.formatRupiah(this.rawAmount);
                                    event.target.value = this.displayAmount;
                                },
                                setFull() {
                                    this.rawAmount = {{ (int)$invoice->remaining_amount }};
                                    this.displayAmount = this.formatRupiah(this.rawAmount);
                                }
                            }">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Nominal Ditransfer <span class="text-red-500">*</span></label>
                                    <button type="button" @click="setFull()" class="text-xs text-teal-700 font-bold hover:underline cursor-pointer">
                                        Bayar Pas (Full)
                                    </button>
                                </div>
                                <div class="currency-input-group">
                                    <span class="currency-addon">Rp</span>
                                    <input type="text"
                                           inputmode="numeric"
                                           required
                                           :value="displayAmount"
                                           @input="updateRaw($event)"
                                           placeholder="0"
                                           class="currency-input-field w-full text-base sm:text-lg font-black text-[#346733] tracking-wide placeholder:text-slate-300">
                                    <input type="hidden" name="amount" :value="rawAmount">
                                </div>
                                <div class="flex items-center justify-between text-xs text-slate-500 mt-1.5">
                                    <span>Sisa tagihan: <strong class="text-slate-800">Rp {{ number_format($invoice->remaining_amount, 0, ',', '.') }}</strong></span>
                                </div>
                                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>

                            <!-- Tanggal Transfer -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tanggal Transfer *</label>
                                <input type="date" name="payment_date" required value="{{ old('payment_date', date('Y-m-d')) }}"
                                    class="w-full px-4 py-2.5 sm:py-3 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none">
                                @error('payment_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Bank Pengirim -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Bank Pengirim</label>
                                <input type="text" name="sender_bank" value="{{ old('sender_bank') }}"
                                    placeholder="Contoh: BCA / BSI / Mandiri"
                                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none">
                            </div>

                            <!-- Atas Nama Pengirim -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Pemilik Rekening Pengirim</label>
                                <input type="text" name="sender_account_name" value="{{ old('sender_account_name', auth()->user()->name) }}"
                                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none">
                            </div>
                        </div>

                        <!-- Berkas Bukti Transfer -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">File Foto / Struk Bukti Transfer *</label>
                            <input type="file" name="proof_file" required accept="image/*,application/pdf"
                                @change="handleFileChange($event)"
                                class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#346733] file:text-white hover:file:bg-[#234622]">
                            <p class="text-[11px] text-slate-400 mt-1">Format gambar JPG, PNG, WEBP, atau file PDF (Maksimal 10MB)</p>
                            @error('proof_file')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

                            <!-- Live Image Preview -->
                            <div x-show="previewUrl" x-cloak class="mt-3 p-3 bg-slate-50 border border-slate-200 rounded-xl max-w-xs">
                                <span class="text-[11px] font-bold text-slate-500 block mb-1.5">Preview Bukti Transfer:</span>
                                <img :src="previewUrl" class="w-full h-auto rounded-lg shadow-sm border border-slate-200">
                            </div>
                        </div>

                        <button type="submit" class="w-full py-3 px-4 rounded-xl bg-[#346733] hover:bg-[#234622] text-white font-bold text-xs tracking-wider uppercase shadow-md transition-all">
                            Kirim Bukti Pembayaran
                        </button>
                    </form>
                </div>
                @endif
            @endif

            <!-- Riwayat Pengajuan Pembayaran untuk Tagihan Ini -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                <h2 class="text-base font-bold text-slate-900">Histori Pembayaran Tagihan Ini</h2>

                <div class="divide-y divide-slate-100">
                    @forelse($invoice->payments as $p)
                        <div class="py-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800">{{ $p->payment_date->format('Y-m-d') }}</span>
                                @if($p->isApproved())
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Disetujui</span>
                                @elseif($p->isPending())
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Menunggu Approval Admin</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800">Ditolak</span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600">Nominal: <strong class="text-slate-900">Rp {{ number_format($p->amount, 0, ',', '.') }}</strong></span>
                                <div class="flex items-center gap-3">
                                    @if($p->isPending() && $waAdminReminderUrl)
                                        <a href="{{ $waAdminReminderUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-emerald-700 font-bold hover:underline text-[11px]">
                                            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                            <span>Reminder WA</span>
                                        </a>
                                    @endif
                                    <a href="{{ $p->proof_url }}"
                                       @click.prevent="$dispatch('open-proof-modal', { url: '{{ $p->proof_url }}', title: 'Bukti Transfer #{{ $invoice->invoice_number }}' })"
                                       target="_blank"
                                       class="text-teal-700 font-bold underline text-[11px] cursor-pointer">
                                        Buka File Bukti &rarr;
                                    </a>
                                </div>
                            </div>

                            @if($p->isRejected() && $p->admin_notes)
                                <div class="text-[11px] text-red-700 bg-red-50 p-2.5 rounded-lg border border-red-200">
                                    <strong>Catatan Penolakan:</strong> {{ $p->admin_notes }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 text-center py-4">Belum ada pengajuan pembayaran untuk tagihan ini.</p>
                    @endforelse
                </div>
            </div>

        </div>

        <!-- Kolom Kanan (1 Kolom): Rekening Bank Tujuan Transfer & Link Grup Kloter -->
        <div class="space-y-6">
            @if($kloterWaGroupUrl)
                <!-- Banner Grup WhatsApp Kloter -->
                <div class="bg-white rounded-2xl border-2 border-emerald-300 p-5 shadow-sm space-y-3">
                    <div class="flex items-center space-x-2.5 text-emerald-800">
                        <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Grup WhatsApp Kloter</h3>
                            <p class="text-[11px] text-slate-500">{{ $invoice->registration?->kloter?->name }}</p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Bergabunglah dengan grup WhatsApp kloter untuk berkoordinasi langsung dengan sesama jama'ah dan pembimbing.
                    </p>
                    <a href="{{ $kloterWaGroupUrl }}" target="_blank" rel="noopener noreferrer" class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        <span>Gabung Grup WhatsApp</span>
                    </a>
                </div>
            @endif

            <div class="bg-gradient-to-br from-teal-900 to-[#346733] rounded-2xl p-5 sm:p-6 text-white shadow-md space-y-4">
                <div class="flex items-center space-x-2 text-[#D4AF37]">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M4 10v7h3v-7H4zm6 0v7h3v-7h-3zm6 0v7h3v-7h-3zM2 22h19v-3H2v3zm9.5-20L2 6v2h19V6l-9.5-4z"/></svg>
                    <span class="text-xs font-black uppercase tracking-wider">Rekening Resmi Pembayaran</span>
                </div>

                @if($invoice->registration?->kloter?->bankAccounts->where('is_active', true)->isNotEmpty())
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/15 text-[11px] text-emerald-100 border border-white/20">
                        <svg class="w-3.5 h-3.5 text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span>Rekening Kloter: <strong class="text-white">{{ $invoice->registration->kloter->name }}</strong></span>
                    </div>
                @endif

                <p class="text-xs text-emerald-100">
                    Silakan transfer tepat sesuai nominal sisa tagihan ke salah satu rekening resmi berikut:
                </p>

                <div class="space-y-3">
                    @foreach($bankAccounts as $bank)
                        <div class="p-3.5 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 space-y-1">
                            <span class="text-xs font-bold text-white block">{{ $bank->bank_name }}</span>
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-sm sm:text-base font-extrabold text-[#D4AF37]">{{ $bank->account_number }}</span>
                                <button type="button" @click="navigator.clipboard.writeText('{{ $bank->account_number }}'); alert('Nomor rekening disalin!')"
                                    class="text-[10px] bg-white/20 hover:bg-white/30 px-2 py-0.5 rounded font-semibold text-white transition-colors">
                                    Salin
                                </button>
                            </div>
                            <span class="text-[11px] text-emerald-200 block">a/n {{ $bank->account_holder }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Petunjuk Langkah Pembayaran -->
            <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-3 shadow-sm">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Langkah Pembayaran:</h3>
                <ol class="list-decimal list-inside text-xs text-slate-600 space-y-1.5 leading-relaxed">
                    <li>Salin nomor rekening tujuan di atas.</li>
                    <li>Lakukan transfer melalui ATM / Internet Banking / m-Banking.</li>
                    <li>Simpan bukti struk transfer (screenshot atau foto).</li>
                    <li>Isi formulir di sebelah kiri dan upload berkas bukti struk.</li>
                    <li>Admin Keuangan akan memverifikasi mutasi bank dan mengubah status menjadi <strong>Lunas</strong>.</li>
                </ol>
            </div>
        </div>

    </div>

</div>
@endsection
