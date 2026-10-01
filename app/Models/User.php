<?php
/**
 * File: app/Models/User.php
 * Tujuan: Model otentikasi pengguna, hak akses peran (Superadmin, Admin Keuangan, Jamaah), dan relasi keluarga
 * Dipakai Oleh: AuthController, DashboardController, BillingService, PaymentService
 * Dependensi Utama: Illuminate\Foundation\Auth\User, Eloquent ORM
 * Daftar Fungsi Utama: isSuperAdmin(), isAdminKeuangan(), isStaff(), familyMembers(), kloterRegistrations(), payments()
 * Side Effect: Query DB tabel users dan relasi anak
 */

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN_KEUANGAN = 'admin_keuangan';
    public const ROLE_JAMAAH = 'jamaah';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isAdminKeuangan(): bool
    {
        return $this->role === self::ROLE_ADMIN_KEUANGAN;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_SUPERADMIN, self::ROLE_ADMIN_KEUANGAN], true);
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function kloterRegistrations(): HasMany
    {
        return $this->hasMany(KloterRegistration::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

