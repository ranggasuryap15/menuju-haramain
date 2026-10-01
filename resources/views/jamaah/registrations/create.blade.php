<!--
File: resources/views/jamaah/registrations/create.blade.php
Tujuan: Halaman formulir pendaftaran akun jamaah dan pemilihan anggota keluarga ke kloter umroh tertentu
Dipakai Oleh: Jamaah\KloterRegistrationController@create (GET /jamaah/registrations/create)
Dependensi Utama: layouts.app, Kloter, FamilyMember
Daftar Komponen Utama: Pilihan kloter, Checkbox multi-select anggota keluarga, Kalkulator ringkasan tagihan bulanan
Side Effect: POST ke /jamaah/registrations
-->
@extends('layouts.app')

@section('title', 'Pendaftaran Kloter Umroh')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="pb-2 border-b border-slate-200">
        <h1 class="text-xl sm:text-2xl font-black text-slate-900">Daftar Kloter Umroh</h1>
        <p class="text-xs sm:text-sm text-slate-500">
            Pilih paket kloter yang diinginkan dan tentukan siapa saja anggota keluarga yang akan diberangkatkan.
        </p>
    </div>

    @if($familyMembers->isEmpty())
        <div class="bg-amber-50 border border-amber-300 rounded-2xl p-6 text-center space-y-3">
            <p class="text-sm font-bold text-amber-900">Anda belum mendaftarkan data anggota keluarga.</p>
            <p class="text-xs text-amber-800">Sebelum memilih kloter, tambahkan data calon peserta terlebih dahulu (minimal 1 orang).</p>
            <a href="{{ route('jamaah.family.index') }}" class="inline-block px-4 py-2 rounded-xl bg-[#346733] text-white font-bold text-xs shadow">
                + Tambah Anggota Keluarga Sekarang
            </a>
        </div>
    @else
        <form action="{{ route('jamaah.registrations.store') }}" method="POST" class="space-y-6"
            x-data="{
                selectedKloterMonthly: 0,
                selectedKloterTarget: 0,
                selectedPaxCount: 0,
                updateCounts() {
                    let checkboxes = document.querySelectorAll('input[name=\'family_member_ids[]\']:checked');
                    this.selectedPaxCount = checkboxes.length;
                }
            }">
            @csrf

            <!-- Langkah 1: Pilih Kloter -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <span class="text-[11px] font-bold text-[#007C6A] uppercase tracking-wider">Langkah 1</span>
                    <h2 class="text-base font-bold text-slate-900">Pilih Paket Kloter</h2>
                    <p class="text-xs text-slate-500">Tiap kloter memiliki periode tabungan, nominal bulanan, dan tanggal target berbeda.</p>
                </div>

                <div class="space-y-3">
                    @forelse($kloters as $k)
                        @php
                            $isRegistered = in_array($k->id, $registeredKloterIds, true);
                        @endphp
                        <label class="block p-4 rounded-xl border-2 transition-all cursor-pointer {{ $isRegistered ? 'opacity-50 border-slate-200 bg-slate-50 cursor-not-allowed' : 'border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/20' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start space-x-3">
                                    <input type="radio" name="kloter_id" value="{{ $k->id }}" required
                                        {{ $isRegistered ? 'disabled' : '' }}
                                        @click="selectedKloterMonthly = {{ $k->monthly_per_pax }}; selectedKloterTarget = {{ $k->target_per_pax }}"
                                        class="mt-1 w-4 h-4 text-[#346733] focus:ring-[#346733] border-slate-300">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs font-black text-slate-500 bg-slate-100 px-2 py-0.5 rounded">{{ $k->code }}</span>
                                            <h3 class="text-sm font-bold text-slate-900">{{ $k->name }}</h3>
                                            @if($isRegistered)
                                                <span class="text-[10px] font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded">Sudah Terdaftar</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-500 mt-1">{{ $k->description }}</p>
                                        <div class="text-[11px] text-slate-600 mt-2 flex flex-wrap gap-x-4 gap-y-1">
                                            <span>Periode: <strong>{{ $k->start_date->format('d M Y') }} s/d {{ $k->end_date->format('d M Y') }}</strong></span>
                                            <span>Durasi: <strong>{{ $k->duration_months }} Bulan</strong></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="block text-[11px] text-slate-500">Iuran / Pax / Bulan</span>
                                    <span class="text-sm sm:text-base font-extrabold text-[#346733]">
                                        Rp {{ number_format($k->monthly_per_pax, 0, ',', '.') }}
                                    </span>
                                    <span class="block text-[10px] text-slate-400">Target: Rp {{ number_format($k->target_per_pax, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </label>
                    @empty
                        <p class="text-xs text-slate-500 text-center py-4">Belum ada kloter aktif yang dibuka.</p>
                    @endforelse
                </div>
            </div>

            <!-- Langkah 2: Pilih Anggota Keluarga yang Didaftarkan -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
                <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-bold text-[#007C6A] uppercase tracking-wider">Langkah 2</span>
                        <h2 class="text-base font-bold text-slate-900">Pilih Anggota Keluarga yang Berangkat</h2>
                        <p class="text-xs text-slate-500">Centang minimal 1 orang anggota keluarga yang didaftarkan pada kloter ini</p>
                    </div>
                    <a href="{{ route('jamaah.family.index') }}" class="text-xs font-bold text-[#007C6A] hover:underline">
                        + Tambah Anggota
                    </a>
                </div>

                <div class="space-y-2.5">
                    @foreach($familyMembers as $m)
                        <label class="flex items-center justify-between p-3.5 rounded-xl border border-slate-200 hover:border-emerald-300 hover:bg-slate-50 cursor-pointer transition-colors">
                            <div class="flex items-center space-x-3">
                                <input type="checkbox" name="family_member_ids[]" value="{{ $m->id }}"
                                    @change="updateCounts()"
                                    class="w-4 h-4 text-[#346733] rounded focus:ring-[#346733] border-slate-300">
                                <div>
                                    <span class="block text-sm font-bold text-slate-800">{{ $m->full_name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $m->relationship }} &bull; NIK: {{ $m->identity_number ?: '-' }}</span>
                                </div>
                            </div>
                            <span class="text-xs font-semibold text-slate-400">Calon Jama'ah</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Ringkasan Tagihan Akumulatif Akun -->
            <div class="bg-gradient-to-br from-[#f0fdf4] to-[#B0E0E5]/20 rounded-2xl border-2 border-emerald-300 p-5 sm:p-6 space-y-3">
                <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#346733]"></span>
                    <span>Ringkasan Estimasi Tagihan Akun Anda</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                    <div class="bg-white p-3 rounded-xl border border-emerald-100 shadow-sm">
                        <span class="block text-[11px] text-slate-500">Jumlah Orang</span>
                        <span class="text-lg font-black text-slate-900" x-text="selectedPaxCount + ' Orang'">0 Orang</span>
                    </div>

                    <div class="bg-white p-3 rounded-xl border border-emerald-100 shadow-sm">
                        <span class="block text-[11px] text-slate-500">Tagihan Terbit Tiap Tanggal 1</span>
                        <span class="text-lg font-black text-[#346733]" x-text="'Rp ' + (selectedPaxCount * selectedKloterMonthly).toLocaleString('id-ID')">Rp 0</span>
                    </div>

                    <div class="bg-white p-3 rounded-xl border border-emerald-100 shadow-sm">
                        <span class="block text-[11px] text-slate-500">Total Akumulasi Target</span>
                        <span class="text-lg font-black text-[#007C6A]" x-text="'Rp ' + (selectedPaxCount * selectedKloterTarget).toLocaleString('id-ID')">Rp 0</span>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500 pt-1">
                    * Tagihan bulanan akan otomatis terbit setiap tanggal 1 dalam rentang periode kloter yang dipilih.
                </p>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end space-x-3">
                <a href="{{ route('jamaah.dashboard') }}" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 transition-colors">
                    Batal
                </a>
                <button type="submit" class="py-2.5 px-6 rounded-xl bg-[#346733] hover:bg-[#234622] text-white font-bold text-xs tracking-wide shadow-md transition-all">
                    Konfirmasi & Daftarkan Kloter
                </button>
            </div>
        </form>
    @endif

</div>
@endsection

