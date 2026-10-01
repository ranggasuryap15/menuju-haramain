<!--
File: resources/views/jamaah/family/index.blade.php
Tujuan: Halaman pengelolaan anggota keluarga/peserta (suami, istri, anak) di dalam satu akun penanggung jawab
Dipakai Oleh: Jamaah\FamilyMemberController@index (GET /jamaah/family)
Dependensi Utama: layouts.app, FamilyMember
Daftar Komponen Utama: Form tambah anggota keluarga, Kartu daftar anggota terdaftar, status keikutsertaan kloter
Side Effect: POST ke /jamaah/family atau DELETE ke /jamaah/family/{id}
-->
@extends('layouts.app')

@section('title', 'Data Anggota Keluarga')

@section('content')
<div class="space-y-6">

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
                            <div>
                                @if($m->registration_paxes_count === 0)
                                    <form action="{{ route('jamaah.family.destroy', $m) }}" method="POST" onsubmit="return confirm('Hapus anggota keluarga ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-xs font-bold transition-colors">
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

</div>
@endsection

