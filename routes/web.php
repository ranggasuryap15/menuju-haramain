<?php
/**
 * File: routes/web.php
 * Tujuan: Definisi rute web aplikasi Tabungan Umroh (Autentikasi guest, Portal Jamaah, Portal Admin Keuangan & Superadmin)
 * Dipakai Oleh: Laravel Routing Kernel
 * Dependensi Utama: AuthController, Jamaah\* Controllers, Admin\* Controllers
 * Daftar Rute Utama: /login, /register (guest), /profile, /jamaah/*, /admin/*
 * Side Effect: Penanganan HTTP request dan proteksi middleware guest, auth, serta role
 */

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\KloterController;
use App\Http\Controllers\Admin\PaymentApprovalController;
use App\Http\Controllers\Admin\RegistrationApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Jamaah\DashboardController as JamaahDashboardController;
use App\Http\Controllers\Jamaah\FamilyMemberController;
use App\Http\Controllers\Jamaah\InvoiceController;
use App\Http\Controllers\Jamaah\KloterRegistrationController;
use App\Http\Controllers\Jamaah\PaymentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Redirect root ke dashboard atau login
Route::get('/', function () {
    if (Auth::check()) {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return $user->isStaff()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('jamaah.dashboard');
    }
    return redirect()->route('login');
});

// Autentikasi & Registrasi Calon Jamaah (Hanya untuk Tamu / Guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Logout
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Profil Pengguna (Dapat diakses oleh Jamaah, Admin Keuangan, & Superadmin)
Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
});

// Area Jama'ah (Semua pengguna terotentikasi, termasuk admin yang mendaftarkan keluarganya)
Route::middleware(['auth'])->prefix('jamaah')->name('jamaah.')->group(function () {
    Route::get('/dashboard', [JamaahDashboardController::class, 'index'])->name('dashboard');

    // Manajemen Anggota Keluarga
    Route::get('/family', [FamilyMemberController::class, 'index'])->name('family.index');
    Route::post('/family', [FamilyMemberController::class, 'store'])->name('family.store');
    Route::delete('/family/{familyMember}', [FamilyMemberController::class, 'destroy'])->name('family.destroy');

    // Pendaftaran Kloter
    Route::get('/registrations/create', [KloterRegistrationController::class, 'create'])->name('registrations.create');
    Route::post('/registrations', [KloterRegistrationController::class, 'store'])->name('registrations.store');

    // Tagihan & Pembayaran
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
});

// Area Admin Keuangan & Superadmin
Route::middleware(['auth', 'role:superadmin,admin_keuangan'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Antrean Approval Bukti Pembayaran
    Route::get('/payments', [PaymentApprovalController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [PaymentApprovalController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/approve', [PaymentApprovalController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{payment}/reject', [PaymentApprovalController::class, 'reject'])->name('payments.reject');

    // Antrean Approval Pendaftaran Kloter
    Route::get('/registrations', [RegistrationApprovalController::class, 'index'])->name('registrations.index');
    Route::get('/registrations/{registration}', [RegistrationApprovalController::class, 'show'])->name('registrations.show');
    Route::post('/registrations/{registration}/approve', [RegistrationApprovalController::class, 'approve'])->name('registrations.approve');
    Route::post('/registrations/{registration}/reject', [RegistrationApprovalController::class, 'reject'])->name('registrations.reject');

    // Master Kloter & Penjadwalan Tagihan
    Route::get('/kloters', [KloterController::class, 'index'])->name('kloters.index');
    Route::get('/kloters/create', [KloterController::class, 'create'])->name('kloters.create');
    Route::post('/kloters', [KloterController::class, 'store'])->name('kloters.store');
    Route::get('/kloters/{kloter}', [KloterController::class, 'show'])->name('kloters.show');
    Route::get('/kloters/{kloter}/edit', [KloterController::class, 'edit'])->name('kloters.edit');
    Route::put('/kloters/{kloter}', [KloterController::class, 'update'])->name('kloters.update');
    Route::post('/kloters/trigger-billing', [KloterController::class, 'triggerBilling'])->name('kloters.trigger-billing');
});
