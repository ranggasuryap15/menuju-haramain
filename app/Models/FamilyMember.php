<?php
/**
 * File: app/Models/FamilyMember.php
 * Tujuan: Model data anggota keluarga/peserta di bawah akun penanggung jawab dengan enkripsi data pribadi NIK sesuai UU PDP No. 27 Tahun 2022
 * Dipakai Oleh: FamilyMemberController, RegistrationController, InvoiceService
 * Dependensi Utama: Illuminate\Database\Eloquent\Model, User, RegistrationPax
 * Daftar Fungsi Utama: user(), registrationPaxes()
 * Side Effect: Query DB tabel family_members dengan enkripsi/dekripsi otomatis identity_number
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'relationship',
        'identity_number',
        'birth_date',
        'gender',
        'phone',
    ];

    protected function casts(): array
    {
        return [
            'identity_number' => 'encrypted',
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registrationPaxes(): HasMany
    {
        return $this->hasMany(RegistrationPax::class);
    }
}

