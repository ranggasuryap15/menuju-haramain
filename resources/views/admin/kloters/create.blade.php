{{--
/**
 * File: resources/views/admin/kloters/create.blade.php
 * Tujuan: Formulir pembuatan paket master kloter umroh baru oleh admin
 * Dipakai Oleh: App\Http\Controllers\Admin\KloterController@create
 * Dependensi Utama: Tailwind CSS CDN, Alpine.js, App\Models\Kloter
 * Daftar Komponen Utama: Input nama, kode, target tabungan/pax, cicilan/bln/pax, start_date, end_date, estimasi durasi bulan dinamis
 * Side Effect: POST form ke admin.kloters.store
 */
--}}
@extends('layouts.app')

@section('title', 'Tambah Kloter Baru')

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="{
    startDate: '{{ old('start_date', date('Y-m-d')) }}',
    endDate: '{{ old('end_date', date('Y-m-d', strtotime('+10 months'))) }}',
    targetPax: {{ old('target_per_pax', 35000000) }},
    monthlyPax: {{ old('monthly_per_pax', 3500000) }},
    formatRupiah(val) {
        if (!val) return '';
        let num = val.toString().replace(/[^0-9]/g, '');
        return num ? parseInt(num, 10).toLocaleString('id-ID') : '';
    },
    get estimatedMonths() {
        if (!this.startDate || !this.endDate) return 0;
        const d1 = new Date(this.startDate);
        const d2 = new Date(this.endDate);
        const months = (d2.getFullYear() - d1.getFullYear()) * 12 + (d2.getMonth() - d1.getMonth());
        return months > 0 ? months : 0;
    },
    autoCalculateMonthly() {
        if (this.estimatedMonths > 0 && this.targetPax > 0) {
            this.monthlyPax = Math.ceil(this.targetPax / this.estimatedMonths);
        }
    }
}">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <nav class="flex items-center space-x-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('admin.kloters.index') }}" class="hover:text-haramain-green flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Daftar Kloter
                </a>
                <span>/</span>
                <span class="text-gray-700 font-medium">Buat Baru</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Tambah Paket Kloter Umroh</h1>
            <p class="text-sm text-gray-500 mt-0.5">Tentukan nama, kode, estimasi durasi menabung, dan tarif per jamaah.</p>
        </div>
    </div>

    <!-- Form Container -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <form action="{{ route('admin.kloters.store') }}" method="POST" class="p-6 sm:p-8 space-y-6">
            @csrf

            <!-- Baris 1: Nama & Kode -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nama Kloter Umroh <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Kloter Ramadhan Berkah 1448 H" class="w-full text-sm rounded-xl border-slate-300 shadow-sm focus:border-haramain-green focus:ring-haramain-green px-4 py-2.5" required>
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Kode Unik Kloter <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code') }}" placeholder="KLTR-2027-01" class="w-full text-sm font-mono uppercase rounded-xl border-slate-300 shadow-sm focus:border-haramain-green focus:ring-haramain-green px-4 py-2.5" required>
                    @error('code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Baris 2: Periode Waktu -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 sm:p-5 bg-gray-50 rounded-2xl border border-gray-200">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Tanggal Mulai Menabung (Start Date) <span class="text-red-500">*</span></label>
                    <input type="date" name="start_date" x-model="startDate" @change="autoCalculateMonthly()" class="w-full text-sm rounded-xl border-slate-300 shadow-sm focus:border-haramain-green focus:ring-haramain-green px-4 py-2.5" required>
                    @error('start_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Tanggal Keberangkatan / Selesai (End Date) <span class="text-red-500">*</span></label>
                    <input type="date" name="end_date" x-model="endDate" @change="autoCalculateMonthly()" class="w-full text-sm rounded-xl border-slate-300 shadow-sm focus:border-haramain-green focus:ring-haramain-green px-4 py-2.5" required>
                    @error('end_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 flex items-center justify-between text-xs text-gray-600 pt-2 border-t border-gray-200">
                    <span>Estimasi Durasi Periode:</span>
                    <span class="font-bold text-haramain-teal" x-text="estimatedMonths + ' Bulan Penagihan'"></span>
                </div>
            </div>

            <!-- Baris 3: Keuangan Per Pax (Mode Keuangan) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Total Target Biaya / Pax <span class="text-red-500">*</span></label>
                    <div class="currency-input-group">
                        <span class="currency-addon">Rp</span>
                        <input type="text"
                               inputmode="numeric"
                               :value="formatRupiah(targetPax)"
                               @input="let v = $event.target.value.replace(/[^0-9]/g, ''); targetPax = v ? parseInt(v, 10) : 0; $event.target.value = formatRupiah(targetPax); autoCalculateMonthly()"
                               placeholder="0"
                               class="currency-input-field w-full text-base font-black text-slate-800 tracking-wide placeholder:text-slate-300" required>
                        <input type="hidden" name="target_per_pax" :value="targetPax">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Total biaya paket yang harus terkumpul per peserta.</p>
                    @error('target_per_pax') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Tagihan Bulanan / Pax <span class="text-red-500">*</span></label>
                    <div class="currency-input-group">
                        <span class="currency-addon">Rp</span>
                        <input type="text"
                               inputmode="numeric"
                               :value="formatRupiah(monthlyPax)"
                               @input="let v = $event.target.value.replace(/[^0-9]/g, ''); monthlyPax = v ? parseInt(v, 10) : 0; $event.target.value = formatRupiah(monthlyPax)"
                               placeholder="0"
                               class="currency-input-field w-full text-base font-black text-[#346733] tracking-wide placeholder:text-slate-300" required>
                        <input type="hidden" name="monthly_per_pax" :value="monthlyPax">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">Nominal yang ditagihkan otomatis tiap tanggal 1 per orang.</p>
                    @error('monthly_per_pax') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Baris 4: Status Kloter -->
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Awal Kloter <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="status" value="active" {{ old('status', 'active') === 'active' ? 'checked' : '' }} class="text-haramain-green focus:ring-haramain-green">
                        <span class="text-xs font-semibold text-gray-800">Active</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="status" value="draft" {{ old('status') === 'draft' ? 'checked' : '' }} class="text-haramain-green focus:ring-haramain-green">
                        <span class="text-xs font-semibold text-gray-800">Draft</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="status" value="closed" {{ old('status') === 'closed' ? 'checked' : '' }} class="text-haramain-green focus:ring-haramain-green">
                        <span class="text-xs font-semibold text-gray-800">Closed</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="status" value="completed" {{ old('status') === 'completed' ? 'checked' : '' }} class="text-haramain-green focus:ring-haramain-green">
                        <span class="text-xs font-semibold text-gray-800">Completed</span>
                    </label>
                </div>
                @error('status') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Baris 5: Deskripsi -->
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Catatan / Deskripsi Fasilitas Kloter</label>
                <textarea name="description" rows="3" placeholder="Informasi hotel Makkah/Madinah, maskapai, atau ketentuan khusus..." class="w-full text-sm rounded-xl border-gray-300 shadow-sm focus:border-haramain-green focus:ring-haramain-green">{{ old('description') }}</textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Submit & Action -->
            <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
                <a href="{{ route('admin.kloters.index') }}" class="px-5 py-2.5 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white shadow-md transition bg-[#346733] hover:bg-[#234622] active:scale-[0.99] cursor-pointer">
                    Simpan Master Kloter
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
