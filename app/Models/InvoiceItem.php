<?php
/**
 * File: app/Models/InvoiceItem.php
 * Tujuan: Model rincian item invoice per orang/pax
 * Dipakai Oleh: InvoiceController, BillingService
 * Dependensi Utama: Illuminate\Database\Eloquent\Model, Invoice, RegistrationPax
 * Daftar Fungsi Utama: invoice(), registrationPax()
 * Side Effect: Query DB tabel invoice_items
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'registration_pax_id',
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function registrationPax(): BelongsTo
    {
        return $this->belongsTo(RegistrationPax::class, 'registration_pax_id');
    }
}

