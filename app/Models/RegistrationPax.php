<?php
/**
 * File: app/Models/RegistrationPax.php
 * Tujuan: Model rincian individu peserta/anggota keluarga yang diberangkatkan dalam satu pendaftaran kloter
 * Dipakai Oleh: RegistrationController, InvoiceService, BillingGenerateCommand
 * Dependensi Utama: Illuminate\Database\Eloquent\Model, KloterRegistration, FamilyMember, InvoiceItem
 * Daftar Fungsi Utama: registration(), familyMember(), invoiceItems()
 * Side Effect: Query DB tabel registration_paxes
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationPax extends Model
{
    use HasFactory;

    protected $table = 'registration_paxes';

    protected $fillable = [
        'registration_id',
        'family_member_id',
        'status',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KloterRegistration::class, 'registration_id');
    }

    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'family_member_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'registration_pax_id');
    }
}

