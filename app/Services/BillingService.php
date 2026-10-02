<?php
/**
 * File: app/Services/BillingService.php
 * Tujuan: Layanan terpusat untuk pembuatan tagihan bulanan otomatis, penanganan pendaftaran susulan (late joiner), pelunasan sisa target di bulan akhir, dan rekonsiliasi status invoice dengan alokasi FIFO waterfall
 * Dipakai Oleh: GenerateMonthlyBillingCommand, AdminInvoiceController, PaymentService, Jamaah\InvoiceController
 * Dependensi Utama: App\Models\Invoice, App\Models\InvoiceItem, App\Models\KloterRegistration, App\Models\Payment, DB
 * Daftar Fungsi Utama: generateMonthlyInvoices(), recalculateInvoiceStatus(), recalculateRegistrationInvoices(), generateInvoiceNumber()
 * Side Effect: Write DB tabel invoices & invoice_items, update paid_amount & status secara transaksional
 */

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\KloterRegistration;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    /**
     * Menerbitkan tagihan bulanan otomatis tanggal 1 untuk seluruh pendaftaran kloter yang aktif.
     * Jamaah susulan (late joiner) hanya ditagih sejak bulan bergabung, dan kekurangan bulan awal
     * ditagihkan sekaligus sebagai pelunasan di bulan akhir kloter sebelum keberangkatan.
     * Menggunakan DB chunking dan eager loading untuk efisiensi memory & I/O.
     */
    public function generateMonthlyInvoices(?Carbon $billingDate = null): array
    {
        $billingDate = $billingDate ?: Carbon::now()->startOfMonth();
        $year = (int) $billingDate->year;
        $month = (int) $billingDate->month;
        $dueDate = $billingDate->copy()->day(10); // Batas bayar tanggal 10 bulan berjalan

        $stats = [
            'created' => 0,
            'skipped' => 0,
        ];

        // Eager load kloter & paxes aktif untuk mencegah N+1 query
        KloterRegistration::query()
            ->with(['kloter', 'paxes' => fn ($q) => $q->where('status', 'active')->with('familyMember')])
            ->where('status', 'active')
            ->whereHas('kloter', function ($q) use ($billingDate) {
                $q->where('status', 'active')
                    ->whereDate('start_date', '<=', $billingDate)
                    ->whereDate('end_date', '>=', $billingDate);
            })
            ->chunkById(100, function ($registrations) use ($year, $month, $billingDate, $dueDate, &$stats) {
                foreach ($registrations as $reg) {
                    $paxes = $reg->paxes;
                    $paxCount = $paxes->count();

                    if ($paxCount === 0) {
                        $stats['skipped']++;
                        continue;
                    }

                    // Jamaah susulan: lewati jika tagihan ditujukan untuk periode sebelum pendaftaran/approval peserta
                    $effectiveJoinMonth = ($reg->approved_at ?: $reg->created_at)->copy()->startOfMonth();
                    if ($billingDate->lt($effectiveJoinMonth)) {
                        $stats['skipped']++;
                        continue;
                    }

                    // Cek idempotensi: hindari duplikasi jika sudah ada tagihan untuk periode ini
                    $exists = Invoice::where('registration_id', $reg->id)
                        ->where('billing_year', $year)
                        ->where('billing_month', $month)
                        ->exists();

                    if ($exists) {
                        $stats['skipped']++;
                        continue;
                    }

                    $monthlyPerPax = (float) $reg->kloter->monthly_per_pax;
                    $normalMonthlyTotal = $monthlyPerPax * $paxCount;

                    // Deteksi apakah periode penagihan ini berada di bulan terakhir kloter
                    $kloterEndMonth = $reg->kloter->end_date->copy()->startOfMonth();
                    $isFinalMonth = $billingDate->greaterThanOrEqualTo($kloterEndMonth);

                    if ($isFinalMonth) {
                        // Pada bulan terakhir, tagihkan sisa seluruh target biaya paket yang belum ditagihkan sebelumnya
                        $totalTarget = (float) $reg->kloter->target_per_pax * $paxCount;
                        $totalBilledPreviously = (float) Invoice::where('registration_id', $reg->id)->sum('total_amount');
                        $remainingToBill = max(0.0, $totalTarget - $totalBilledPreviously);

                        if ($remainingToBill <= 0.0) {
                            $stats['skipped']++;
                            continue;
                        }

                        $totalAmount = $remainingToBill;
                    } else {
                        $totalAmount = $normalMonthlyTotal;
                    }

                    DB::transaction(function () use (
                        $reg, $year, $month, $billingDate, $dueDate, $totalAmount,
                        $paxes, $paxCount, $isFinalMonth, $monthlyPerPax, $normalMonthlyTotal
                    ) {
                        $invoice = Invoice::create([
                            'registration_id' => $reg->id,
                            'invoice_number' => $this->generateInvoiceNumber($reg->kloter->code, $year, $month, $reg->id),
                            'billing_year' => $year,
                            'billing_month' => $month,
                            'billing_date' => $billingDate->toDateString(),
                            'due_date' => $dueDate->toDateString(),
                            'total_amount' => $totalAmount,
                            'paid_amount' => 0,
                            'status' => Invoice::STATUS_UNPAID,
                        ]);

                        $monthName = $billingDate->locale('id')->translatedFormat('F Y');

                        if ($isFinalMonth && $totalAmount > $normalMonthlyTotal) {
                            // Late joiner catchup: pisahkan tagihan reguler dan pelunasan periode awal
                            $catchupPerPax = ($totalAmount - $normalMonthlyTotal) / $paxCount;

                            foreach ($paxes as $pax) {
                                $paxName = $pax->familyMember?->full_name ?? 'Peserta';

                                InvoiceItem::create([
                                    'invoice_id' => $invoice->id,
                                    'registration_pax_id' => $pax->id,
                                    'amount' => $monthlyPerPax,
                                    'description' => "Tabungan Umroh Bulan {$monthName} - {$paxName}",
                                ]);

                                InvoiceItem::create([
                                    'invoice_id' => $invoice->id,
                                    'registration_pax_id' => $pax->id,
                                    'amount' => $catchupPerPax,
                                    'description' => "Pelunasan Sisa Periode Awal Sebelum Bergabung - {$paxName}",
                                ]);
                            }
                        } else {
                            $itemAmountPerPax = $totalAmount / $paxCount;
                            $descPrefix = ($isFinalMonth && $totalAmount < $normalMonthlyTotal)
                                ? "Tabungan Umroh Bulan {$monthName} (Penyesuaian Akhir)"
                                : "Tabungan Umroh Bulan {$monthName}";

                            foreach ($paxes as $pax) {
                                InvoiceItem::create([
                                    'invoice_id' => $invoice->id,
                                    'registration_pax_id' => $pax->id,
                                    'amount' => $itemAmountPerPax,
                                    'description' => "{$descPrefix} - " . ($pax->familyMember?->full_name ?? 'Peserta'),
                                ]);
                            }
                        }
                    });

                    // Alokasikan dana carry-over jika ada kelebihan pembayaran dari bulan lalu
                    $this->recalculateRegistrationInvoices($reg);

                    $stats['created']++;
                }
            });

        return $stats;
    }

    /**
     * Hitung ulang status pelunasan seluruh invoice pada suatu pendaftaran kloter secara kronologis (Waterfall FIFO).
     * Jika peserta membayar lebih pada suatu periode, kelebihan dana otomatis mengalir memotong tagihan periode berikutnya.
     */
    public function recalculateRegistrationInvoices(KloterRegistration $registration): void
    {
        // Hitung total seluruh pembayaran berstatus approved untuk pendaftaran ini
        $approvedTotal = (float) Payment::whereHas('invoice', function ($query) use ($registration) {
            $query->where('registration_id', $registration->id);
        })->where('status', Payment::STATUS_APPROVED)->sum('amount');

        // Ambil semua invoice pendaftaran diurutkan kronologis (terlama ke terbaru)
        $invoices = Invoice::where('registration_id', $registration->id)
            ->orderBy('billing_year', 'asc')
            ->orderBy('billing_month', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $remainingFunds = $approvedTotal;

        foreach ($invoices as $inv) {
            $totalAmount = (float) $inv->total_amount;
            $allocated = min($remainingFunds, $totalAmount);

            $inv->paid_amount = $allocated;
            $remainingFunds = max(0.0, $remainingFunds - $allocated);

            if ($allocated >= $totalAmount) {
                $inv->status = Invoice::STATUS_PAID;
            } elseif ($allocated > 0) {
                $inv->status = Invoice::STATUS_PARTIALLY_PAID;
            } else {
                $inv->status = Carbon::parse($inv->due_date)->isPast()
                    ? Invoice::STATUS_OVERDUE
                    : Invoice::STATUS_UNPAID;
            }

            // Simpan hanya jika ada atribut yang berubah untuk efisiensi DB I/O
            if ($inv->isDirty()) {
                $inv->save();
            }
        }
    }

    /**
     * Rekonsiliasi invoice dengan memicu rekonsiliasi buku besar pendaftaran kloter terkait
     */
    public function recalculateInvoiceStatus(Invoice $invoice): void
    {
        if ($invoice->relationLoaded('registration') && $invoice->registration) {
            $this->recalculateRegistrationInvoices($invoice->registration);
        } else {
            $registration = KloterRegistration::find($invoice->registration_id);
            if ($registration) {
                $this->recalculateRegistrationInvoices($registration);
            }
        }
    }

    protected function generateInvoiceNumber(string $kloterCode, int $year, int $month, int $registrationId): string
    {
        $code = Str::upper(Str::slug($kloterCode, ''));
        return sprintf("INV-%s-%04d%02d-%04d", $code, $year, $month, $registrationId);
    }
}

