{{--
/**
 * File: resources/views/admin/invoices/show.blade.php
 * Tujuan: Menampilkan detail spesifik tagihan bulanan jama'ah bagi Superadmin & Admin Keuangan, termasuk rincian item per pax keluarga, rekonsiliasi pembayaran yang masuk, dan verifikasi status pelunasan
 * Dipakai Oleh: App\Http\Controllers\Admin\InvoiceController@show (GET /admin/invoices/{invoice})
 * Dependensi Utama: Tailwind CSS CDN, Alpine.js, App\Models\Invoice, App\Models\Payment
 * Daftar Komponen Utama: Breadcrumb, Kartu ringkasan tagihan & status, Informasi kepala keluarga & kloter, Tabel rincian biaya per jiwa (items), Riwayat setoran pembayaran (payments) dengan viewer bukti transfer
 * Side Effect: Tampilan detail tagihan dan navigasi ke verifikasi pembayaran
 */
--}}
@extends('layouts.app')

@section('title', 'Detail Tagihan - ' . $invoice->invoice_number)

@section('content')
<div class="space-y-6">

    <!-- Header & Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-[#346733]">Dashboard</a>
                <span>/</span>
                <a href="{{ route('admin.invoices.index') }}" class="hover:text-[#346733]">Tagihan Jama'ah</a>
                <span>/</span>
                <span class="text-slate-700 font-semibold">{{ $invoice->invoice_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-xl sm:text-2xl font-black text-slate-900">{{ $invoice->invoice_number }}</h1>
                @if($invoice->isPaid())
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Lunas</span>
                @elseif($invoice->status === 'partially_paid')
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">Bayar Sebagian</span>
                @elseif($invoice->due_date && $invoice->due_date->isPast())
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800">Jatuh Tempo</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Belum Bayar</span>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Periode Tabungan: <strong class="text-slate-800">{{ $invoice->period_label }}</strong> &bull; Terbit: {{ $invoice->billing_date->format('Y-m-d') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.invoices.index') }}" class="px-4 py-2 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                &larr; Kembali ke Daftar
            </a>
        </div>
    </div>

    <!-- 1. Ringkasan Finansial Tagihan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-xs">
            <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block mb-1">Total Tagihan</span>
            <span class="text-xl font-black text-slate-900">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</span>
            <span class="text-[11px] text-slate-400 block mt-1">Sesuai jumlah pax terdaftar</span>
        </div>

        <div class="p-5 bg-white rounded-2xl border border-emerald-200 shadow-xs">
            <span class="text-xs text-emerald-700 font-bold uppercase tracking-wider block mb-1">Sudah Dibayar</span>
            <span class="text-xl font-black text-emerald-700">Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
            <span class="text-[11px] text-slate-400 block mt-1">Pembayaran yang terverifikasi</span>
        </div>

        <div class="p-5 bg-white rounded-2xl border border-red-200 shadow-xs">
            <span class="text-xs text-red-600 font-bold uppercase tracking-wider block mb-1">Sisa Pembayaran</span>
            <span class="text-xl font-black {{ $invoice->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                Rp {{ number_format($invoice->remaining_amount, 0, ',', '.') }}
            </span>
            <span class="text-[11px] text-slate-400 block mt-1">{{ $invoice->remaining_amount > 0 ? 'Kewajiban pelunasan' : 'Tagihan telah lunas' }}</span>
        </div>

        <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-xs">
            <span class="text-xs text-slate-500 font-bold uppercase tracking-wider block mb-1">Batas Jatuh Tempo</span>
            <span class="text-xl font-black text-slate-900">{{ $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '-' }}</span>
            <span class="text-[11px] {{ $invoice->due_date && $invoice->due_date->isPast() && !$invoice->isPaid() ? 'text-red-600 font-bold' : 'text-slate-400' }} block mt-1">
                {{ $invoice->due_date && $invoice->due_date->isPast() && !$invoice->isPaid() ? 'Telah melewati batas tempo' : 'Tanggal 10 bulan berjalan' }}
            </span>
        </div>
    </div>

    <!-- 2. Grid Rincian Jamaah & Rekening Kloter -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Data Jama'ah Pendaftar -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Data Jama'ah Pendaftar</h2>
                @if($invoice->registration?->user?->phone && $invoice->remaining_amount > 0)
                    @php
                        $phone = $invoice->registration->user->phone;
                        $waPhone = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $phone));
                    @endphp
                    <a href="https://wa.me/{{ $waPhone }}?text={{ urlencode('Assalamu\'alaikum Bapak/Ibu ' . ($invoice->registration->user->name ?? 'Jamaah') . ', kami dari pengurus Tabungan Umroh Menuju Haramain menginfokan tagihan periode ' . $invoice->period_label . ' sebesar Rp ' . number_format($invoice->remaining_amount, 0, ',', '.') . ' pada ' . ($invoice->registration->kloter->name ?? 'Kloter Umroh') . '. Mohon kesediaannya untuk melakukan pembayaran. Alhamdulillah Jazakumullahu Khoiro.') }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl border border-emerald-200 transition">
                        <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.299.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.073.043.42-.101.825z"/></svg>
                        <span>Hubungi via WA</span>
                    </a>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="text-slate-400 block">Nama Jama'ah:</span>
                    <span class="font-bold text-slate-900 text-sm">{{ $invoice->registration?->user?->name }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block">Email:</span>
                    <span class="font-semibold text-slate-800">{{ $invoice->registration?->user?->email }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block">No. Telepon / WA:</span>
                    <span class="font-semibold text-slate-800">{{ $invoice->registration?->user?->phone ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-slate-400 block">Kloter:</span>
                    <a href="{{ route('admin.kloters.show', $invoice->registration?->kloter_id) }}" class="font-bold text-[#346733] hover:underline">
                        {{ $invoice->registration?->kloter?->name }} ({{ $invoice->registration?->kloter?->code }})
                    </a>
                </div>
            </div>
        </div>

        <!-- Rekening Bank Penampung Kloter -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3">
                Rekening Tujuan Pembayaran
            </h2>
            <div class="space-y-2">
                @forelse($invoice->registration?->kloter?->bankAccounts ?? [] as $bank)
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-900 text-xs block">{{ $bank->bank_name }}</span>
                            <span class="text-xs text-slate-600 font-mono">{{ $bank->account_number }}</span>
                            <span class="text-[11px] text-slate-400 block">a/n {{ $bank->account_holder }}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Aktif</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 py-2">Belum ada rekening khusus yang ditautkan ke kloter ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 3. Rincian Tagihan per Jiwa (Invoice Items) -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                Rincian Jiwa / Anggota Keluarga yang Ditagihkan ({{ $invoice->items->count() }} Orang)
            </h2>
            <span class="text-xs font-bold text-[#346733]">
                Tarif per Pax: Rp {{ number_format($invoice->registration?->kloter?->monthly_per_pax, 0, ',', '.') }}/bln
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">Nama Anggota Keluarga</th>
                        <th class="py-3 px-4">Hubungan</th>
                        <th class="py-3 px-4">Deskripsi Tagihan</th>
                        <th class="py-3 px-4 text-right">Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($invoice->items as $index => $item)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-4 text-slate-400 font-mono">{{ $index + 1 }}</td>
                            <td class="py-3 px-4 font-bold text-slate-900">
                                {{ $item->registrationPax?->familyMember?->full_name ?? 'Peserta' }}
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $item->registrationPax?->familyMember?->relationship ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $item->description }}
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-slate-900">
                                Rp {{ number_format($item->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-400 text-xs">
                                Tidak ada rincian item.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-50 font-bold border-t border-slate-200">
                    <tr>
                        <td colspan="4" class="py-3 px-4 text-right text-slate-700 uppercase">Total Tagihan:</td>
                        <td class="py-3 px-4 text-right text-base text-slate-900 font-black">
                            Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- 4. Riwayat Setoran / Pembayaran Masuk untuk Tagihan Ini -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider">
                Riwayat Pembayaran Masuk ({{ $invoice->payments->count() }} Pembayaran)
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">No</th>
                        <th class="py-3 px-4">Tgl Bayar & Input</th>
                        <th class="py-3 px-4">Nominal Masuk</th>
                        <th class="py-3 px-4">Bank Tujuan</th>
                        <th class="py-3 px-4">Pengirim</th>
                        <th class="py-3 px-4 text-center">Bukti Transfer</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($invoice->payments as $index => $payment)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-4 text-slate-400 font-mono">{{ $index + 1 }}</td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-900 block">
                                    {{ $payment->payment_date ? $payment->payment_date->format('Y-m-d') : '-' }}
                                </span>
                                <span class="text-[11px] text-slate-400">Input: {{ $payment->created_at->format('Y-m-d H:i') }}</span>
                            </td>
                            <td class="py-3 px-4 font-bold text-sm text-[#346733]">
                                Rp {{ number_format($payment->amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-slate-700">
                                {{ $payment->bankAccount?->bank_name ?? 'Bank Kas' }}
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $payment->sender_bank }} a/n {{ $payment->sender_account_name }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button type="button"
                                        @click.prevent="$dispatch('open-proof-modal', { url: '{{ $payment->proof_url }}', title: 'Bukti Transfer #{{ $payment->id }} - {{ $payment->user->name }}' })"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-teal-700 bg-teal-50 border border-teal-200 hover:bg-teal-100 transition cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Lihat Bukti</span>
                                </button>
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if($payment->isApproved())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        Approved
                                    </span>
                                    @if($payment->verifier)
                                        <span class="text-[10px] text-slate-400 block mt-0.5">Oleh: {{ $payment->verifier->name }}</span>
                                    @endif
                                @elseif($payment->isPending())
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                        Pending
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800">
                                        Rejected
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="{{ route('admin.payments.show', $payment) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition shadow-xs">
                                    Periksa
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400 text-xs">
                                Belum ada bukti pembayaran yang diunggah untuk tagihan ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
