{{--
/**
 * File: resources/views/admin/kloters/create.blade.php
 * Tujuan: Formulir pembuatan paket master kloter umroh baru oleh admin beserta auto-uppercase kode kloter, link WhatsApp grup, dan pilihan multi-rekening bank penampung
 * Dipakai Oleh: App\Http\Controllers\Admin\KloterController@create
 * Dependensi Utama: Tailwind CSS CDN, Alpine.js, App\Models\Kloter, App\Models\BankAccount
 * Daftar Komponen Utama: Input nama, kode (auto-uppercase), target tabungan/pax, cicilan/bln/pax, start_date, end_date, link whatsapp group, estimasi durasi bulan dinamis, checklist rekening bank & quick add rekening baru
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
                    <input type="text" name="code" value="{{ old('code') }}" placeholder="KLTR-2027-01" oninput="this.value = this.value.toUpperCase()" class="w-full text-sm font-mono uppercase rounded-xl border-slate-300 shadow-sm focus:border-haramain-green focus:ring-haramain-green px-4 py-2.5" required>
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

            <!-- Baris 5: Rekening Bank Penampung Kloter -->
            <div class="space-y-3" x-data="{ showNewBank: false }">
                <div class="flex items-center justify-between">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                            Rekening Bank Tujuan Kloter
                        </label>
                        <p class="text-xs text-gray-500">Pilih satu atau beberapa rekening bank penampung setoran tabungan untuk kloter ini.</p>
                    </div>
                    <button type="button" @click="showNewBank = !showNewBank" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 flex items-center gap-1 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span x-text="showNewBank ? 'Tutup Input Bank Baru' : '+ Rekening Bank Baru'"></span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse($bankAccounts as $bank)
                        <label class="relative flex items-start p-3.5 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/40">
                            <div class="flex items-center h-5">
                                <input type="checkbox" name="bank_account_ids[]" value="{{ $bank->id }}"
                                    {{ in_array($bank->id, old('bank_account_ids', [])) ? 'checked' : '' }}
                                    class="w-4 h-4 rounded text-[#346733] focus:ring-[#346733] border-gray-300">
                            </div>
                            <div class="ml-3 text-xs">
                                <span class="font-bold text-gray-900 block">{{ $bank->bank_name }}</span>
                                <span class="font-mono text-gray-700 block mt-0.5">{{ $bank->account_number }}</span>
                                <span class="text-gray-400 block mt-0.5">a/n {{ $bank->account_holder }}</span>
                            </div>
                        </label>
                    @empty
                        <div class="col-span-2 p-4 text-center rounded-xl bg-gray-50 border border-dashed border-gray-300 text-xs text-gray-500">
                            Belum ada master rekening bank. Silakan tambahkan rekening bank di bawah.
                        </div>
                    @endforelse
                </div>
                @error('bank_account_ids') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

                <!-- Form Tambah Bank Baru Cepat (Accordion) -->
                <div x-show="showNewBank" x-cloak class="p-4 bg-gray-50 rounded-2xl border border-gray-200 space-y-3">
                    <div class="flex items-center gap-1.5 text-xs font-bold text-gray-800">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        <span>Tambah Rekening Bank Baru Langsung</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nama Bank</label>
                            <input type="text" name="new_bank_name" value="{{ old('new_bank_name') }}" placeholder="Contoh: BSI / BCA Syariah" class="w-full text-xs rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Nomor Rekening</label>
                            <input type="text" name="new_account_number" value="{{ old('new_account_number') }}" placeholder="Contoh: 7123456789" class="w-full text-xs font-mono rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-600 mb-1">Atas Nama (Pemilik)</label>
                            <input type="text" name="new_account_holder" value="{{ old('new_account_holder') }}" placeholder="Contoh: Yayasan Haramain" class="w-full text-xs rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 px-3 py-2">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Baris Link WhatsApp Grup -->
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                    </svg>
                    Link Grup WhatsApp Jama'ah (Opsional)
                </label>
                <input type="url" name="whatsapp_group_url" value="{{ old('whatsapp_group_url') }}" placeholder="https://chat.whatsapp.com/..." class="w-full text-sm rounded-xl border-slate-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600 px-4 py-2.5">
                <p class="text-xs text-gray-500 mt-1">Tautan grup WhatsApp agar jama'ah kloter dapat langsung bergabung.</p>
                @error('whatsapp_group_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Baris 6: Deskripsi -->
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
