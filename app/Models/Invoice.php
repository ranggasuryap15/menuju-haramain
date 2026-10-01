<?php
/**
 * File: app/Models/Invoice.php
 * Tujuan: Model tagihan bulanan tabungan umroh per pendaftaran kloter (terbit otomatis tgl 1) dengan tracking carry-over credit dan validasi urutan pembayaran
 * Dipakai Oleh: InvoiceController, BillingService, PaymentController, JamaahDashboardController
 * Dependensi Utama: Illuminate\Database\Eloquent\Model, KloterRegistration, InvoiceItem, Payment
 * Daftar Fungsi Utama: registration(), items(), payments(), getRemainingAmountAttribute(), getDirectPaidAmountAttribute(), getCarryOverCreditAttribute(), getSurplusAmountAttribute(), getPeriodLabelAttribute(), getUnpaidPreviousInvoice(), isLockedByPreviousUnpaid(), setUnpaidPreviousInvoice()
 * Side Effect: Query DB tabel invoices dan pembaruan status pembayaran
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'registration_id',
        'invoice_number',
        'billing_year',
        'billing_month',
        'billing_date',
        'due_date',
        'total_amount',
        'paid_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'billing_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'billing_year' => 'integer',
            'billing_month' => 'integer',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(KloterRegistration::class, 'registration_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->paid_amount);
    }

    /**
     * Jumlah pembayaran yang disetor langsung pada invoice ini dan telah disetujui (Approved)
     */
    public function getDirectPaidAmountAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->where('status', Payment::STATUS_APPROVED)->sum('amount');
        }
        return (float) $this->payments()->where('status', Payment::STATUS_APPROVED)->sum('amount');
    }

    /**
     * Saldo kredit bawaan dari kelebihan bayar periode sebelumnya yang dialokasikan ke tagihan ini
     */
    public function getCarryOverCreditAttribute(): float
    {
        return max(0.0, (float) $this->paid_amount - (float) $this->direct_paid_amount);
    }

    /**
     * Kelebihan nominal bayar pada invoice ini yang diteruskan memotong tagihan periode mendatang
     */
    public function getSurplusAmountAttribute(): float
    {
        return max(0.0, (float) $this->direct_paid_amount - (float) $this->total_amount);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function getPeriodLabelAttribute(): string
    {
        return Carbon::createFromDate($this->billing_year, $this->billing_month, 1)->locale('id')->translatedFormat('F Y');
    }

    public function hasPendingPayment(): bool
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->where('status', Payment::STATUS_PENDING)->isNotEmpty();
        }
        return $this->payments()->where('status', Payment::STATUS_PENDING)->exists();
    }

    /**
     * Cache instance tagihan sebelumnya yang belum lunas dalam satu lifecycle
     */
    protected ?Invoice $unpaidPreviousInvoiceInstance = null;
    protected bool $hasCheckedUnpaidPrevious = false;

    /**
     * Mengambil tagihan tertua dari periode sebelumnya pada pendaftaran kloter yang sama yang belum lunas
     */
    public function getUnpaidPreviousInvoice(): ?Invoice
    {
        if ($this->hasCheckedUnpaidPrevious) {
            return $this->unpaidPreviousInvoiceInstance;
        }

        $this->unpaidPreviousInvoiceInstance = Invoice::query()
            ->where('registration_id', $this->registration_id)
            ->where(function ($q) {
                $q->where('billing_year', '<', $this->billing_year)
                    ->orWhere(function ($sub) {
                        $sub->where('billing_year', $this->billing_year)
                            ->where('billing_month', '<', $this->billing_month);
                    });
            })
            ->where('status', '!=', self::STATUS_PAID)
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->orderBy('billing_year', 'asc')
            ->orderBy('billing_month', 'asc')
            ->first();

        $this->hasCheckedUnpaidPrevious = true;

        return $this->unpaidPreviousInvoiceInstance;
    }

    /**
     * Mengatur cache tagihan sebelumnya (misal saat preloading bulk dari Controller untuk menghindari N+1)
     */
    public function setUnpaidPreviousInvoice(?Invoice $invoice): void
    {
        $this->unpaidPreviousInvoiceInstance = $invoice;
        $this->hasCheckedUnpaidPrevious = true;
    }

    /**
     * Mengecek apakah pembayaran tagihan ini terkunci karena ada tagihan periode sebelumnya yang belum lunas
     */
    public function isLockedByPreviousUnpaid(): bool
    {
        return $this->getUnpaidPreviousInvoice() !== null;
    }
}


