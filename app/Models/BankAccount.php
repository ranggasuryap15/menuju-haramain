<?php
/**
 * File: app/Models/BankAccount.php
 * Tujuan: Model rekening bank penampung tabungan umroh milik penyelenggara/travel
 * Dipakai Oleh: PaymentController, KloterController, InvoiceController, BankAccount
 * Dependensi Utama: Illuminate\Database\Eloquent\Model, Payment, Kloter
 * Daftar Fungsi Utama: payments(), kloters(), scopeActive()
 * Side Effect: Query DB tabel bank_accounts dan pivot kloter_bank_account
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name',
        'account_number',
        'account_holder',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function kloters(): BelongsToMany
    {
        return $this->belongsToMany(Kloter::class, 'kloter_bank_account')->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

