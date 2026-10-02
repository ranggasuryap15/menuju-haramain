<?php

/**
 * File: app/Models/KloterRegistration.php
 * Tujuan: Model pendaftaran akun user ke kloter tertentu beserta status approval, tanggal mulai penagihan (start_billing_date), rekapitulasi capaian tabungan, dan deteksi pendaftaran susulan (late joiner)
 * Dipakai Oleh: RegistrationController, RegistrationApprovalController, JamaahDashboardController, BillingService
 * Dependensi Utama: Illuminate\Database\Eloquent\Model, Kloter, User, RegistrationPax, Invoice, Payment, Carbon\Carbon
 * Daftar Fungsi Utama: kloter(), user(), approver(), paxes(), invoices(), payments(), getEffectiveStartBillingDate(), isLateJoiner(), getMissedInitialMonthsCount(), getMissedInitialAmount(), getRemainingUnbilledAmount()
 * Side Effect: Query DB tabel kloter_registrations dan agregasi pembayaran
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class KloterRegistration extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'kloter_id',
        'user_id',
        'total_pax',
        'status',
        'start_billing_date',
        'approved_by',
        'approved_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'start_billing_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function kloter(): BelongsTo
    {
        return $this->belongsTo(Kloter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->approver();
    }

    public function paxes(): HasMany
    {
        return $this->hasMany(RegistrationPax::class, 'registration_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'registration_id');
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Invoice::class, 'registration_id', 'invoice_id');
    }

    public function getActivePaxCountAttribute(): int
    {
        return $this->paxes()->where('status', 'active')->count();
    }

    public function getTargetTotalAttribute(): float
    {
        $paxCount = $this->active_pax_count ?: $this->total_pax;
        $targetPerPax = (float) ($this->kloter?->target_per_pax ?? 0);
        return $paxCount * $targetPerPax;
    }

    public function getTotalSavedAttribute(): float
    {
        if ($this->relationLoaded('invoices')) {
            $isInvoicesPaymentsLoaded = $this->invoices->every(fn ($inv) => $inv->relationLoaded('payments'));
            if ($isInvoicesPaymentsLoaded) {
                return (float) $this->invoices
                    ->flatMap->payments
                    ->where('status', Payment::STATUS_APPROVED)
                    ->sum('amount');
            }
        }

        return (float) $this->payments()->where('payments.status', Payment::STATUS_APPROVED)->sum('amount');
    }

    public function getProgressPercentageAttribute(): float
    {
        $target = $this->target_total;
        if ($target <= 0) {
            return 0;
        }

        return min(100.0, round(($this->total_saved / $target) * 100, 1));
    }

    /**
     * Mendapatkan bulan awal efektif penagihan untuk pendaftaran ini.
     * Jika start_billing_date diisi secara eksplisit, gunakan tanggal tersebut.
     * Jika tidak diisi (null), secara default penagihan dimulai sejak awal periode kloter.
     */
    public function getEffectiveStartBillingDate(): Carbon
    {
        if ($this->start_billing_date) {
            return Carbon::parse($this->start_billing_date)->startOfMonth();
        }

        if ($this->kloter && $this->kloter->start_date) {
            return $this->kloter->start_date->copy()->startOfMonth();
        }

        return ($this->approved_at ?: $this->created_at)->copy()->startOfMonth();
    }

    /**
     * Memeriksa apakah pendaftaran bergabung susulan (setelah bulan pertama kloter berjalan)
     */
    public function isLateJoiner(): bool
    {
        if (!$this->kloter || !$this->kloter->start_date) {
            return false;
        }
        $kloterStartDate = $this->kloter->start_date->copy()->startOfMonth();
        return $this->getEffectiveStartBillingDate()->greaterThan($kloterStartDate);
    }

    /**
     * Menghitung berapa bulan awal yang terlewat sebelum akun mendaftar/disetujui
     */
    public function getMissedInitialMonthsCount(): int
    {
        if (!$this->isLateJoiner()) {
            return 0;
        }
        $joinDate = $this->getEffectiveStartBillingDate();
        $kloterStartDate = $this->kloter->start_date->copy()->startOfMonth();
        return max(0, (int) round($kloterStartDate->diffInMonths($joinDate)));
    }

    /**
     * Menghitung akumulasi nominal bulan awal yang belum ditagihkan untuk seluruh pax di pendaftaran ini
     */
    public function getMissedInitialAmount(): float
    {
        $paxCount = $this->active_pax_count ?: $this->total_pax;
        $monthlyPerPax = (float) ($this->kloter?->monthly_per_pax ?? 0);
        return (float) ($this->getMissedInitialMonthsCount() * $monthlyPerPax * $paxCount);
    }

    /**
     * Menghitung sisa total target paket yang belum pernah diterbitkan tagihannya
     */
    public function getRemainingUnbilledAmount(): float
    {
        $totalBilled = (float) $this->invoices()->sum('total_amount');
        return max(0, $this->target_total - $totalBilled);
    }
}
