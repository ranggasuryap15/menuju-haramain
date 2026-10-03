<!--
File: resources/views/admin/registrations/index.blade.php
Tujuan: Halaman antrean verifikasi dan persetujuan (approval) pendaftaran kloter oleh Admin/Superadmin
Dipakai Oleh: App\Http\Controllers\Admin\RegistrationApprovalController@index (GET /admin/registrations)
Dependensi Utama: layouts.app, KloterRegistration, Alpine.js
Daftar Komponen Utama: Filter status (Pending, Aktif, Ditolak), Tabel desktop / Card mobile, Modal konfirmasi persetujuan, Modal penolakan
Side Effect: POST ke /admin/registrations/{registration}/approve dan reject
-->
@extends('layouts.app')

@section('title', 'Approval Pendaftaran Kloter')

@section('content')
<div class="space-y-6" x-data="{
    approveModalOpen: false,
    rejectModalOpen: false,
    selectedId: null,
    selectedName: '',
    selectedKloter: '',
    openApprove(id, name, kloter) {
        this.selectedId = id;
        this.selectedName = name;
        this.selectedKloter = kloter;
        this.approveModalOpen = true;
    },
    openReject(id, name, kloter) {
        this.selectedId = id;
        this.selectedName = name;
        this.selectedKloter = kloter;
        this.rejectModalOpen = true;
    }
}">

    <!-- Header & Keterangan -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">Persetujuan Pendaftaran Kloter</h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Verifikasi pendaftaran jamaah baru sebelum kloter diaktifkan dan tagihan diterbitkan.
            </p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="text-xs font-bold text-[#346733] hover:underline flex items-center space-x-1">
            <span>&larr; Dasbor Admin</span>
        </a>
    </div>

    <!-- Alert Notifikasi -->
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs font-semibold flex items-center space-x-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Tab Filter Status -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
        <a href="{{ route('admin.registrations.index', ['status' => 'pending']) }}"
           class="px-4 py-2 rounded-xl font-bold transition whitespace-nowrap {{ $status === 'pending' ? 'bg-[#346733] text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
            Menunggu Verifikasi ({{ $counts['pending'] }})
        </a>
        <a href="{{ route('admin.registrations.index', ['status' => 'active']) }}"
           class="px-4 py-2 rounded-xl font-bold transition whitespace-nowrap {{ $status === 'active' ? 'bg-[#346733] text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
            Disetujui / Aktif ({{ $counts['active'] }})
        </a>
        <a href="{{ route('admin.registrations.index', ['status' => 'rejected']) }}"
           class="px-4 py-2 rounded-xl font-bold transition whitespace-nowrap {{ $status === 'rejected' ? 'bg-[#346733] text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
            Ditolak ({{ $counts['rejected'] }})
        </a>
        <a href="{{ route('admin.registrations.index', ['status' => 'all']) }}"
           class="px-4 py-2 rounded-xl font-bold transition whitespace-nowrap {{ $status === 'all' ? 'bg-[#346733] text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
            Semua Data
        </a>
    </div>

    <!-- Data List Container -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        <!-- Mobile Cards Format -->
        <div class="sm:hidden divide-y divide-slate-100 p-4 space-y-3">
            @forelse($registrations as $reg)
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-[#346733]">ID #{{ $reg->id }}</span>
                        @if($reg->isPending())
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">Menunggu Approval</span>
                        @elseif($reg->isActive())
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">Aktif & Disetujui</span>
                        @elseif($reg->isRejected())
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800 border border-red-300">Ditolak</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">{{ $reg->status }}</span>
                        @endif
                    </div>

                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ $reg->user->name }}</h3>
                        <p class="text-xs text-slate-500">{{ $reg->user->email }} &bull; {{ $reg->user->phone ?? '-' }}</p>
                    </div>

                    <div class="pt-2 border-t border-slate-200 text-xs space-y-1">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Kloter:</span>
                            <span class="font-bold text-slate-800">{{ $reg->kloter->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Peserta (Pax):</span>
                            <span class="font-semibold text-slate-800">{{ $reg->total_pax }} Orang</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Target Total:</span>
                            <span class="font-bold text-[#346733]">Rp {{ number_format($reg->target_total, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <!-- Daftar Nama Pax Singkat -->
                    <div class="text-[11px] bg-white p-2 rounded-lg border border-slate-200 text-slate-600">
                        <span class="font-bold text-slate-700 block mb-1">Daftar Anggota:</span>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($reg->paxes as $pax)
                                <li>{{ $pax->familyMember->full_name ?? 'Peserta' }} ({{ $pax->familyMember->relationship ?? '-' }})</li>
                            @endforeach
                        </ul>
                    </div>

                    @if($reg->isRejected() && $reg->admin_notes)
                        <div class="text-[11px] bg-red-50 p-2 rounded-lg border border-red-200 text-red-700">
                            <strong>Alasan Penolakan:</strong> {{ $reg->admin_notes }}
                        </div>
                    @endif

                    <div class="pt-2 flex items-center justify-between gap-2 border-t border-slate-200">
                        <a href="{{ route('admin.registrations.show', $reg) }}" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                            Detail &rarr;
                        </a>

                        @if($reg->isPending())
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="openApprove({{ $reg->id }}, '{{ addslashes($reg->user->name) }}', '{{ addslashes($reg->kloter->name) }}')"
                                        class="px-3 py-1.5 rounded-lg bg-[#346733] text-white text-xs font-bold hover:bg-[#234622] transition">
                                    Setujui
                                </button>
                                <button type="button" @click="openReject({{ $reg->id }}, '{{ addslashes($reg->user->name) }}', '{{ addslashes($reg->kloter->name) }}')"
                                        class="px-2.5 py-1.5 rounded-lg border border-red-300 text-red-600 text-xs font-bold hover:bg-red-50 transition">
                                    Tolak
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-center py-8 text-xs text-slate-400">Tidak ada data pendaftaran kloter pada kategori ini.</p>
            @endforelse
        </div>

        <!-- Desktop Table Format -->
        <table class="w-full text-left text-xs hidden sm:table">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
                <tr>
                    <th class="py-3 px-4">Tgl Daftar</th>
                    <th class="py-3 px-4">Nama Jamaah</th>
                    <th class="py-3 px-4">Kloter</th>
                    <th class="py-3 px-4">Jumlah Pax</th>
                    <th class="py-3 px-4">Target Tabungan</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($registrations as $reg)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-3.5 px-4 text-slate-500 whitespace-nowrap">
                            {{ $reg->created_at->format('Y-m-d') }}
                            <span class="block text-[10px] text-slate-400">{{ $reg->created_at->format('H:i') }} WIB</span>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="font-bold text-slate-900 block">{{ $reg->user->name }}</span>
                            <span class="text-[11px] text-slate-500 block">{{ $reg->user->email }}</span>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="font-semibold text-slate-800 block">{{ $reg->kloter->name }}</span>
                            <span class="text-[11px] text-[#346733] font-bold block">{{ $reg->kloter->code }}</span>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="font-bold text-slate-800">{{ $reg->total_pax }} Orang</span>
                            <span class="text-[10px] text-slate-400 block">
                                {{ $reg->paxes->map(fn($p) => $p->familyMember->relationship ?? 'Peserta')->implode(', ') }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 font-bold text-slate-800 whitespace-nowrap">
                            Rp {{ number_format($reg->target_total, 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                            @if($reg->isPending())
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">
                                    Menunggu Approval
                                </span>
                            @elseif($reg->isActive())
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                    Aktif & Disetujui
                                </span>
                            @elseif($reg->isRejected())
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 border border-red-300">
                                    Ditolak
                                </span>
                            @else
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                                    {{ $reg->status }}
                                </span>
                            @endif
                        </td>
                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('admin.registrations.show', $reg) }}" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition" title="Lihat Rincian">
                                    Rincian
                                </a>

                                @if($reg->isPending())
                                    <button type="button" @click="openApprove({{ $reg->id }}, '{{ addslashes($reg->user->name) }}', '{{ addslashes($reg->kloter->name) }}')"
                                            class="px-2.5 py-1.5 rounded-lg bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition cursor-pointer" title="Setujui">
                                        Setujui
                                    </button>
                                    <button type="button" @click="openReject({{ $reg->id }}, '{{ addslashes($reg->user->name) }}', '{{ addslashes($reg->kloter->name) }}')"
                                            class="px-2 py-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-xs font-bold transition cursor-pointer" title="Tolak">
                                        Tolak
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-10 text-center text-slate-400 text-xs">
                            Tidak ada data pendaftaran kloter pada kategori ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Paginasi -->
        @if($registrations->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $registrations->links() }}
            </div>
        @endif
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL KONFIRMASI APPROVAL PENDAFTARAN KLOTER                          -->
    <!-- ===================================================================== -->
    <div x-show="approveModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center sm:p-0">
            <div x-show="approveModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="approveModalOpen = false"></div>

            <div x-show="approveModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0"
                 class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-200">
                
                <form :action="'{{ url('/admin/registrations') }}/' + selectedId + '/approve'" method="POST">
                    @csrf
                    <div class="bg-white p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-emerald-100 text-[#346733] flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900">Konfirmasi Persetujuan Kloter</h3>
                                <p class="text-xs text-slate-500">Pendaftaran akan diaktifkan ke dalam kloter.</p>
                            </div>
                        </div>

                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Nama Jamaah:</span>
                                <span class="font-bold text-slate-900" x-text="selectedName"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Kloter Umroh:</span>
                                <span class="font-bold text-[#346733]" x-text="selectedKloter"></span>
                            </div>
                        </div>

                        <p class="text-xs text-slate-600 leading-relaxed">
                            Setelah disetujui, pendaftaran kloter jamaah akan berstatus <strong>Aktif</strong>. Jika periode kloter sedang berjalan, jadwal tagihan bulanan pertama akan otomatis diterbitkan.
                        </p>
                    </div>

                    <div class="bg-slate-50 px-6 py-4 flex flex-row-reverse gap-2 border-t border-slate-100">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-[#346733] hover:bg-[#234622] text-white text-xs font-bold transition shadow-sm cursor-pointer">
                            Ya, Setujui Pendaftaran
                        </button>
                        <button type="button" @click="approveModalOpen = false" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 transition cursor-pointer">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- MODAL PENOLAKAN PENDAFTARAN KLOTER (REJECT)                          -->
    <!-- ===================================================================== -->
    <div x-show="rejectModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center sm:p-0">
            <div x-show="rejectModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="rejectModalOpen = false"></div>

            <div x-show="rejectModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0"
                 class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-slate-200">
                
                <form :action="'{{ url('/admin/registrations') }}/' + selectedId + '/reject'" method="POST">
                    @csrf
                    <div class="bg-white p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900">Tolak Pendaftaran Kloter</h3>
                                <p class="text-xs text-slate-500">Berikan alasan penolakan yang jelas untuk jamaah.</p>
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                            <span class="text-slate-500">Peserta:</span> <strong class="text-slate-900" x-text="selectedName"></strong> &bull;
                            <span class="text-slate-500">Kloter:</span> <strong class="text-slate-900" x-text="selectedKloter"></strong>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Alasan Penolakan <span class="text-red-500">*</span>
                            </label>
                            <textarea name="admin_notes" rows="3" required minlength="5" maxlength="500"
                                      placeholder="Contoh: Kuota kloter telah penuh / Data anggota keluarga belum lengkap / Mohon mendaftar di kloter berikutnya"
                                      class="w-full p-3.5 text-sm rounded-xl border border-slate-300 focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none"></textarea>
                        </div>
                    </div>

                    <div class="bg-slate-50 px-6 py-4 flex flex-row-reverse gap-2 border-t border-slate-100">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition shadow-sm cursor-pointer">
                            Tolak Pendaftaran
                        </button>
                        <button type="button" @click="rejectModalOpen = false" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 transition cursor-pointer">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
