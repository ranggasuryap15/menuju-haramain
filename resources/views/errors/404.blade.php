<!--
File: resources/views/errors/404.blade.php
Tujuan: Halaman penanganan ramah saat halaman tidak ditemukan (HTTP 404 Not Found) dengan navigasi kembali untuk PWA dan web
Dipakai Oleh: Laravel Exception Handler saat NotFoundHttpException terjadi
Dependensi Utama: Tailwind CSS CDN, Google Fonts (Plus Jakarta Sans)
Daftar Komponen Utama: Kartu status 404, Tombol Beranda, Tombol Kembali
Side Effect: Navigasi pengguna kembali ke halaman utama
-->
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Halaman Tidak Ditemukan - Menuju Haramain</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased text-slate-800 bg-gradient-to-br from-slate-100 via-emerald-50/40 to-slate-100">
    <div class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200/80 p-6 sm:p-8 text-center space-y-6">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="space-y-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-bold">Error 404</span>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900">Halaman Tidak Ditemukan</h1>
            <p class="text-xs sm:text-sm text-slate-500">Tautan yang Anda tuju mungkin sudah dipindahkan, diubah, atau tidak tersedia.</p>
        </div>
        <div class="space-y-2">
            <a href="{{ url('/') }}" class="w-full py-3 px-4 rounded-xl bg-[#346733] hover:bg-[#234622] text-white font-bold text-xs sm:text-sm transition flex items-center justify-center gap-2">
                &larr; Kembali ke Beranda
            </a>
        </div>
    </div>
</body>
</html>
