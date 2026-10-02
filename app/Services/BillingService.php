<?php
/**
 * File: app/Services/BillingService.php
 * Tujuan: Layanan terpusat untuk pembuatan tagihan bulanan otomatis maupun manual (serentak seluruh sistem, per kloter, maupun per orang / per pendaftaran keluarga), penanganan pendaftaran susulan (late joiner), pelunasan sisa target di bulan akhir, dan rekonsiliasi status invoice dengan alokasi FIFO waterfall
 * Dipakai Oleh: GenerateMonthlyBillingCommand, Admin\KloterController, PaymentService, Jamaah\InvoiceController, Jamaah\KloterRegistrationController, Admin\RegistrationApprovalController
 * Dependensi Utama: App\Models\Invoice, App\Models\InvoiceItem, App\Models\Kloter, App\Models\KloterRegistration, App\Models\Payment, DB, Str, Carbon
 * Daftar Fungsi Utama: generateMonthlyInvoices(), generateKloterInvoices(), generateKloterAllPendingInvoices(), generateRegistrationInvoice(), generateRegistrationAllPendingInvoices(), processSingleRegistrationInvoice(), recalculateInvoiceStatus(), recalculateRegistrationInvoices(), generateInvoiceNumber()
 * Side Effect: Write DB tabel invoices & invoice_items, update paid_amount & status secara transaksional
 */

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Kloter;
use App\Models\KloterRegistration;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    /**
     * Menerbitkan tagihan bulanan khusus untuk kloter tertentu
     */
    public function generateKloterInvoices(Kloter $kloter, ?Carbon $billingDate = null): array
    {
        return $this->generateMonthlyInvoices($billingDate, $kloter);
    }

    /**
     * Menerbitkan sekaligus tagihan bulanan kloter dari awal kloter hingga periode tertentu yang belum terbit
     */
    public function generateKloterAllPendingInvoices(Kloter $kloter, ?Carbon $upToDate = null): array
    {
        $current = $kloter->start_date->copy()->startOfMonth();
        $targetLimit = ($upToDate ?: Carbon::now())->copy()->startOfMonth();
        $kloterEnd = $kloter->end_date->copy()->startOfMonth();

        if ($targetLimit->greaterThan($kloterEnd)) {
            $targetLimit = $kloterEnd;
        }

        $totalCreated = 0;
        $totalSkipped = 0;
        $monthsCount = 0;

        while ($current->lessThanOrEqualTo($targetLimit)) {
            $res = $this->generateKloterInvoices($kloter, $current);
            $totalCreated += $res['created'];
            $totalSkipped += $res['skipped'];
            $monthsCount++;
            $current->addMonth();
        }

        return [
            'created' => $totalCreated,
            'skipped' => $totalSkipped,
            'months' => $monthsCount,
            'start_period' => $kloter->start_date->copy()->startOfMonth()->locale('id')->translatedFormat('F Y'),
            'end_period' => $targetLimit->locale('id')->translatedFormat('F Y'),
        ];
    }

    /**
     * Menerbitkan tagihan bulanan manual untuk 1 akun pendaftar / per orang tertentu
     */
    public function generateRegistrationInvoice(KloterRegistration $registration, ?Carbon $billingDate = null): array
    {
        $billingDate = $billingDate ?: Carbon::now()->startOfMonth();
        $created = $this->processSingleRegistrationInvoice($registration, $billingDate);

        return [
            'created' => $created ? 1 : 0,
            'skipped' => $created ? 0 : 1,
            'period' => $billingDate->locale('id')->translatedFormat('F Y'),
        ];
    }

    /**
     * Menerbitkan sekaligus tagihan untuk 1 pendaftaran jama'ah tertentu dari awal bergabung hingga periode tertentu
     */
    public function generateRegistrationAllPendingInvoices(KloterRegistration $registration, ?Carbon $upToDate = null): array
    {
        $kloter = $registration->kloter ?: Kloter::find($registration->kloter_id);
        $effectiveStart = $registration->getEffectiveStartBillingDate();
        $current = $effectiveStart->copy()->startOfMonth();
        $targetLimit = ($upToDate ?: Carbon::now())->copy()->startOfMonth();
        $kloterEnd = $kloter ? $kloter->end_date->copy()->startOfMonth() : $targetLimit;

        if ($targetLimit->greaterThan($kloterEnd)) {
            $targetLimit = $kloterEnd;
        }

        $totalCreated = 0;
        $totalSkipped = 0;
        $monthsCount = 0;

        while ($current->lessThanOrEqualTo($targetLimit)) {
            $created = $this->processSingleRegistrationInvoice($registration, $current);
            if ($created) {
                $totalCreated++;
            } else {
                $totalSkipped++;
            }
            $monthsCount++;
            $current->addMonth();
        }

        return [
            'created' => $totalCreated,
            'skipped' => $totalSkipped,
            'months' => $monthsCount,
            'start_period' => $effectiveStart->copy()->startOfMonth()->locale('id')->translatedFormat('F Y'),
            'end_period' => $targetLimit->locale('id')->translatedFormat('F Y'),
        ];
    }

    /**
     * Menerbitkan tagihan bulanan tanggal 1 untuk seluruh pendaftaran kloter aktif atau kloter spesifik.
     * Menggunakan DB chunking dan eager loading untuk efisiensi memory & I/O.
     */
    public function generateMonthlyInvoices(?Carbon $billingDate = null, ?Kloter $targetKloter = null): array
    {
        $billingDate = $billingDate ?: Carbon::now()->startOfMonth();

        $stats = [
            'created' => 0,
            'skipped' => 0,
        ];

        $query = KloterRegistration::query()
            ->with(['kloter', 'paxes' => fn ($q) => $q->where('status', 'active')->with('familyMember')])
            ->where('status', 'active');

        if ($targetKloter) {
            $query->where('kloter_id', $targetKloter->id);
        }

        $query->whereHas('kloter', function ($q) use ($billingDate, $targetKloter) {
            if ($targetKloter) {
                $q->where('id', $targetKloter->id);
            }
            $q->where('status', 'active')
                ->whereDate('start_date', '<=', $billingDate)
                ->whereDate('end_date', '>=', $billingDate);
        })
        ->chunkById(100, function ($registrations) use ($billingDate, &$stats) {
            foreach ($registrations as $reg) {
                if ($this->processSingleRegistrationInvoice($reg, $billingDate)) {
                    $stats['created']++;
                } else {
                    $stats['skipped']++;
                }
            }
        });

        return $stats;
    }

    /**
     * Proses pembuatan tagihan untuk satu pendaftaran kloter pada bulan tertentu
     * dengan idempotency, late joiner check, penyesuaian bulan akhir, dan auto-recalculate waterfall
     */
    public function processSingleRegistrationInvoice(KloterRegistration $reg, Carbon $billingDate): bool
    {
        if (!$reg->relationLoaded('kloter')) {
            $reg->load('kloter');
        }
        if (!$reg->relationLoaded('paxes')) {
            $reg->load(['paxes' => fn ($q) => $q->where('status', 'active')->with('familyMember')]);
        }

        if ($reg->status !== 'active') {
            return false;
        }

        $kloter = $reg->kloter;
        if (!$kloter || $kloter->status !== 'active') {
            return false;
        }

        $billingDate = $billingDate->copy()->startOfMonth();
        $kloterStart = $kloter->start_date->copy()->startOfMonth();
        $kloterEnd = $kloter->end_date->copy()->startOfMonth();

        // Di luar rentang aktif kloter
        if ($billingDate->lt($kloterStart) || $billingDate->gt($kloterEnd)) {
            return false;
        }

        $paxes = $reg->paxes->where('status', 'active');
        $paxCount = $paxes->count();
        if ($paxCount === 0) {
            return false;
        }

        // Jamaah susulan (late joiner): lewati jika tagihan sebelum bulan bergabung efektif
        $effectiveJoinMonth = $reg->getEffectiveStartBillingDate()->copy()->startOfMonth();
        if ($billingDate->lt($effectiveJoinMonth)) {
            return false;
        }

        $year = (int) $billingDate->year;
        $month = (int) $billingDate->month;
        $dueDate = $billingDate->copy()->day(10); // Jatuh tempo tanggal 10

        // Idempotency: cek apakah sudah pernah dibuat tagihan periode ini
        $exists = Invoice::where('registration_id', $reg->id)
            ->where('billing_year', $year)
            ->where('billing_month', $month)
            ->exists();

        if ($exists) {
            return false;
        }

        $monthlyPerPax = (float) $kloter->monthly_per_pax;
        $normalMonthlyTotal = $monthlyPerPax * $paxCount;

        // Deteksi bulan terakhir kloter
        $isFinalMonth = $billingDate->greaterThanOrEqualTo($kloterEnd);

        if ($isFinalMonth) {
            $totalTarget = (float) $kloter->target_per_pax * $paxCount;
            $totalBilledPreviously = (float) Invoice::where('registration_id', $reg->id)->sum('total_amount');
            $remainingToBill = max(0.0, $totalTarget - $totalBilledPreviously);

            if ($remainingToBill <= 0.0) {
                return false;
            }

            $totalAmount = $remainingToBill;
        } else {
            $totalAmount = $normalMonthlyTotal;
        }

        DB::transaction(function () use (
            $reg, $kloter, $year, $month, $billingDate, $dueDate, $totalAmount,
            $paxes, $paxCount, $isFinalMonth, $monthlyPerPax, $normalMonthlyTotal
        ) {
            $invoice = Invoice::create([
                'registration_id' => $reg->id,
                'invoice_number' => $this->generateInvoiceNumber($kloter->code, $year, $month, $reg->id),
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

        // Alokasikan dana carry-over jika ada saldo berlebih dari pembayaran sebelumnya
        $this->recalculateRegistrationInvoices($reg);

        return true;
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
