<?php
/**
 * File: app/Models/Kloter.php
 * Tujuan: Model master data kloter/paket umroh dengan target tabungan, akumulasi dana terkumpul, rentang tanggal periode, link grup WhatsApp kloter, mutator kode uppercase, dan relasi rekening bank kloter
 * Dipakai Oleh: KloterController, BillingGenerateCommand, RegistrationController, InvoiceController
 * Dependensi Utama: Illuminate\Database\Eloquent\Model, Illuminate\Database\Eloquent\Casts\Attribute, KloterRegistration, Invoice, Payment, BankAccount
 * Daftar Fungsi Utama: registrations(), invoices(), bankAccounts(), code(), isActive(), getDurationMonthsAttribute(), getTotalPaidAttribute(), getTotalBilledAttribute()
 * Side Effect: Query DB tabel kloters, agregasi pembayaran masuk, relasi pivot kloter_bank_account
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Kloter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'target_per_pax',
        'monthly_per_pax',
        'start_date',
        'end_date',
        'description',
        'whatsapp_group_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_per_pax' => 'decimal:2',
            'monthly_per_pax' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Memastikan kode kloter selalu tersimpan dan dibaca dalam format UPPERCASE
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? strtoupper($value) : $value,
            set: fn (?string $value) => $value ? strtoupper(trim($value)) : $value,
        );
    }

    public function bankAccounts(): BelongsToMany
    {
        return $this->belongsToMany(BankAccount::class, 'kloter_bank_account')->withTimestamps();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(KloterRegistration::class);
    }

    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(Invoice::class, KloterRegistration::class, 'kloter_id', 'registration_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getDurationMonthsAttribute(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        return max(1, (int) round($this->start_date->diffInMonths($this->end_date)));
    }

    /**
     * Total dana tabungan riil yang telah disetor dan diverifikasi (Approved) untuk kloter ini
     */
    public function getTotalPaidAttribute(): float
    {
        if ($this->relationLoaded('registrations')) {
            $isDeepLoaded = $this->registrations->every(function ($reg) {
                return $reg->relationLoaded('invoices') && $reg->invoices->every(fn ($inv) => $inv->relationLoaded('payments'));
            });

            if ($isDeepLoaded) {
                return (float) $this->registrations
                    ->flatMap->invoices
                    ->flatMap->payments
                    ->where('status', Payment::STATUS_APPROVED)
                    ->sum('amount');
            }
        }

        return (float) Payment::query()
            ->where('payments.status', Payment::STATUS_APPROVED)
            ->whereHas('invoice.registration', fn ($q) => $q->where('kloter_id', $this->id))
            ->sum('amount');
    }

    /**
     * Total seluruh nominal tagihan yang telah diterbitkan pada kloter ini
     */
    public function getTotalBilledAttribute(): float
    {
        if ($this->relationLoaded('registrations')) {
            $isInvoicesLoaded = $this->registrations->every(fn ($reg) => $reg->relationLoaded('invoices'));
            if ($isInvoicesLoaded) {
                return (float) $this->registrations
                    ->flatMap->invoices
                    ->sum('total_amount');
            }
        }

        return (float) Invoice::query()
            ->whereHas('registration', fn ($q) => $q->where('kloter_id', $this->id))
            ->sum('total_amount');
    }
}
