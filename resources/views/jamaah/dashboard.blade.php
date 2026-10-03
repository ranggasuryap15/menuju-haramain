{{--
/**
 * File: resources/views/jamaah/dashboard.blade.php
 * Tujuan: Halaman dasbor jamaah untuk melihat progres tabungan keluarga, tagihan bulan berjalan, status kloter (termasuk info pendaftaran susulan / late joiner, link WhatsApp grup kloter), dan riwayat pembayaran
 * Dipakai Oleh: Jamaah\DashboardController@index (GET /jamaah/dashboard)
 * Dependensi Utama: layouts.app, KloterRegistration, Invoice, Payment, Kloter
 * Daftar Komponen Utama: Kartu Progres Hijau-Emas, Antrean Tagihan Belum Terbayar, Kartu Kloter (Info Late Joiner, Tautan Grup WA), Ringkasan Pax Keluarga, Riwayat Mutasi Pembayaran
 * Side Effect: Render tampilan dashboard jamaah
 */
--}}
@extends('layouts.app')

@section('title', 'Dasbor Tabungan Jama\'ah')

@section('content')
<div class="space-y-6">

    <!-- Top Hero Banner: Progres Tabungan Keluarga -->
    <div class="bg-gradient-to-br from-[#346733] via-[#2a5529] to-[#007C6A] rounded-2xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-44 h-44 rounded-full bg-[#D4AF37]/15 pointer-events-none blur-xl"></div>
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            
            <div class="space-y-2">
                <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 text-xs font-semibold text-[#D4AF37]">
                    <span class="w-2 h-2 rounded-full bg-[#D4AF37]"></span>
                    <span>Bismillah Menuju Baitullah</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Assalamu'alaikum, {{ $user->name }}</h1>
                <p class="text-emerald-100 text-sm max-w-xl">
                    Pantau target tabungan, anggota keluarga yang diberangkatkan, serta jadwal pembayaran otomatis setiap tanggal 1.
                </p>
            </div>

            <!-- Progres Box -->
            <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-5 min-w-[280px] sm:min-w-[320px] space-y-3 shadow-inner">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-emerald-100 uppercase tracking-wider font-semibold">Progres Akumulasi</span>
                    <span class="text-[#D4AF37] font-black text-sm">{{ $progressPercentage }}%</span>
                </div>
                
                <!-- Progress Bar -->
                <div class="w-full bg-black/20 rounded-full h-3 overflow-hidden p-0.5">
                    <div class="bg-gradient-to-r from-amber-300 via-[#D4AF37] to-amber-200 h-full rounded-full transition-all duration-700 shadow-sm" style="width: {{ $progressPercentage }}%"></div>
                </div>

                <div class="flex items-baseline justify-between pt-1">
                    <div>
                        <span class="block text-[11px] text-emerald-100">Terkumpul</span>
                        <span class="text-base sm:text-lg font-extrabold text-white">Rp {{ number_format($totalSaved, 0, ',', '.') }}</span>
                    </div>
                    <div class="text-right">
                        <span class="block text-[11px] text-emerald-100">Total Target</span>
                        <span class="text-xs sm:text-sm font-semibold text-emerald-200">Rp {{ number_format($totalTarget, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Peringatan Tagihan Bulan Berjalan Jika Belum Dibayar -->
    @if($unpaidInvoices->isNotEmpty())
        <div class="bg-amber-50 border-2 border-amber-300 rounded-2xl p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2.5">
                    <span class="p-2 rounded-xl bg-amber-200 text-amber-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-amber-950">Tagihan Menunggu Pembayaran</h2>
                        <p class="text-xs text-amber-800">Terdapat {{ $unpaidInvoices->count() }} tagihan yang belum lunas. Silakan transfer dan unggah bukti transfer manual.</p>
                    </div>
                </div>
            </div>

            <!-- List Tagihan -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                @foreach($unpaidInvoices as $inv)
                    <div class="bg-white rounded-xl p-4 border border-amber-200 shadow-sm flex flex-col justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[#346733]">{{ $inv->invoice_number }}</span>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $inv->status === 'partially_paid' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $inv->status === 'partially_paid' ? 'Dibayar Sebagian' : 'Belum Dibayar' }}
                                </span>
                            </div>
                            <h3 class="text-sm font-bold text-slate-900">Periode {{ $inv->period_label }}</h3>
                            <p class="text-xs text-slate-500">Kloter: {{ $inv->registration->kloter->name }} ({{ $inv->items->count() }} Orang)</p>
                            <div class="pt-2 text-sm font-black text-slate-900">
                                Sisa Tagihan: <span class="text-red-600">Rp {{ number_format($inv->remaining_amount, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[11px] text-slate-500">Jatuh tempo: {{ $inv->due_date->format('Y-m-d') }}</span>
                            <a href="{{ route('jamaah.invoices.show', $inv) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold shadow-sm transition-colors">
                                Bayar & Upload Bukti &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- 2 Kolom Layout: Kloter Terdaftar & Anggota Keluarga -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Kolom Kiri (2 Kolom): Kloter Terdaftar -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Kloter Umroh yang Diikuti</h2>
                        <p class="text-xs text-slate-500">Paket, kuota orang, dan status partisipasi</p>
                    </div>
                    <a href="{{ route('jamaah.registrations.create') }}" class="px-3 py-1.5 rounded-xl bg-teal-50 hover:bg-teal-100 text-[#007C6A] border border-teal-200 text-xs font-bold transition-colors">
                        + Daftar Kloter Lain
                    </a>
                </div>

                <div class="p-5 space-y-4">
                    @forelse($registrations as $reg)
                        <div class="rounded-xl border border-slate-200 p-4 hover:border-emerald-300 transition-all bg-slate-50/50 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-slate-200 text-slate-700">{{ $reg->kloter->code }}</span>
                                        @if($reg->isPending())
                                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">
                                                Menunggu Approval Admin
                                            </span>
                                        @elseif($reg->isActive())
                                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                Aktif
                                            </span>
                                        @elseif($reg->isRejected())
                                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800 border border-red-300">
                                                Ditolak
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                                {{ $reg->status }}
                                            </span>
                                        @endif
                                    </div>
                                    <h3 class="text-base font-bold text-slate-900 mt-1">{{ $reg->kloter->name }}</h3>
                                    <p class="text-xs text-slate-500">
                                        Periode: {{ $reg->kloter->start_date->format('Y-m-d') }} s/d {{ $reg->kloter->end_date->format('Y-m-d') }}
                                        ({{ $reg->kloter->duration_months }} Bulan)
                                    </p>
                                </div>
                                <div class="text-left sm:text-right">
                                    <span class="block text-xs text-slate-500">Tagihan Bulanan Akun:</span>
                                    <span class="text-base font-extrabold text-[#346733]">
                                        Rp {{ number_format($reg->kloter->monthly_per_pax * $reg->active_pax_count, 0, ',', '.') }}
                                    </span>
                                    <span class="block text-[11px] text-slate-400">({{ $reg->active_pax_count }} Orang x Rp {{ number_format($reg->kloter->monthly_per_pax, 0, ',', '.') }})</span>
                                </div>
                            </div>

                            @if($reg->isPending())
                                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800 flex items-center space-x-2">
                                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Pendaftaran kloter sedang menunggu persetujuan (approval) Administrator. Tagihan bulanan akan mulai diterbitkan setelah disetujui.</span>
                                </div>
                            @elseif($reg->isRejected())
                                <div class="p-3 bg-red-50 rounded-xl border border-red-200 text-xs text-red-800 space-y-1">
                                    <div class="flex items-center space-x-2 font-bold">
                                        <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span>Pendaftaran kloter ditolak oleh Admin.</span>
                                    </div>
                                    @if($reg->admin_notes)
                                        <p class="text-[11px] text-red-700 pl-6">Alasan: "{{ $reg->admin_notes }}"</p>
                                    @endif
                                </div>
                            @endif

                            @if($reg->isActive() && $reg->isLateJoiner())
                                <div class="p-3 bg-blue-50/80 rounded-xl border border-blue-200 text-xs text-blue-900 space-y-1">
                                    <div class="flex items-center space-x-2 font-bold text-blue-950">
                                        <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>Pendaftaran Susulan (Bergabung di Tengah Periode Kloter)</span>
                                    </div>
                                    <p class="text-[11px] text-blue-800 leading-relaxed">
                                        Anda bergabung setelah kloter berjalan (terlewat {{ $reg->getMissedInitialMonthsCount() }} bulan awal). Tagihan bulanan reguler dimulai sejak bulan Anda bergabung. Kekurangan biaya periode awal sebesar <strong>Rp {{ number_format($reg->getMissedInitialAmount(), 0, ',', '.') }}</strong> akan ditagihkan sekaligus sebagai pelunasan di bulan akhir kloter sebelum keberangkatan.
                                    </p>
                                </div>
                            @endif

                            <!-- List Peserta di Kloter Ini -->
                            <div class="pt-2 border-t border-slate-200">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block mb-1.5">Peserta Diberangkatkan ({{ $reg->paxes->count() }} Orang):</span>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($reg->paxes as $pax)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-xs font-medium text-slate-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#007C6A] mr-1.5"></span>
                                            {{ $pax->familyMember?->full_name }}
                                            <span class="text-[10px] text-slate-400 ml-1">({{ $pax->familyMember?->relationship }})</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            @if($reg->isActive() && $reg->kloter->whatsapp_group_url)
                                <div class="pt-2 border-t border-slate-200/80 flex items-center justify-between flex-wrap gap-2">
                                    <div class="flex items-center space-x-1.5 text-xs text-slate-600">
                                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                        <span class="font-medium">Grup Koordinasi WhatsApp Kloter</span>
                                    </div>
                                    <a href="{{ $reg->kloter->whatsapp_group_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-all">
                                        <span>Gabung Grup WA</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <p class="text-sm text-slate-500">Anda belum mendaftar pada kloter manapun.</p>
                            <a href="{{ route('jamaah.registrations.create') }}" class="mt-2 inline-block px-4 py-2 rounded-xl bg-[#346733] text-white text-xs font-bold shadow">
                                Pilih Kloter Sekarang
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Riwayat Pembayaran Terbaru -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Riwayat Pembayaran Terbaru</h2>
                        <p class="text-xs text-slate-500">Histori transfer dan status verifikasi admin keuangan</p>
                    </div>
                    <a href="{{ route('jamaah.invoices.index') }}" class="text-xs font-bold text-[#346733] hover:underline">
                        Lihat Semua Tagihan &rarr;
                    </a>
                </div>

                <!-- Desktop Table & Mobile Cards -->
                <div class="overflow-x-auto">
                    <!-- Mobile Card Format (Stacked) -->
                    <div class="sm:hidden divide-y divide-slate-100 p-4 space-y-3">
                        @forelse($recentPayments as $p)
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-700">{{ $p->payment_date->format('Y-m-d') }}</span>
                                    @if($p->isApproved())
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Disetujui</span>
                                    @elseif($p->isPending())
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Menunggu Approval</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800">Ditolak</span>
                                    @endif
                                </div>
                                <div class="flex justify-between items-baseline">
                                    <span class="text-xs text-slate-500">Nominal:</span>
                                    <span class="text-sm font-extrabold text-slate-900">Rp {{ number_format($p->amount, 0, ',', '.') }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500 flex justify-between">
                                    <span>Tujuan: {{ $p->bankAccount?->bank_name }}</span>
                                    <a href="{{ $p->proof_url }}" 
                                       @click.prevent="$dispatch('open-proof-modal', { url: '{{ $p->proof_url }}', title: 'Bukti Transfer #{{ $p->invoice?->invoice_number }}' })"
                                       target="_blank" 
                                       class="text-teal-700 font-bold underline cursor-pointer">Lihat Bukti</a>
                                </div>
                                @if($p->admin_notes)
                                    <div class="text-[11px] text-red-600 bg-red-50 p-2 rounded border border-red-200">
                                        Alasan: {{ $p->admin_notes }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-500 text-center py-4">Belum ada riwayat pembayaran.</p>
                        @endforelse
                    </div>

                    <!-- Desktop Table -->
                    <table class="w-full text-left text-xs hidden sm:table">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Tagihan</th>
                                <th class="py-3 px-4">Bank Tujuan</th>
                                <th class="py-3 px-4">Nominal</th>
                                <th class="py-3 px-4">Bukti</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($recentPayments as $p)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-semibold text-slate-800">{{ $p->payment_date->format('Y-m-d') }}</td>
                                    <td class="py-3 px-4 text-slate-600">{{ $p->invoice?->invoice_number }}</td>
                                    <td class="py-3 px-4 text-slate-600">{{ $p->bankAccount?->bank_name }}</td>
                                    <td class="py-3 px-4 font-bold text-slate-900">Rp {{ number_format($p->amount, 0, ',', '.') }}</td>
                                    <td class="py-3 px-4">
                                        <a href="{{ $p->proof_url }}" 
                                           @click.prevent="$dispatch('open-proof-modal', { url: '{{ $p->proof_url }}', title: 'Bukti Transfer #{{ $p->invoice?->invoice_number }}' })"
                                           target="_blank" 
                                           class="text-teal-700 hover:text-teal-900 font-semibold underline cursor-pointer">
                                            Buka Bukti
                                        </a>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        @if($p->isApproved())
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Disetujui</span>
                                        @elseif($p->isPending())
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Menunggu Approval</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800" title="{{ $p->admin_notes }}">Ditolak</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-6 text-center text-slate-400">Belum ada riwayat pembayaran yang dikirim.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan (1 Kolom): Data Anggota Keluarga di Akun -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Anggota Keluarga</h2>
                        <p class="text-xs text-slate-500">Orang di dalam akun Anda</p>
                    </div>
                    <a href="{{ route('jamaah.family.index') }}" class="text-xs font-bold text-[#007C6A] hover:underline">
                        Kelola
                    </a>
                </div>

                <div class="p-5 space-y-3">
                    @forelse($user->familyMembers as $m)
                        <div class="p-3 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between">
                            <div>
                                <span class="block text-sm font-bold text-slate-800">{{ $m->full_name }}</span>
                                <span class="block text-[11px] text-slate-500">{{ $m->relationship }} &bull; {{ $m->identity_number ?: 'NIK belum diisi' }}</span>
                            </div>
                            <span class="w-8 h-8 rounded-full bg-emerald-100 text-[#346733] font-bold text-xs flex items-center justify-center">
                                {{ substr($m->relationship, 0, 1) }}
                            </span>
                        </div>
                    @empty
                        <div class="text-center py-6 text-slate-400 text-xs">
                            Belum ada anggota keluarga ditambahkan.
                        </div>
                    @endforelse

                    <a href="{{ route('jamaah.family.index') }}" class="mt-3 block w-full py-2.5 px-3 text-center rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                        + Tambah Istri / Anak / Anggota
                    </a>
                </div>
            </div>

            <!-- Kartu Edukasi Alur Tabungan -->
            <div class="bg-gradient-to-br from-teal-50 to-[#B0E0E5]/30 rounded-2xl border border-teal-200 p-5 space-y-2 text-xs text-teal-950">
                <h3 class="font-extrabold text-sm text-[#007C6A] flex items-center space-x-1.5">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    <span>Ketentuan Penagihan</span>
                </h3>
                <ul class="list-disc list-inside space-y-1 text-slate-700 text-[11px] leading-relaxed">
                    <li>Tagihan terbit otomatis setiap <strong>tanggal 1</strong> setiap bulan.</li>
                    <li>Satu tagihan mencakup seluruh anggota keluarga yang berangkat.</li>
                    <li>Wajib transfer dan upload bukti struk sebelum batas jatuh tempo.</li>
                    <li>Admin Keuangan akan memverifikasi dalam waktu 1x24 jam kerja.</li>
                </ul>
            </div>
        </div>

    </div>

</div>
@endsection
