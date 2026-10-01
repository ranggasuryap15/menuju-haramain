<?php
/**
 * File: app/Console/Commands/GenerateMonthlyInvoicesCommand.php
 * Tujuan: Perintah artisan terjadwal (Scheduler) setiap tanggal 1 untuk menerbitkan tagihan bulanan
 * Dipakai Oleh: Laravel Scheduler (routes/console.php) / Cron Tab server
 * Dependensi Utama: App\Services\BillingService, Carbon\Carbon
 * Daftar Fungsi Utama: handle()
 * Side Effect: Query DB pendaftaran kloter, write data invoices bulanan
 */

namespace App\Console\Commands;

use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:generate-monthly {--date= : Tanggal periode penagihan (format YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otomatis menerbitkan tagihan bulanan umroh tanggal 1 untuk seluruh pendaftaran kloter yang aktif';

    /**
     * Execute the console command.
     */
    public function handle(BillingService $billingService): int
    {
        $dateParam = $this->option('date');
        $billingDate = $dateParam ? Carbon::parse($dateParam)->startOfMonth() : Carbon::now()->startOfMonth();

        $this->info("Menjalankan generator tagihan bulanan periode: " . $billingDate->locale('id')->translatedFormat('F Y'));

        $stats = $billingService->generateMonthlyInvoices($billingDate);

        $this->info("Selesai diproses.");
        $this->table(
            ['Kategori', 'Jumlah'],
            [
                ['Tagihan Baru Dibuat', $stats['created']],
                ['Dilewati / Sudah Ada', $stats['skipped']],
            ]
        );

        return Command::SUCCESS;
    }
}

