<?php
/**
 * File: app/Models/BankAccount.php
 * Tujuan: Model rekening bank penampung tabungan umroh milik penyelenggara/travel
 * Dipakai Oleh: PaymentController, BankAccountController, JamaahBillingView
 * Dependensi Utama: Illuminate\Database\Eloquent\Model, Payment
 * Daftar Fungsi Utama: payments(), scopeActive()
 * Side Effect: Query DB tabel bank_accounts
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

