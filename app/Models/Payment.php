<?php
/**
 * File: app/Models/Payment.php
 * Tujuan: Model transaksi pembayaran, file bukti transfer manual, dan status approval admin
 * Dipakai Oleh: PaymentController, AdminApprovalController, InvoiceService
 * Dependensi Utama: Illuminate\Database\Eloquent\Model, Invoice, User, BankAccount
 * Daftar Fungsi Utama: invoice(), user(), bankAccount(), verifier(), canBeApprovedBy(), getProofUrlAttribute()
 * Side Effect: Query DB tabel payments dan verifikasi bukti transfer
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'invoice_id',
        'user_id',
        'bank_account_id',
        'amount',
        'payment_date',
        'proof_path',
        'sender_bank',
        'sender_account_name',
        'status',
        'verified_by',
        'verified_at',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Memastikan admin/superadmin tidak dapat menyetujui bukti transfer milik akunnya sendiri
     */
    public function canBeApprovedBy(User $admin): bool
    {
        if ($this->user_id === $admin->id) {
            return false;
        }

        return $admin->isStaff();
    }

    public function getProofUrlAttribute(): string
    {
        if (empty($this->proof_path)) {
            return '';
        }

        // Gunakan asset() agar selalu dinamis mengikuti domain aktif & protokol (HTTPS) request saat ini,
        // mencegah broken image jika APP_URL di .env server masih http://localhost
        return asset('storage/' . ltrim($this->proof_path, '/'));
    }
}
