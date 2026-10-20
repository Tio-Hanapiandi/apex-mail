<?php

namespace App\Console\Commands;

use App\Models\MailAccount;
use App\Services\MailboxMigrationService;
use Exception;
use Illuminate\Console\Command;

class MigrateMailboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:migrate-archive 
                            {email : Alamat email akun tujuan di ApexMail} 
                            {archive_path : Lokasi file arsip (.zip, .tgz, .tar.gz, atau .eml)} 
                            {--folder=inbox : Folder tujuan awal jika tidak terdeteksi} 
                            {--allow-duplicates : Izinkan impor pesan duplikat (default: lewati duplikat)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrasikan seluruh email dan folder dari berkas backup Zimbra / EML ke akun ApexMail tanpa mengubah tanggal atau isi asli';

    /**
     * Execute the console command.
     */
    public function handle(MailboxMigrationService $migrationService): int
    {
        $email = $this->argument('email');
        $archivePath = $this->argument('archive_path');
        $defaultFolder = $this->option('folder') ?: 'inbox';
        $skipDuplicates = !$this->option('allow-duplicates');

        $this->info("=== APEXMAIL MAILBOX MIGRATION SUITE ===");
        $this->info("Menyiapkan migrasi untuk akun: {$email}");

        $account = MailAccount::where('email', $email)->first();
        if (!$account) {
            $this->error("Akun email {$email} tidak ditemukan dalam database ApexMail.");
            return self::FAILURE;
        }

        if (!file_exists($archivePath)) {
            $this->error("Berkas arsip tidak ditemukan di: {$archivePath}");
            return self::FAILURE;
        }

        $fileSizeMb = round(filesize($archivePath) / (1024 * 1024), 2);
        $this->line("Ukuran berkas arsip: {$fileSizeMb} MB");
        $this->line("Memproses ekstraksi & pembacaan MIME pesan...");

        try {
            $startTime = microtime(true);
            $result = $migrationService->importArchive($account, $archivePath, [
                'default_folder' => $defaultFolder,
                'skip_duplicates' => $skipDuplicates,
                'default_is_read' => true,
            ]);
            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info("✓ MIGRASI BERHASIL SELESAI ({$duration} detik)");
            $this->table(
                ['Metrik', 'Nilai'],
                [
                    ['Total Berkas EML Ditemukan', $result['total_files']],
                    ['Pesan Berhasil Diimpor', $result['imported_count']],
                    ['Pesan Dilewati (Duplikat)', $result['skipped_count']],
                    ['Pesan Gagal Diimpor', $result['failed_count']],
                    ['Total Ukuran Data Diimpor', round($result['total_bytes'] / (1024 * 1024), 2) . ' MB'],
                    ['Folder Yang Terpetakan', implode(', ', $result['folders'] ?? [])],
                ]
            );

            if (!empty($result['errors'])) {
                $this->warn("Catatan Peringatan:");
                foreach (array_slice($result['errors'], 0, 10) as $err) {
                    $this->line(" - {$err}");
                }
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Terjadi kegagalan saat proses migrasi: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
