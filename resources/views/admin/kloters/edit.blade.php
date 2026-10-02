{{--
/**
 * File: resources/views/admin/kloters/edit.blade.php
 * Tujuan: Formulir pengubahan rincian master kloter umroh (nama, kode auto-uppercase, target tabungan, cicilan, tanggal periode, status, relasi & edit detail multi-rekening bank penampung, deskripsi) oleh Superadmin dan Admin Keuangan
 * Dipakai Oleh: App\Http\Controllers\Admin\KloterController@edit (GET /admin/kloters/{kloter}/edit)
 * Dependensi Utama: layouts.app, Tailwind CSS, Alpine.js, App\Models\Kloter, App\Models\BankAccount
 * Daftar Komponen Utama: Breadcrumb, Input nama & kode unik (auto-uppercase), Periode tanggal dinamis, Mode keuangan target/cicilan per pax, Pilihan status kloter, Checklist rekening bank kloter dengan tombol edit modal & tambah rekening baru, Input deskripsi/fasilitas, Modal edit rekening bank, Tombol update
 * Side Effect: PUT request form ke route('admin.kloters.update', $kloter), PUT request form ke route('admin.bank-accounts.update', $bank)
 */
--}}
@extends('layouts.app')

@section('title', 'Edit Kloter - ' . $kloter->name)

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="{
    startDate: '{{ old('start_date', $kloter->start_date ? $kloter->start_date->format('Y-m-d') : '') }}',
    endDate: '{{ old('end_date', $kloter->end_date ? $kloter->end_date->format('Y-m-d') : '') }}',
    targetPax: {{ (int) old('target_per_pax', (int)$kloter->target_per_pax) }},
    monthlyPax: {{ (int) old('monthly_per_pax', (int)$kloter->monthly_per_pax) }},
    showEditBankModal: false,
    editBank: { id: null, bank_name: '', account_number: '', account_holder: '', is_active: true },
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
    <!-- Header & Breadcrumb -->
    <div class="flex items-center justify-between">
        <div>
            <nav class="flex items-center space-x-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('admin.kloters.index') }}" class="hover:text-haramain-green flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Daftar Kloter
                </a>
                <span>/</span>
                <a href="{{ route('admin.kloters.show', $kloter) }}" class="hover:text-haramain-green font-medium text-gray-700">
                    {{ $kloter->code }}
                </a>
                <span>/</span>
                <span class="text-gray-700 font-medium">Edit</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit Detail Paket Kloter</h1>
            <p class="text-sm text-gray-500 mt-0.5">Perbarui nama, kode, periode, tarif per jamaah, atau status kloter <strong>{{ $kloter->code }}</strong>.</p>
        </div>
    </div>

    <!-- Form Container -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <form action="{{ route('admin.kloters.update', $kloter) }}" method="POST" class="p-6 sm:p-8 space-y-6">
            @csrf
            @method('PUT')

            <!-- Baris 1: Nama & Kode -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Nama Kloter Umroh <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $kloter->name) }}" placeholder="Contoh: Kloter Ramadhan Berkah 1448 H" class="w-full text-sm rounded-xl border-slate-300 shadow-sm focus:border-haramain-green focus:ring-haramain-green px-4 py-2.5" required>
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Kode Unik Kloter <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code', $kloter->code) }}" placeholder="KLTR-2027-01" oninput="this.value = this.value.toUpperCase()" class="w-full text-sm font-mono uppercase rounded-xl border-slate-300 shadow-sm focus:border-haramain-green focus:ring-haramain-green px-4 py-2.5" required>
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
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Status Kloter <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 {{ old('status', $kloter->status) === 'active' ? 'bg-emerald-50/70 border-emerald-300' : '' }}">
                        <input type="radio" name="status" value="active" {{ old('status', $kloter->status) === 'active' ? 'checked' : '' }} class="text-haramain-green focus:ring-haramain-green">
                        <span class="text-xs font-semibold text-gray-800">Active</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 {{ old('status', $kloter->status) === 'draft' ? 'bg-amber-50/70 border-amber-300' : '' }}">
                        <input type="radio" name="status" value="draft" {{ old('status', $kloter->status) === 'draft' ? 'checked' : '' }} class="text-haramain-green focus:ring-haramain-green">
                        <span class="text-xs font-semibold text-gray-800">Draft</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 {{ old('status', $kloter->status) === 'closed' ? 'bg-slate-100 border-slate-300' : '' }}">
                        <input type="radio" name="status" value="closed" {{ old('status', $kloter->status) === 'closed' ? 'checked' : '' }} class="text-haramain-green focus:ring-haramain-green">
                        <span class="text-xs font-semibold text-gray-800">Closed</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 {{ old('status', $kloter->status) === 'completed' ? 'bg-blue-50/70 border-blue-300' : '' }}">
                        <input type="radio" name="status" value="completed" {{ old('status', $kloter->status) === 'completed' ? 'checked' : '' }} class="text-haramain-green focus:ring-haramain-green">
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

                @php
                    $selectedBankIds = old('bank_account_ids', $kloter->bankAccounts->pluck('id')->toArray());
                @endphp

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @forelse($bankAccounts as $bank)
                        <div class="relative flex items-start justify-between p-3.5 rounded-xl border border-gray-200 hover:bg-gray-50 transition">
                            <label class="flex items-start cursor-pointer flex-1 mr-2">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" name="bank_account_ids[]" value="{{ $bank->id }}"
                                        {{ in_array($bank->id, $selectedBankIds) ? 'checked' : '' }}
                                        class="w-4 h-4 rounded text-[#346733] focus:ring-[#346733] border-gray-300">
                                </div>
                                <div class="ml-3 text-xs">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-gray-900 block">{{ $bank->bank_name }}</span>
                                        <span class="px-1.5 py-0.2 rounded text-[9px] font-semibold {{ $bank->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $bank->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </div>
                                    <span class="font-mono text-gray-700 block mt-0.5">{{ $bank->account_number }}</span>
                                    <span class="text-gray-400 block mt-0.5">a/n {{ $bank->account_holder }}</span>
                                </div>
                            </label>
                            <button type="button" 
                                @click.prevent.stop="editBank = { id: {{ $bank->id }}, bank_name: '{{ addslashes($bank->bank_name) }}', account_number: '{{ addslashes($bank->account_number) }}', account_holder: '{{ addslashes($bank->account_holder) }}', is_active: {{ $bank->is_active ? 'true' : 'false' }} }; showEditBankModal = true;"
                                class="text-[11px] font-bold text-emerald-700 hover:text-emerald-900 bg-white hover:bg-emerald-50 border border-emerald-300 rounded px-2 py-1 shadow-2xs cursor-pointer inline-flex items-center gap-1 shrink-0">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                <span>Edit</span>
                            </button>
                        </div>
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

            <!-- Baris 6: Deskripsi -->
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Catatan / Deskripsi Fasilitas Kloter</label>
                <textarea name="description" rows="3" placeholder="Informasi hotel Makkah/Madinah, maskapai, atau ketentuan khusus..." class="w-full text-sm rounded-xl border-gray-300 shadow-sm focus:border-haramain-green focus:ring-haramain-green px-4 py-2.5">{{ old('description', $kloter->description) }}</textarea>
                @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Submit & Action -->
            <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
                <a href="{{ route('admin.kloters.show', $kloter) }}" class="px-5 py-2.5 rounded-xl border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                    Batal
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white shadow-md transition bg-[#346733] hover:bg-[#234622] active:scale-[0.99] cursor-pointer">
                    Simpan Perubahan Kloter
                </button>
            </div>
        </form>
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
</div>
@endsection
