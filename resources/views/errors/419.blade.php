<!--
File: resources/views/errors/419.blade.php
Tujuan: Halaman penanganan ramah saat sesi atau CSRF token kedaluwarsa (HTTP 419 Page Expired) khusus aplikasi PWA dan web
Dipakai Oleh: Laravel Exception Handler saat TokenMismatchException terjadi
Dependensi Utama: Tailwind CSS CDN, Google Fonts (Plus Jakarta Sans)
Daftar Komponen Utama: Kartu status sesi berakhir, tombol Refresh/Muat Ulang, tombol Masuk/Login, tombol Beranda
Side Effect: Refresh halaman atau navigasi ke /login atau /
-->
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sesi Telah Berakhir - Menuju Haramain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased text-slate-800 bg-gradient-to-br from-slate-100 via-emerald-50/40 to-slate-100">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200/80 p-6 sm:p-8 text-center space-y-6">
        
        <!-- Logo & Ikon Header -->
        <div class="relative mx-auto w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-[#346733] to-[#007C6A] text-[#D4AF37] flex items-center justify-center shadow-lg shadow-emerald-900/10">
            <svg class="w-8 h-8 sm:w-10 sm:h-10 fill-current" viewBox="0 0 24 24">
                <path d="M12 2L3 8v12h18V8L12 2zm0 3.2L18.4 9H5.6L12 5.2zM5 11h14v7H5v-7zm7 1.5c-1.38 0-2.5 1.12-2.5 2.5s1.12 2.5 2.5 2.5 2.5-1.12 2.5-2.5-1.12-2.5-2.5-2.5z"/>
            </svg>
            <span class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-amber-400 text-slate-900 flex items-center justify-center text-xs font-black shadow">
                !
            </span>
        </div>

        <!-- Judul & Keterangan -->
        <div class="space-y-2">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-bold">
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Status 419 &bull; Sesi Kedaluwarsa</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Sesi Halaman Telah Berakhir</h1>
            <p class="text-xs sm:text-sm text-slate-500 leading-relaxed max-w-sm mx-auto">
                Demi keamanan data akun dan tabungan umroh Anda, sistem secara otomatis mengamankan sesi setelah tidak aktif beberapa saat atau saat aplikasi baru dipasang.
            </p>
        </div>

        <!-- Panduan & Solusi Cepat -->
        <div class="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200/80 text-left text-xs text-emerald-950 space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-emerald-900">
                <svg class="w-4 h-4 text-emerald-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Langkah Penyelesaian Mudah:</span>
            </div>
            <p class="text-[11px] text-emerald-800 leading-relaxed pl-5">
                Cukup tekan tombol <strong>"Muat Ulang Halaman"</strong> di bawah untuk mendapatkan sesi baru yang segar, atau tekan <strong>"Masuk Kembali"</strong> jika Anda perlu login ulang.
            </p>
        </div>

        <!-- Tombol Aksi Navigasi (Sangat penting untuk PWA tanpa address bar) -->
        <div class="space-y-2.5 pt-2">
            <!-- Tombol 1: Reload / Muat Ulang -->
            <button type="button"
                    onclick="window.location.reload();"
                    class="w-full py-3 px-4 rounded-xl bg-[#346733] hover:bg-[#234622] text-white font-bold text-xs sm:text-sm shadow-md shadow-emerald-900/10 transition flex items-center justify-center gap-2 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Muat Ulang Halaman (Refresh)</span>
            </button>

            <!-- Tombol 2: Masuk Kembali / Login -->
            <a href="{{ url('/login') }}"
               class="w-full py-2.5 px-4 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                <span>Masuk Kembali (Login)</span>
            </a>

            <!-- Tombol 3: Beranda -->
            <div class="pt-2">
                <a href="{{ url('/') }}" class="text-xs font-semibold text-slate-400 hover:text-[#346733] transition inline-flex items-center gap-1">
                    <span>&larr; Kembali ke Beranda Utama</span>
                </a>
            </div>
        </div>

    </div>
</body>
</html>
