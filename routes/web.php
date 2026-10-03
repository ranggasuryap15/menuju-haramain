<?php

/**
 * File: routes/web.php
 * Tujuan: Definisi rute web aplikasi Tabungan Umroh (Autentikasi guest, Portal Jamaah, Portal Admin Keuangan & Superadmin, Manajemen Tagihan, Manajemen Rekening Bank, Manajemen Pengguna, Fallback Penyajian File Storage)
 * Dipakai Oleh: Laravel Routing Kernel
 * Dependensi Utama: AuthController, Jamaah\* Controllers, Admin\* Controllers, UserController, Storage
 * Daftar Rute Utama: /login, /register, /profile, /storage/*, /jamaah/*, /admin/*, /admin/invoices/*, /admin/bank-accounts/*, /admin/users/*
 * Side Effect: Penanganan HTTP request dan proteksi middleware guest, auth, serta role
 */

use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\KloterController;
use App\Http\Controllers\Admin\PaymentApprovalController;
use App\Http\Controllers\Admin\RegistrationApprovalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Jamaah\DashboardController as JamaahDashboardController;
use App\Http\Controllers\Jamaah\FamilyMemberController;
use App\Http\Controllers\Jamaah\InvoiceController;
use App\Http\Controllers\Jamaah\KloterRegistrationController;
use App\Http\Controllers\Jamaah\PaymentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

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
    Route::put('/family/{familyMember}', [FamilyMemberController::class, 'update'])->name('family.update');
    Route::delete('/family/{familyMember}', [FamilyMemberController::class, 'destroy'])->name('family.destroy');

    // Pendaftaran Kloter
    Route::get('/registrations/create', [KloterRegistrationController::class, 'create'])->name('registrations.create');
    Route::post('/registrations', [KloterRegistrationController::class, 'store'])->name('registrations.store');

    // Tagihan & Pembayaran
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
});

// Area Admin Keuangan & Superadmin
Route::middleware(['auth', 'role:superadmin,admin_keuangan'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Antrean Approval Bukti Pembayaran
    Route::get('/payments', [PaymentApprovalController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [PaymentApprovalController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/approve', [PaymentApprovalController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{payment}/reject', [PaymentApprovalController::class, 'reject'])->name('payments.reject');
    Route::post('/payments/{payment}/revert', [PaymentApprovalController::class, 'revertToPending'])->name('payments.revert');

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
    Route::post('/kloters/{kloter}/trigger-billing', [KloterController::class, 'triggerKloterBilling'])->name('kloters.trigger-kloter-billing');

    // Manajemen Rekening Bank Penampung Kloter (Tambah & Edit Detail)
    Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
    Route::put('/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update');

    // Monitoring Tagihan Jama'ah (Superadmin & Admin Keuangan)
    Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');

    // Manajemen Pengguna (Khusus Superadmin: Data Jama'ah & Data Admin)
    Route::middleware(['role:superadmin'])->prefix('users')->name('users.')->group(function () {
        Route::get('/jamaah', [UserController::class, 'jamaahIndex'])->name('jamaah');
        Route::post('/jamaah', [UserController::class, 'storeJamaah'])->name('jamaah.store');
        Route::get('/admins', [UserController::class, 'adminIndex'])->name('admins');
        Route::post('/admins', [UserController::class, 'storeAdmin'])->name('admin.store');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::put('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
        Route::post('/{user}/role', [UserController::class, 'changeRole'])->name('change-role');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });
});

// Fallback penyajian file storage publik (khusus jika symlink storage di server/shared hosting belum dibuat atau tidak didukung)
Route::get('/storage/{path}', function (string $path) {
    $disk = Storage::disk('public');
    if (!$disk->exists($path)) {
        abort(404);
    }

    return $disk->response($path);
})->where('path', '.*')->name('storage.fallback');
