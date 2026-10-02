<!--
File: resources/views/layouts/app.blade.php
Tujuan: Master layout responsif hybrid: Desktop Sidebar kiri, Dropdown Profil Akun topbar, Mobile Bottom Nav Bar, Mobile Slide-over Drawer dengan click-outside auto-collapse, Modal Pusat Notifikasi Interaktif, dan Global Ergonomic Form Field Styles
Dipakai Oleh: Seluruh view aplikasi (Portal Jamaah dan Portal Admin)
Dependensi Utama: Tailwind CSS CDN, Alpine.js CDN, Google Fonts (Plus Jakarta Sans), App\Services\NotificationService, App\Models\Payment, App\Models\KloterRegistration
Daftar Komponen Utama: Desktop Sidebar (lg:flex), Desktop Topbar dengan Dropdown Profil & Tombol Notifikasi, Mobile Topbar dengan Tombol Notifikasi & Burger Button, Mobile Bottom Bar, Mobile Account Drawer, Modal Pusat Notifikasi & Antrean Interaktif, Global Form Inputs CSS, Main Content Container, Footer
Side Effect: Render HTML shell, navigasi antarmuka, modal notifikasi real-time, dan standar visual input form
-->
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Menuju Haramain') - Tabungan Umroh Terencana</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN (Tanpa NPM / Nodejs) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            green: '#346733',     // Primary Dominant Green (Killarney)
                            darkGreen: '#234622',
                            teal: '#007C6A',      // Secondary Accent Teal (Deep Sea)
                            gold: '#D4AF37',      // Logo / Embellishment Gold
                            lightGold: '#F8F1D8',
                            powder: '#B0E0E5',    // Soft Background Accent
                            softGray: '#F9FAFB',
                        },
                        'haramain': {
                            green: '#346733',
                            darkGreen: '#234622',
                            teal: '#007C6A',
                            gold: '#D4AF37',
                            powder: '#B0E0E5',
                        },
                        'haramain-green': '#346733',
                        'haramain-teal': '#007C6A',
                        'haramain-gold': '#D4AF37',
                        'haramain-powder': '#B0E0E5',
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        :root {
            --haramain-green: #346733;
            --haramain-teal: #007C6A;
            --haramain-gold: #D4AF37;
            --haramain-powder: #B0E0E5;
        }
        [x-cloak] { display: none !important; }

        /* ========================================================================= */
        /* GLOBAL FORM FIELD PADDING & ERGONOMICS                                    */
        /* Menjamin semua field input memiliki padding lega dan tidak mepet          */
        /* ========================================================================= */
        input[type="text"]:not(.currency-input-field),
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="date"],
        input[type="month"],
        input[type="tel"],
        input[type="url"],
        select,
        textarea {
            padding: 0.6875rem 1rem !important; /* 11px vertikal, 16px horizontal */
            line-height: 1.5 !important;
            border-radius: 0.75rem;
            font-size: 0.875rem;
        }

        /* Khusus input berkas (file upload) */
        input[type="file"] {
            padding: 0.5rem 0.75rem !important;
            font-size: 0.875rem;
            line-height: 1.5 !important;
        }

        /* Komponen Form Nominal Mata Uang (Currency Input Group) */
        .currency-input-group {
            display: flex;
            align-items: stretch;
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .currency-input-group:focus-within {
            border-color: #346733 !important;
            box-shadow: 0 0 0 3px rgba(52, 103, 51, 0.2) !important;
        }
        .currency-addon {
            display: inline-flex;
            align-items: center;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 800;
            color: #475569;
            background-color: #f8fafc;
            border-right: 1px solid #e2e8f0;
            user-select: none;
            flex-shrink: 0;
        }
        .currency-input-group input.currency-input-field,
        .currency-input-group input[type="text"] {
            flex: 1 1 auto;
            width: 100%;
            min-width: 0;
            border: 0 !important;
            outline: none !important;
            background: transparent !important;
            padding: 0.625rem 1rem !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-800 bg-[#f8faf9]" x-data="{ mobileDrawerOpen: false, notificationsOpen: false }">

    @auth
        @php
            $isStaff = auth()->user()->isStaff();
            $notificationsData = $notificationsData ?? [
                'total_count' => 0,
                'approval_registrations_count' => 0,
                'approval_registrations' => collect(),
                'approval_payments_count' => 0,
                'approval_payments' => collect(),
                'unpaid_invoices_count' => 0,
                'unpaid_invoices' => collect(),
                'pending_payments_count' => 0,
                'pending_payments' => collect(),
                'registration_alerts_count' => 0,
                'registration_alerts' => collect(),
            ];
            $pendingPaymentsCount = $notificationsData['approval_payments_count'] ?? ($isStaff ? \App\Models\Payment::where('status', 'pending')->count() : 0);
            $pendingRegistrationsCount = $notificationsData['approval_registrations_count'] ?? ($isStaff ? \App\Models\KloterRegistration::where('status', \App\Models\KloterRegistration::STATUS_PENDING)->count() : 0);
        @endphp

        <!-- ========================================================================= -->
        <!-- 1. DESKTOP SIDEBAR (Tampil hanya di layar Desktop lg: ke atas)            -->
        <!-- ========================================================================= -->
        <aside class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-64 lg:flex-col bg-white border-r border-slate-200/90 z-40 shadow-sm">
            <!-- Brand & Logo -->
            <a href="{{ $isStaff ? route('admin.dashboard') : route('jamaah.dashboard') }}" class="flex items-center gap-3 px-6 h-20 border-b border-slate-100 hover:bg-slate-50/60 transition">
                <div class="w-10 h-10 rounded-xl bg-[#346733] flex items-center justify-center text-[#D4AF37] shadow-md flex-shrink-0">
                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                        <path d="M12 2L3 8v12h18V8L12 2zm0 3.2L18.4 9H5.6L12 5.2zM5 11h14v7H5v-7zm7 1.5c-1.38 0-2.5 1.12-2.5 2.5s1.12 2.5 2.5 2.5 2.5-1.12 2.5-2.5-1.12-2.5-2.5-2.5z"/>
                    </svg>
                </div>
                <div class="overflow-hidden">
                    <span class="text-base font-extrabold tracking-tight text-[#346733] block leading-tight truncate">Menuju Haramain</span>
                    <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase block">Tabungan Umroh</span>
                </div>
            </a>

            <!-- Role Badge -->
            <div class="px-6 py-3 bg-slate-50/80 border-b border-slate-100 flex items-center justify-between">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Peran Aktif</span>
                @if(auth()->user()->isSuperAdmin())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                        Superadmin
                    </span>
                @elseif(auth()->user()->isAdminKeuangan())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-teal-100 text-teal-900 border border-teal-300">
                        Admin Keuangan
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-[#346733] border border-emerald-300">
                        Jama'ah
                    </span>
                @endif
            </div>

            <!-- Sidebar Navigation Links (Scrollable jika layar pendek) -->
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
                <!-- Tombol Pusat Notifikasi & Antrean (Sidebar Quick Trigger) -->
                <button type="button" @click="notificationsOpen = true"
                        class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-2xl text-xs font-bold transition text-slate-700 bg-slate-100/90 hover:bg-amber-50 hover:text-amber-900 hover:border-amber-300 border border-slate-200 cursor-pointer shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <div class="relative">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @if(($notificationsData['total_count'] ?? 0) > 0)
                                <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-ping"></span>
                            @endif
                        </div>
                        <span>Pusat Notifikasi</span>
                    </div>
                    @if(($notificationsData['total_count'] ?? 0) > 0)
                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-black bg-red-600 text-white animate-pulse">
                            {{ $notificationsData['total_count'] }}
                        </span>
                    @else
                        <span class="text-[10px] font-semibold text-slate-400">0</span>
                    @endif
                </button>

                @if($isStaff)
                    <!-- Menu Admin & Keuangan -->
                    <div>
                        <div class="px-3 mb-2 text-[10px] font-extrabold tracking-wider text-slate-400 uppercase">
                            Admin & Keuangan
                        </div>
                        <nav class="space-y-1">
                            <!-- Dashboard Admin -->
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('admin.dashboard') ? 'bg-[#346733] text-white shadow-sm' : 'text-slate-600 hover:text-[#346733] hover:bg-slate-100' }}">
                                <svg class="w-4 h-4 {{ request()->routeIs('admin.dashboard') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span>Dashboard Admin</span>
                            </a>

                            <!-- Approval Pendaftaran Kloter -->
                            <a href="{{ route('admin.registrations.index') }}" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('admin.registrations.*') ? 'bg-[#346733] text-white shadow-sm' : 'text-slate-600 hover:text-[#346733] hover:bg-slate-100' }}">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 {{ request()->routeIs('admin.registrations.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                                    <span>Approval Kloter</span>
                                </div>
                                @if($pendingRegistrationsCount > 0)
                                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-black {{ request()->routeIs('admin.registrations.*') ? 'bg-amber-400 text-slate-900' : 'bg-amber-500 text-white' }}">
                                        {{ $pendingRegistrationsCount }}
                                    </span>
                                @endif
                            </a>

                            <!-- Approval Pembayaran -->
                            <a href="{{ route('admin.payments.index') }}" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('admin.payments.*') ? 'bg-[#346733] text-white shadow-sm' : 'text-slate-600 hover:text-[#346733] hover:bg-slate-100' }}">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 {{ request()->routeIs('admin.payments.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Approval Pembayaran</span>
                                </div>
                                @if($pendingPaymentsCount > 0)
                                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-black {{ request()->routeIs('admin.payments.*') ? 'bg-amber-400 text-slate-900' : 'bg-amber-500 text-white' }}">
                                        {{ $pendingPaymentsCount }}
                                    </span>
                                @endif
                            </a>

                            <!-- Master Kloter -->
                            <a href="{{ route('admin.kloters.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('admin.kloters.*') ? 'bg-[#346733] text-white shadow-sm' : 'text-slate-600 hover:text-[#346733] hover:bg-slate-100' }}">
                                <svg class="w-4 h-4 {{ request()->routeIs('admin.kloters.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                <span>Master Kloter</span>
                            </a>
                        </nav>
                    </div>
                @endif

                <!-- Menu Tabungan Jama'ah -->
                <div>
                    <div class="px-3 mb-2 text-[10px] font-extrabold tracking-wider text-slate-400 uppercase">
                        {{ $isStaff ? 'Portal Tabungan Saya' : 'Menu Jama\'ah' }}
                    </div>
                    <nav class="space-y-1">
                        <!-- Tabungan Saya -->
                        <a href="{{ route('jamaah.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('jamaah.dashboard') ? 'bg-[#346733] text-white shadow-sm' : 'text-slate-600 hover:text-[#346733] hover:bg-slate-100' }}">
                            <svg class="w-4 h-4 {{ request()->routeIs('jamaah.dashboard') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            <span>Tabungan Saya</span>
                        </a>

                        <!-- Tagihan Bulanan -->
                        <a href="{{ route('jamaah.invoices.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('jamaah.invoices.*') ? 'bg-[#346733] text-white shadow-sm' : 'text-slate-600 hover:text-[#346733] hover:bg-slate-100' }}">
                            <svg class="w-4 h-4 {{ request()->routeIs('jamaah.invoices.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                            <span>Tagihan Bulanan</span>
                        </a>

                        <!-- Data Keluarga -->
                        <a href="{{ route('jamaah.family.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('jamaah.family.*') ? 'bg-[#346733] text-white shadow-sm' : 'text-slate-600 hover:text-[#346733] hover:bg-slate-100' }}">
                            <svg class="w-4 h-4 {{ request()->routeIs('jamaah.family.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            <span>Data Keluarga</span>
                        </a>

                        <!-- Daftar Kloter Baru -->
                        <a href="{{ route('jamaah.registrations.create') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-bold transition {{ request()->routeIs('jamaah.registrations.*') ? 'bg-[#007C6A] text-white shadow-sm' : 'text-teal-700 hover:bg-teal-50' }}">
                            <svg class="w-4 h-4 {{ request()->routeIs('jamaah.registrations.*') ? 'text-white' : 'text-teal-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Daftar Kloter Baru</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Footer Sidebar: User Profile & Logout -->
            <div class="p-4 border-t border-slate-200 bg-white">
                <div class="flex items-center justify-between gap-2">
                    <div class="overflow-hidden">
                        <span class="block text-xs font-bold text-slate-900 truncate">{{ auth()->user()->name }}</span>
                        <span class="block text-[11px] text-slate-400 truncate">{{ auth()->user()->email }}</span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" title="Keluar dari akun" class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- ========================================================================= -->
        <!-- 2. MOBILE TOPBAR (Tampil hanya di layar Mobile < lg)                       -->
        <!-- ========================================================================= -->
        <header class="lg:hidden sticky top-0 z-40 bg-white border-b border-slate-200/90 shadow-sm backdrop-blur-md bg-white/95 h-14 flex items-center justify-between px-4">
            <a href="{{ $isStaff ? route('admin.dashboard') : route('jamaah.dashboard') }}" class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-[#346733] flex items-center justify-center text-[#D4AF37] shadow-sm">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                        <path d="M12 2L3 8v12h18V8L12 2zm0 3.2L18.4 9H5.6L12 5.2zM5 11h14v7H5v-7zm7 1.5c-1.38 0-2.5 1.12-2.5 2.5s1.12 2.5 2.5 2.5 2.5-1.12 2.5-2.5-1.12-2.5-2.5-2.5z"/>
                    </svg>
                </div>
                <div>
                    <span class="text-sm font-extrabold text-[#346733] leading-none block">Menuju Haramain</span>
                    <span class="text-[9px] font-semibold text-slate-400 uppercase tracking-wider block">Tabungan Umroh</span>
                </div>
            </a>

            <div class="flex items-center gap-2">
                @if(auth()->user()->isSuperAdmin())
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">Superadmin</span>
                @elseif(auth()->user()->isAdminKeuangan())
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-100 text-teal-900 border border-teal-300">Keuangan</span>
                @else
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-[#346733] border border-emerald-300">Jama'ah</span>
                @endif

                <!-- Tombol Notifikasi Mobile Topbar -->
                <button type="button" @click="notificationsOpen = true" class="relative p-1.5 rounded-lg text-slate-600 hover:bg-slate-100 transition cursor-pointer" title="Pusat Notifikasi & Antrean">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    @if(($notificationsData['total_count'] ?? 0) > 0)
                        <span class="absolute top-0 right-0 bg-red-600 text-white text-[9px] font-black min-w-4 h-4 px-1 rounded-full flex items-center justify-center shadow-xs">
                            {{ $notificationsData['total_count'] > 99 ? '99+' : $notificationsData['total_count'] }}
                        </span>
                    @endif
                </button>

                <!-- Tombol Buka Menu / Switcher Mobile -->
                <button type="button" @click.stop="mobileDrawerOpen = !mobileDrawerOpen" class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100 transition cursor-pointer" title="Menu Lengkap">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </header>

        <!-- ========================================================================= -->
        <!-- 3. MOBILE BOTTOM NAVIGATION BAR (Thumb-friendly, fixed bottom)            -->
        <!-- ========================================================================= -->
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-50 bg-white/95 backdrop-blur-md border-t border-slate-200/90 shadow-[0_-4px_12px_rgba(0,0,0,0.05)] h-16">
            <div class="grid {{ $isStaff ? 'grid-cols-5' : 'grid-cols-4' }} h-full max-w-lg mx-auto">
                @if($isStaff)
                    <!-- 1. Admin Dashboard -->
                    <a href="{{ route('admin.dashboard') }}" class="flex flex-col items-center justify-center py-1 transition {{ request()->routeIs('admin.dashboard') ? 'text-[#346733]' : 'text-slate-400 hover:text-slate-700' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span class="text-[10px] font-bold mt-1">Admin</span>
                    </a>

                    <!-- 2. Approval Pembayaran (dengan badge dot) -->
                    <a href="{{ route('admin.payments.index') }}" class="flex flex-col items-center justify-center py-1 relative transition {{ request()->routeIs('admin.payments.*') ? 'text-[#346733]' : 'text-slate-400 hover:text-slate-700' }}">
                        <div class="relative">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @if($pendingPaymentsCount > 0)
                                <span class="absolute -top-1 -right-2 bg-amber-500 text-white text-[9px] font-black w-4 h-4 rounded-full flex items-center justify-center">
                                    {{ $pendingPaymentsCount }}
                                </span>
                            @endif
                        </div>
                        <span class="text-[10px] font-bold mt-1">Approval</span>
                    </a>

                    <!-- 3. Master Kloter -->
                    <a href="{{ route('admin.kloters.index') }}" class="flex flex-col items-center justify-center py-1 transition {{ request()->routeIs('admin.kloters.*') ? 'text-[#346733]' : 'text-slate-400 hover:text-slate-700' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span class="text-[10px] font-bold mt-1">Kloter</span>
                    </a>

                    <!-- 4. Tabungan Saya (Dual-role) -->
                    <a href="{{ route('jamaah.dashboard') }}" class="flex flex-col items-center justify-center py-1 transition {{ request()->routeIs('jamaah.dashboard') ? 'text-[#346733]' : 'text-slate-400 hover:text-slate-700' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <span class="text-[10px] font-bold mt-1">Tabungan</span>
                    </a>

                    <!-- 5. Menu Lainnya & Demo Switcher -->
                    <button type="button" @click.stop="mobileDrawerOpen = !mobileDrawerOpen" class="flex flex-col items-center justify-center py-1 text-slate-400 hover:text-slate-700 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                        <span class="text-[10px] font-bold mt-1">Lainnya</span>
                    </button>
                @else
                    <!-- Mode Jama'ah Murni: 4 Tombol Bottom Bar -->
                    <!-- 1. Beranda -->
                    <a href="{{ route('jamaah.dashboard') }}" class="flex flex-col items-center justify-center py-1 transition {{ request()->routeIs('jamaah.dashboard') ? 'text-[#346733]' : 'text-slate-400 hover:text-slate-700' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span class="text-[10px] font-bold mt-1">Beranda</span>
                    </a>

                    <!-- 2. Tagihan -->
                    <a href="{{ route('jamaah.invoices.index') }}" class="flex flex-col items-center justify-center py-1 transition {{ request()->routeIs('jamaah.invoices.*') ? 'text-[#346733]' : 'text-slate-400 hover:text-slate-700' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                        <span class="text-[10px] font-bold mt-1">Tagihan</span>
                    </a>

                    <!-- 3. Keluarga -->
                    <a href="{{ route('jamaah.family.index') }}" class="flex flex-col items-center justify-center py-1 transition {{ request()->routeIs('jamaah.family.*') ? 'text-[#346733]' : 'text-slate-400 hover:text-slate-700' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span class="text-[10px] font-bold mt-1">Keluarga</span>
                    </a>

                    <!-- 4. Akun & Menu Lainnya -->
                    <button type="button" @click.stop="mobileDrawerOpen = !mobileDrawerOpen" class="flex flex-col items-center justify-center py-1 text-slate-400 hover:text-slate-700 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="text-[10px] font-bold mt-1">Akun</span>
                    </button>
                @endif
            </div>
        </nav>

        <!-- ========================================================================= -->
        <!-- 4. MOBILE SLIDE-OVER DRAWER (Akses Menu Tambahan, Demo Switcher, Logout)   -->
        <!-- ========================================================================= -->
        <div x-show="mobileDrawerOpen" 
             x-cloak 
             @keydown.escape.window="mobileDrawerOpen = false" 
             class="relative z-50 lg:hidden" 
             aria-labelledby="slide-over-title" 
             role="dialog" 
             aria-modal="true">
            <!-- Backdrop gelap & blur (klik di luar sidebar untuk menutup) -->
            <div x-show="mobileDrawerOpen" 
                 x-transition:enter="ease-in-out duration-300" 
                 x-transition:enter-start="opacity-0" 
                 x-transition:enter-end="opacity-100" 
                 x-transition:leave="ease-in-out duration-300" 
                 x-transition:leave-start="opacity-100" 
                 x-transition:leave-end="opacity-0" 
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity cursor-pointer" 
                 @click="mobileDrawerOpen = false"
                 aria-hidden="true"></div>

            <div class="fixed inset-0 overflow-hidden pointer-events-none" @click.self="mobileDrawerOpen = false">
                <div class="absolute inset-0 overflow-hidden pointer-events-none" @click.self="mobileDrawerOpen = false">
                    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div x-show="mobileDrawerOpen" 
                             x-transition:enter="transform transition ease-in-out duration-300" 
                             x-transition:enter-start="translate-x-full" 
                             x-transition:enter-end="translate-x-0" 
                             x-transition:leave="transform transition ease-in-out duration-300" 
                             x-transition:leave-start="translate-x-0" 
                             x-transition:leave-end="translate-x-full" 
                             @click.outside="mobileDrawerOpen = false"
                             class="pointer-events-auto w-screen max-w-xs bg-white shadow-2xl flex flex-col justify-between">
                            <div class="p-5 overflow-y-auto">
                                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                                    <div>
                                        <h3 class="font-bold text-slate-900 text-sm">{{ auth()->user()->name }}</h3>
                                        <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>
                                    </div>
                                    <button type="button" @click="mobileDrawerOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                <!-- Menu Ekstra Mobile -->
                                <div class="py-4 space-y-2">
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Navigasi Utama</div>
                                    <!-- Tombol Buka Modal Notifikasi dari Drawer -->
                                    <button type="button" @click="mobileDrawerOpen = false; notificationsOpen = true" class="w-full text-left px-3 py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 flex items-center justify-between transition cursor-pointer mb-2">
                                        <div class="flex items-center gap-2.5">
                                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                            <span>Pusat Notifikasi & Tagihan</span>
                                        </div>
                                        @if(($notificationsData['total_count'] ?? 0) > 0)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-red-600 text-white">
                                                {{ $notificationsData['total_count'] }}
                                            </span>
                                        @else
                                            <span class="text-[10px] text-slate-400 font-semibold">0</span>
                                        @endif
                                    </button>

                                    @if($isStaff)
                                        <a href="{{ route('admin.dashboard') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Dashboard Admin</a>
                                        <a href="{{ route('admin.registrations.index') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Approval Kloter ({{ $pendingRegistrationsCount }})</a>
                                        <a href="{{ route('admin.payments.index') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Approval Pembayaran ({{ $pendingPaymentsCount }})</a>
                                        <a href="{{ route('admin.kloters.index') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Master Kloter</a>
                                    @endif
                                    <a href="{{ route('jamaah.dashboard') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Tabungan Saya</a>
                                    <a href="{{ route('jamaah.invoices.index') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Tagihan Bulanan</a>
                                    <a href="{{ route('jamaah.family.index') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50">Data Anggota Keluarga</a>
                                    <a href="{{ route('jamaah.registrations.create') }}" class="block px-3 py-2 rounded-lg text-xs font-bold text-teal-700 bg-teal-50">Daftar Kloter Baru</a>
                                    <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center justify-between">
                                        <span>Profil Saya</span>
                                        <span class="text-[10px] text-slate-400">Ubah Akun &rarr;</span>
                                    </a>
                                </div>
                            </div>

                            <div class="p-5 border-t border-slate-100 bg-slate-50">
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full py-2.5 px-4 text-center text-xs font-bold text-red-600 bg-red-100 rounded-xl hover:bg-red-200 transition">
                                        Keluar dari Akun (Logout)
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- 4B. MODAL PUSAT NOTIFIKASI & ANTREAN (Interaktif Popup)                   -->
        <!-- ========================================================================= -->
        <div x-show="notificationsOpen"
             x-cloak
             @keydown.escape.window="notificationsOpen = false"
             class="fixed inset-0 z-50 overflow-y-auto"
             aria-labelledby="modal-notifications-title"
             role="dialog"
             aria-modal="true">
            
            <!-- Backdrop Blur Overlay (Klik luar menutup modal) -->
            <div x-show="notificationsOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
                 @click="notificationsOpen = false"
                 aria-hidden="true"></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <div x-show="notificationsOpen"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     @click.outside="notificationsOpen = false"
                     class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 w-full max-w-2xl border border-slate-200 flex flex-col max-h-[85vh]">
                    
                    <!-- Modal Header -->
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shadow-xs">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900" id="modal-notifications-title">
                                    Pusat Notifikasi & Antrean
                                </h3>
                                <p class="text-xs text-slate-500">
                                    @if(($notificationsData['total_count'] ?? 0) > 0)
                                        Ada <span class="font-bold text-slate-800">{{ $notificationsData['total_count'] }}</span> hal yang memerlukan perhatian atau tindakan Anda.
                                    @else
                                        Semua tagihan dan verifikasi Anda saat ini sudah selesai.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <button type="button" @click="notificationsOpen = false" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer">
                            <span class="sr-only">Tutup</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Modal Body (Scrollable) -->
                    <div class="px-6 py-4 overflow-y-auto space-y-6 flex-1">
                        @if(($notificationsData['total_count'] ?? 0) == 0)
                            <!-- Empty State -->
                            <div class="py-12 text-center">
                                <div class="w-16 h-16 bg-emerald-100 text-[#346733] rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <h4 class="text-sm font-bold text-slate-800 mb-1">Tidak Ada Notifikasi Baru</h4>
                                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                                    Alhamdulillah! Tidak ada antrean approval atau tagihan aktif yang tertunda saat ini.
                                </p>
                            </div>
                        @else
                            <!-- 1. Section Approval Pendaftaran Kloter (Untuk Superadmin & Keuangan) -->
                            @if(($notificationsData['approval_registrations_count'] ?? 0) > 0)
                                <div>
                                    <div class="flex items-center justify-between mb-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Approval Pendaftaran Kloter</h4>
                                        </div>
                                        <span class="text-[11px] font-bold px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full">
                                            {{ $notificationsData['approval_registrations_count'] }} Menunggu
                                        </span>
                                    </div>
                                    <div class="space-y-2">
                                        @foreach($notificationsData['approval_registrations'] as $reg)
                                            <div class="p-3 bg-amber-50/70 border border-amber-200/80 rounded-2xl flex items-center justify-between gap-3">
                                                <div>
                                                    <div class="text-xs font-bold text-slate-800">{{ $reg->user->name ?? 'Jamaah' }}</div>
                                                    <div class="text-[11px] text-slate-500">
                                                        Kloter: <span class="font-semibold text-slate-700">{{ $reg->kloter->name ?? '-' }}</span> ({{ $reg->total_pax }} Pax)
                                                    </div>
                                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                                        Daftar: {{ $reg->created_at ? $reg->created_at->diffForHumans() : '-' }}
                                                    </div>
                                                </div>
                                                <a href="{{ route('admin.registrations.index') }}" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-xs whitespace-nowrap transition">
                                                    Tinjau &rarr;
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- 2. Section Approval Pembayaran (Untuk Superadmin & Keuangan) -->
                            @if(($notificationsData['approval_payments_count'] ?? 0) > 0)
                                <div>
                                    <div class="flex items-center justify-between mb-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full bg-teal-500"></span>
                                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Approval Bukti Transfer</h4>
                                        </div>
                                        <span class="text-[11px] font-bold px-2 py-0.5 bg-teal-100 text-teal-800 rounded-full">
                                            {{ $notificationsData['approval_payments_count'] }} Menunggu
                                        </span>
                                    </div>
                                    <div class="space-y-2">
                                        @foreach($notificationsData['approval_payments'] as $payment)
                                            <div class="p-3 bg-teal-50/70 border border-teal-200/80 rounded-2xl flex items-center justify-between gap-3">
                                                <div>
                                                    <div class="text-xs font-bold text-slate-800">{{ $payment->user->name ?? 'Jamaah' }}</div>
                                                    <div class="text-[11px] text-slate-600">
                                                        Nominal: <span class="font-bold text-[#346733]">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span>
                                                    </div>
                                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                                        Invoice: {{ $payment->invoice->invoice_number ?? '-' }} &bull; {{ $payment->created_at ? $payment->created_at->diffForHumans() : '-' }}
                                                    </div>
                                                </div>
                                                <a href="{{ route('admin.payments.index') }}" class="px-3 py-1.5 rounded-xl bg-[#007C6A] hover:bg-teal-700 text-white text-xs font-bold shadow-xs whitespace-nowrap transition">
                                                    Verifikasi &rarr;
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- 3. Section Tagihan Umroh Belum Lunas (Personal Jamaah / Dual-Role) -->
                            @if(($notificationsData['unpaid_invoices_count'] ?? 0) > 0)
                                <div>
                                    <div class="flex items-center justify-between mb-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Tagihan Tabungan Belum Lunas</h4>
                                        </div>
                                        <span class="text-[11px] font-bold px-2 py-0.5 bg-rose-100 text-rose-800 rounded-full">
                                            {{ $notificationsData['unpaid_invoices_count'] }} Tagihan
                                        </span>
                                    </div>
                                    <div class="space-y-2">
                                        @foreach($notificationsData['unpaid_invoices'] as $invoice)
                                            @php
                                                $remaining = max(0, $invoice->total_amount - $invoice->paid_amount);
                                                $monthNames = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
                                                $periodName = ($monthNames[$invoice->billing_month] ?? 'Bulan '.$invoice->billing_month).' '.$invoice->billing_year;
                                            @endphp
                                            <div class="p-3 bg-rose-50/60 border border-rose-200/80 rounded-2xl flex items-center justify-between gap-3">
                                                <div>
                                                    <div class="text-xs font-bold text-slate-800">{{ $periodName }}</div>
                                                    <div class="text-[11px] text-slate-600">
                                                        Sisa: <span class="font-extrabold text-rose-600">Rp {{ number_format($remaining, 0, ',', '.') }}</span>
                                                    </div>
                                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                                        Kloter: {{ $invoice->registration->kloter->name ?? '-' }} &bull; Jatuh Tempo: {{ \Carbon\Carbon::parse($invoice->due_date)->translatedFormat('d M Y') }}
                                                    </div>
                                                </div>
                                                <a href="{{ route('jamaah.invoices.show', $invoice) }}" class="px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-xs whitespace-nowrap transition">
                                                    Bayar &rarr;
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- 4. Section Bukti Transfer Sedang Diverifikasi (Personal) -->
                            @if(($notificationsData['pending_payments_count'] ?? 0) > 0)
                                <div>
                                    <div class="flex items-center justify-between mb-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Pembayaran Dalam Proses Review</h4>
                                        </div>
                                        <span class="text-[11px] font-bold px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full">
                                            {{ $notificationsData['pending_payments_count'] }} Menunggu
                                        </span>
                                    </div>
                                    <div class="space-y-2">
                                        @foreach($notificationsData['pending_payments'] as $p)
                                            <div class="p-3 bg-blue-50/60 border border-blue-200/80 rounded-2xl flex items-center justify-between gap-3">
                                                <div>
                                                    <div class="text-xs font-bold text-slate-800">
                                                        Rp {{ number_format($p->amount, 0, ',', '.') }}
                                                    </div>
                                                    <div class="text-[11px] text-slate-600">
                                                        Tagihan: {{ $p->invoice->invoice_number ?? '-' }}
                                                    </div>
                                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                                        Bukti transfer telah dikirim dan sedang ditinjau oleh Admin Keuangan.
                                                    </div>
                                                </div>
                                                <span class="px-2.5 py-1 rounded-xl bg-blue-100 text-blue-800 text-[11px] font-bold whitespace-nowrap">
                                                    Diproses
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- 5. Section Status Pendaftaran Kloter Jamaah -->
                            @if(($notificationsData['registration_alerts_count'] ?? 0) > 0)
                                <div>
                                    <div class="flex items-center justify-between mb-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Status Pendaftaran Kloter</h4>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        @foreach($notificationsData['registration_alerts'] as $alert)
                                            <div class="p-3 {{ $alert->status === \App\Models\KloterRegistration::STATUS_PENDING ? 'bg-amber-50/70 border-amber-200' : 'bg-red-50/70 border-red-200' }} border rounded-2xl">
                                                <div class="flex items-center justify-between">
                                                    <div class="text-xs font-bold text-slate-800">
                                                        {{ $alert->kloter->name ?? 'Kloter' }}
                                                    </div>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $alert->status === \App\Models\KloterRegistration::STATUS_PENDING ? 'bg-amber-200 text-amber-900' : 'bg-red-200 text-red-900' }}">
                                                        {{ $alert->status === \App\Models\KloterRegistration::STATUS_PENDING ? 'Menunggu Approval' : 'Ditolak' }}
                                                    </span>
                                                </div>
                                                @if($alert->rejection_note)
                                                    <div class="text-[11px] text-red-700 mt-1">
                                                        Alasan penolakan: {{ $alert->rejection_note }}
                                                    </div>
                                                @else
                                                    <div class="text-[10px] text-slate-500 mt-0.5">
                                                        Pendaftaran kloter Anda sedang dalam antrean verifikasi Admin atau Superadmin.
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs text-slate-400">Pusat Notifikasi Menuju Haramain</span>
                        <button type="button" @click="notificationsOpen = false" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endauth

    <!-- ========================================================================= -->
    <!-- 5. MAIN CONTENT WRAPPER (Responsive Margin Desktop & Padding Mobile)       -->
    <!-- ========================================================================= -->
    <div class="{{ auth()->check() ? 'lg:pl-64' : '' }} flex flex-col min-h-screen">
        @auth
            <!-- Desktop Topbar Minimalis -->
            <header class="hidden lg:flex sticky top-0 z-30 bg-white/90 backdrop-blur-md border-b border-slate-200/80 h-16 items-center justify-between px-8">
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="font-medium">Assalamu'alaikum,</span>
                    <span class="font-bold text-slate-800">{{ auth()->user()->name }}</span>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Tombol Lonceng Notifikasi Desktop Modal Trigger -->
                    <button @click="notificationsOpen = true" type="button"
                            class="relative inline-flex items-center justify-center p-2 rounded-xl border border-slate-200 hover:border-slate-300 hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition cursor-pointer bg-white shadow-xs"
                            title="Buka Pusat Notifikasi & Antrean">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        @if(($notificationsData['total_count'] ?? 0) > 0)
                            <span class="absolute -top-1 -right-1 bg-red-600 text-white text-[9px] font-black min-w-4 h-4 px-1 rounded-full flex items-center justify-center shadow-xs animate-pulse">
                                {{ $notificationsData['total_count'] > 99 ? '99+' : $notificationsData['total_count'] }}
                            </span>
                        @endif
                    </button>

                    <!-- Dropdown Menu Profil Pengguna -->
                    <div class="relative" x-data="{ profileOpen: false }">
                        <button @click="profileOpen = !profileOpen" type="button"
                                class="flex items-center gap-2 py-1 px-2.5 rounded-xl border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition cursor-pointer bg-white shadow-xs">
                            <div class="w-7 h-7 rounded-lg bg-emerald-100 text-[#346733] font-black text-xs flex items-center justify-center">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                            <span class="text-xs font-bold text-slate-800 max-w-[130px] truncate">{{ auth()->user()->name }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div x-show="profileOpen" @click.outside="profileOpen = false" x-cloak
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="origin-top-right absolute right-0 mt-2 w-60 rounded-2xl shadow-xl bg-white border border-slate-200 py-2 z-50">

                            <!-- Header Info Profil -->
                            <div class="px-4 py-2.5 border-b border-slate-100">
                                <p class="text-xs font-bold text-slate-900 truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[11px] text-slate-500 truncate">{{ auth()->user()->email }}</p>
                                <div class="mt-1.5">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold {{ auth()->user()->isStaff() ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-[#346733]' }}">
                                        {{ auth()->user()->role === 'superadmin' ? 'Superadmin' : (auth()->user()->role === 'admin_keuangan' ? 'Admin Keuangan' : 'Jama\'ah Tabungan') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Link ke Halaman Profil Lengkap -->
                            <div class="py-1">
                                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-[#346733] transition">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <span>Profil Saya (Ubah Akun)</span>
                                </a>
                            </div>

                            <!-- Tombol Logout di dalam Dropdown -->
                            <div class="pt-1 border-t border-slate-100">
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full text-left flex items-center gap-2.5 px-4 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition cursor-pointer">
                                        <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                        <span>Keluar dari Akun</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
        @else
            <!-- Guest Topbar -->
            <header class="sticky top-0 z-40 bg-white border-b border-slate-200/80 shadow-sm h-16 flex items-center justify-between px-6 max-w-7xl mx-auto w-full">
                <a href="{{ route('login') }}" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-[#346733] flex items-center justify-center text-[#D4AF37] shadow-sm">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 2L3 8v12h18V8L12 2zm0 3.2L18.4 9H5.6L12 5.2zM5 11h14v7H5v-7zm7 1.5c-1.38 0-2.5 1.12-2.5 2.5s1.12 2.5 2.5 2.5 2.5-1.12 2.5-2.5-1.12-2.5-2.5-2.5z"/></svg>
                    </div>
                    <span class="text-base font-extrabold text-[#346733]">Menuju Haramain</span>
                </a>
                <div class="flex items-center gap-3">
                    <a href="{{ route('login') }}" class="text-xs font-bold text-slate-600 hover:text-[#346733]">Masuk</a>
                    <a href="{{ route('register') }}" class="px-4 py-2 rounded-xl bg-[#346733] text-white text-xs font-bold shadow hover:bg-[#234622] transition">Daftar Akun</a>
                </div>
            </header>
        @endauth

        <!-- Main Content (pb-24 di mobile agar tidak tertutup bottom bar) -->
        <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 {{ auth()->check() ? 'pb-24 lg:pb-8' : '' }}">
            <!-- Flash Alert Messages -->
            @if(session('success'))
                <div class="mb-6 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800 flex items-start gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <div class="flex-1 font-medium">{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 p-4 text-sm text-red-800 flex items-start gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="flex-1 font-medium">{{ session('error') }}</div>
                </div>
            @endif

            @if(session('info'))
                <div class="mb-6 rounded-2xl bg-cyan-50 border border-cyan-200 p-4 text-sm text-cyan-800 flex items-start gap-3 shadow-sm">
                    <svg class="w-5 h-5 text-cyan-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="flex-1 font-medium">{{ session('info') }}</div>
                </div>
            @endif

            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="mt-auto bg-white border-t border-slate-200/80 py-5 text-xs text-slate-500 {{ auth()->check() ? 'hidden lg:block' : '' }}">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left">
                <div class="flex items-center justify-center sm:justify-start gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#346733]"></span>
                    <span class="font-bold text-[#346733]">Menuju Haramain</span>
                    <span>&bull; Sistem Tabungan & Angsuran Umroh Terencana</span>
                </div>
                <div>
                    Desain Presisi &bull; Bebas Riba &bull; Amanah Terjamin
                </div>
            </div>
        </footer>
    </div>

    <!-- ========================================================================= -->
    <!-- 6. GLOBAL PROOF VIEWER & INTERACTIVE ZOOM MODAL                           -->
    <!-- ========================================================================= -->
    <div x-data="proofModalViewer()"
         @open-proof-modal.window="openModal($event.detail)"
         @keydown.escape.window="closeModal()"
         x-cloak>

        <!-- Backdrop Gelap & Touch Outside Handler -->
        <div x-show="isOpen"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-auto bg-slate-950/85 backdrop-blur-md flex flex-col items-center justify-center p-2 sm:p-4 select-none"
             @click.self="closeModal()">

            <!-- Floating Top Bar Controls (Tombol X & Info) -->
            <div class="fixed top-3 right-3 sm:top-5 sm:right-6 z-50 flex items-center gap-3">
                <!-- Status Badge Zoom -->
                <button type="button"
                        @click.stop="toggleZoom()"
                        class="px-3 py-1.5 rounded-full text-xs font-bold text-white bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/20 shadow-lg flex items-center gap-1.5 transition cursor-pointer">
                    <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!isZoomed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/>
                        <path x-show="isZoomed" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/>
                    </svg>
                    <span x-text="isZoomed ? 'Perkecil (Normal)' : 'Klik Gambar / Zoom Detail'"></span>
                </button>

                <!-- Tombol X Tutup Modal -->
                <button type="button"
                        @click.stop="closeModal()"
                        class="w-10 h-10 rounded-full bg-white/15 hover:bg-red-600 text-white backdrop-blur-md border border-white/20 flex items-center justify-center shadow-xl transition-all duration-200 hover:scale-105 active:scale-95 cursor-pointer"
                        title="Tutup Bukti (Esc atau Sentuh di luar)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Petunjuk di Bawah Gambar -->
            <div class="fixed bottom-3 inset-x-0 text-center pointer-events-none z-50">
                <span class="inline-block px-3 py-1 rounded-full text-[11px] font-medium text-white/70 bg-black/40 backdrop-blur-sm border border-white/10">
                    Sentuh di luar gambar atau tekan <kbd class="px-1.5 py-0.5 rounded bg-white/20 text-white font-mono text-[10px]">X / Esc</kbd> untuk keluar
                </span>
            </div>

            <!-- Image Viewport Container (Klik area luar gambar akan menutup modal) -->
            <div class="w-full h-full flex items-center justify-center overflow-auto p-4"
                 @click="if ($event.target === $el) closeModal()">

                <div class="relative transition-transform duration-300 ease-out origin-center"
                     :class="isZoomed ? 'scale-150 sm:scale-[2.0] my-24 sm:my-32' : 'scale-100 my-auto'">

                    <img :src="imageUrl"
                         :alt="title"
                         @click.stop="toggleZoom()"
                         class="max-h-[80vh] max-w-[90vw] sm:max-w-2xl rounded-2xl shadow-2xl border border-white/20 object-contain transition-all duration-200"
                         :class="isZoomed ? 'cursor-zoom-out ring-4 ring-amber-400/50' : 'cursor-zoom-in hover:brightness-105'"
                         :title="isZoomed ? 'Klik untuk memperkecil' : 'Klik untuk memperbesar / zoom detail'"
                    >
                </div>
            </div>
        </div>
    </div>

    <script>
        function proofModalViewer() {
            return {
                isOpen: false,
                imageUrl: '',
                title: '',
                isZoomed: false,
                openModal(data) {
                    if (!data || !data.url) return;
                    this.imageUrl = data.url;
                    this.title = data.title || 'Bukti Transfer';
                    this.isZoomed = false;
                    this.isOpen = true;
                    document.body.classList.add('overflow-hidden');
                },
                closeModal() {
                    this.isOpen = false;
                    this.isZoomed = false;
                    this.imageUrl = '';
                    document.body.classList.remove('overflow-hidden');
                },
                toggleZoom() {
                    this.isZoomed = !this.isZoomed;
                }
            }
        }
    </script>

    @stack('scripts')
</body>
</html>
