<!--
File: resources/views/jamaah/family/index.blade.php
Tujuan: Halaman pengelolaan anggota keluarga/peserta (suami, istri, anak) di dalam satu akun penanggung jawab beserta fitur penambahan, pengubahan data (modal edit), dan penghapusan
Dipakai Oleh: Jamaah\FamilyMemberController@index (GET /jamaah/family)
Dependensi Utama: layouts.app, FamilyMember, Alpine.js
Daftar Komponen Utama: Form tambah anggota keluarga, Kartu daftar anggota terdaftar, Tombol Edit & Modal Popup Edit Anggota, Tombol Hapus, Status keikutsertaan kloter
Side Effect: POST ke /jamaah/family, PUT ke /jamaah/family/{id}, DELETE ke /jamaah/family/{id}
-->
@extends('layouts.app')

@section('title', 'Data Anggota Keluarga')

@section('content')
<div class="space-y-6" x-data="{
    editModalOpen: false,
    editFormAction: '',
    editMember: {
        id: null,
        full_name: '',
        relationship: 'Kepala Keluarga',
        identity_number: '',
        birth_date: '',
        gender: '',
        phone: ''
    },
    openEditModal(member) {
        this.editMember = {
            id: member.id,
            full_name: member.full_name || '',
            relationship: member.relationship || 'Kepala Keluarga',
            identity_number: member.identity_number || '',
            birth_date: member.birth_date ? member.birth_date.substring(0, 10) : '',
            gender: member.gender || '',
            phone: member.phone || ''
        };
        this.editFormAction = '{{ url('/jamaah/family') }}/' + member.id;
        this.editModalOpen = true;
    }
}">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">Anggota Keluarga & Peserta</h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Satu akun dapat menampung beberapa orang (ayah, ibu, anak, dsb) untuk didaftarkan bersama ke kloter umroh.
            </p>
        </div>
        <a href="{{ route('jamaah.dashboard') }}" class="text-xs font-bold text-[#346733] hover:underline flex items-center space-x-1">
            <span>&larr; Kembali ke Dashboard</span>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Form Tambah Anggota (1 Kolom) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h2 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                    <span class="p-1.5 rounded-lg bg-emerald-100 text-[#346733]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </span>
                    <span>Tambah Anggota Baru</span>
                </h2>
                <p class="text-[11px] text-slate-500 mt-1">Masukkan data diri calon peserta</p>
            </div>

            <form action="{{ route('jamaah.family.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap Sesuai KTP/Paspor *</label>
                    <input type="text" name="full_name" required value="{{ old('full_name') }}"
                        class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none"
                        placeholder="Contoh: Fatimah Az-Zahra">
                    @error('full_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Hubungan Keluarga *</label>
                    <select name="relationship" required
                        class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none bg-white">
                        <option value="Kepala Keluarga">Kepala Keluarga (Diri Sendiri)</option>
                        <option value="Istri">Istri</option>
                        <option value="Suami">Suami</option>
                        <option value="Anak">Anak</option>
                        <option value="Orang Tua">Orang Tua</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                    @error('relationship')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor NIK / Paspor</label>
                    <input type="text" name="identity_number" value="{{ old('identity_number') }}"
                        class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none"
                        placeholder="16 digit NIK atau Nomor Paspor">
                    @error('identity_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tanggal Lahir</label>
                        <input type="date" name="birth_date" value="{{ old('birth_date') }}"
                            class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Jenis Kelamin</label>
                        <select name="gender" class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none bg-white">
                            <option value="">Pilih</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor Telepon / WhatsApp</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                        class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none"
                        placeholder="08xxxxxxxxxx">
                </div>

                <button type="submit" class="w-full py-3 px-4 rounded-xl bg-[#346733] hover:bg-[#234622] text-white font-bold text-xs tracking-wider uppercase shadow-md transition-all">
                    Simpan Anggota Keluarga
                </button>
            </form>
        </div>

        <!-- Daftar Anggota Terdaftar (2 Kolom) -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Daftar Anggota Keluarga ({{ $members->count() }} Orang)</h2>
                        <p class="text-xs text-slate-500">Anggota yang dapat dipilih saat mendaftar ke kloter tabungan</p>
                    </div>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($members as $m)
                        <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start space-x-3">
                                <span class="w-10 h-10 rounded-xl bg-teal-100 text-[#007C6A] font-extrabold text-sm flex items-center justify-center flex-shrink-0">
                                    {{ substr($m->relationship, 0, 1) }}
                                </span>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <h3 class="text-sm font-bold text-slate-900">{{ $m->full_name }}</h3>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                            {{ $m->relationship }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-0.5 space-x-2">
                                        <span>NIK: {{ $m->identity_number ?: '-' }}</span>
                                        <span>&bull;</span>
                                        <span>Gender: {{ $m->gender === 'L' ? 'Laki-laki' : ($m->gender === 'P' ? 'Perempuan' : '-') }}</span>
                                        @if($m->birth_date)
                                            <span>&bull;</span>
                                            <span>Lahir: {{ $m->birth_date->format('d/m/Y') }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-1">
                                        @if($m->registration_paxes_count > 0)
                                            <span class="inline-flex items-center text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                                Terdaftar di {{ $m->registration_paxes_count }} Kloter
                                            </span>
                                        @else
                                            <span class="inline-flex items-center text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                                                Belum masuk kloter
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center space-x-2 shrink-0">
                                <button type="button"
                                        @click="openEditModal({{ json_encode([
                                            'id' => $m->id,
                                            'full_name' => $m->full_name,
                                            'relationship' => $m->relationship,
                                            'identity_number' => $m->identity_number,
                                            'birth_date' => $m->birth_date ? $m->birth_date->format('Y-m-d') : '',
                                            'gender' => $m->gender,
                                            'phone' => $m->phone,
                                        ]) }})"
                                        class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 text-xs font-bold transition flex items-center space-x-1 cursor-pointer">
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    <span>Edit</span>
                                </button>

                                @if($m->registration_paxes_count === 0)
                                    <form action="{{ route('jamaah.family.destroy', $m) }}" method="POST" onsubmit="return confirm('Hapus anggota keluarga ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-xs font-bold transition cursor-pointer">
                                            Hapus
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[11px] text-slate-400 italic">Terkunci dalam kloter</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-400 text-xs">
                            Belum ada anggota keluarga yang didaftarkan. Gunakan form di sebelah kiri untuk menambah anggota.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    <!-- Modal Edit Anggota Keluarga -->
    <div x-show="editModalOpen" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div x-show="editModalOpen" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="editModalOpen = false"
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal Content -->
            <div x-show="editModalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-100">
                
                <div class="p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                        <div class="flex items-center space-x-2">
                            <span class="p-2 rounded-xl bg-emerald-100 text-[#346733]">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-slate-900" id="modal-title">Edit Data Anggota Keluarga</h3>
                                <p class="text-xs text-slate-500">Perbarui identitas calon peserta umroh</p>
                            </div>
                        </div>
                        <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form :action="editFormAction" method="POST" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap Sesuai KTP/Paspor *</label>
                            <input type="text" name="full_name" x-model="editMember.full_name" required
                                class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none"
                                placeholder="Contoh: Fatimah Az-Zahra">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Hubungan Keluarga *</label>
                            <select name="relationship" x-model="editMember.relationship" required
                                class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none bg-white">
                                <option value="Kepala Keluarga">Kepala Keluarga (Diri Sendiri)</option>
                                <option value="Istri">Istri</option>
                                <option value="Suami">Suami</option>
                                <option value="Anak">Anak</option>
                                <option value="Orang Tua">Orang Tua</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor NIK / Paspor</label>
                            <input type="text" name="identity_number" x-model="editMember.identity_number"
                                class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none"
                                placeholder="16 digit NIK atau Nomor Paspor">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Tanggal Lahir</label>
                                <input type="date" name="birth_date" x-model="editMember.birth_date"
                                    class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Jenis Kelamin</label>
                                <select name="gender" x-model="editMember.gender" class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none bg-white">
                                    <option value="">Pilih</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="phone" x-model="editMember.phone"
                                class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-[#346733] focus:border-[#346733] outline-none"
                                placeholder="08xxxxxxxxxx">
                        </div>

                        <div class="pt-4 flex items-center justify-end space-x-3 border-t border-slate-100">
                            <button type="button" @click="editModalOpen = false"
                                class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-100 text-xs font-bold transition cursor-pointer">
                                Batal
                            </button>
                            <button type="submit"
                                class="px-5 py-2.5 rounded-xl bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold shadow-md transition cursor-pointer">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

