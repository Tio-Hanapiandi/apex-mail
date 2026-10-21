<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use PDO;

class ExportDatabaseSqliteToMysqlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:export-sql {--output=apexmail_database.sql : Nama file hasil export}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export database SQLite ApexMail ke format MySQL .sql siap import phpMyAdmin';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $outputFilename = $this->option('output') ?: 'apexmail_database.sql';
        $outputPath1 = base_path($outputFilename);
        $outputPath2 = 'F:\\Backup_SSD\\Laragon\\www\\apex\\' . $outputFilename;

        $sqlitePath = database_path('database.sqlite');
        if (!file_exists($sqlitePath)) {
            $this->error("File database SQLite tidak ditemukan di: {$sqlitePath}");
            return 1;
        }

        $this->info("1. Membaca SQLite: {$sqlitePath} (" . number_format(filesize($sqlitePath) / 1024, 1) . " KB)");

        try {
            $mysqlPdo = new PDO("mysql:host=127.0.0.1;port=3306", "root", "", [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Exception $e) {
            $this->error("Gagal terhubung ke MySQL: " . $e->getMessage());
            return 1;
        }

        $tempDb = 'apexmail_export_temp';
        $this->info("2. Membuat database staging MySQL: '{$tempDb}'...");
        $mysqlPdo->exec("DROP DATABASE IF EXISTS `{$tempDb}`");
        $mysqlPdo->exec("CREATE DATABASE `{$tempDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        Config::set('database.connections.mysql_temp', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => $tempDb,
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ]);

        $this->info("3. Menjalankan skema migrasi ke MySQL staging...");
        Artisan::call('migrate', [
            '--database' => 'mysql_temp',
            '--force' => true,
        ]);

        $sqlitePdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $destPdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname={$tempDb};charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $destPdo->exec("SET FOREIGN_KEY_CHECKS=0");

        $stmt = $sqlitePdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->info("4. Menyalin data tabel SQLite ke MySQL staging...");
        foreach ($tables as $table) {
            $exists = $destPdo->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
            if (!$exists) continue;

            $destPdo->exec("TRUNCATE TABLE `{$table}`");
            $rows = $sqlitePdo->query("SELECT * FROM `{$table}`")->fetchAll();
            $count = count($rows);
            if ($count === 0) continue;

            $columns = array_keys($rows[0]);
            $colList = implode('`, `', $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));

            $insertSql = "INSERT INTO `{$table}` (`{$colList}`) VALUES ({$placeholders})";
            $insertStmt = $destPdo->prepare($insertSql);

            $destPdo->beginTransaction();
            foreach ($rows as $row) {
                $insertStmt->execute(array_values($row));
            }
            $destPdo->commit();
            $this->line("   - {$table}: {$count} baris");
        }
        $destPdo->exec("SET FOREIGN_KEY_CHECKS=1");

        $dumpBin = 'F:\\Backup_SSD\\Laragon\\bin\\mysql\\mysql-8.0.46-winx64\\bin\\mysqldump.exe';
        if (!file_exists($dumpBin)) {
            $dumpBin = 'mysqldump';
        }

        $this->info("5. Meng-export SQL dump (kompatibel phpMyAdmin)...");
        $fullCmd = "cmd /c \"\"{$dumpBin}\" --user=root --host=127.0.0.1 --port=3306 --default-character-set=utf8mb4 --add-drop-table --complete-insert --hex-blob --single-transaction --routines --triggers \"{$tempDb}\" > \"{$outputPath1}\"\"";
        system($fullCmd, $returnVar);

        if ($returnVar === 0 && file_exists($outputPath1) && filesize($outputPath1) > 0) {
            $this->info("✓ Sukses! Berkas .sql tersimpan di: {$outputPath1} (" . number_format(filesize($outputPath1) / 1024, 1) . " KB)");
            if ($outputPath1 !== $outputPath2 && file_exists(dirname($outputPath2))) {
                @copy($outputPath1, $outputPath2);
            }
        } else {
            $this->error("Gagal menjalankan mysqldump.");
        }

        $mysqlPdo->exec("DROP DATABASE IF EXISTS `{$tempDb}`");
        $this->info("6. Staging dibersihkan. Berkas siap diimport ke phpMyAdmin!");

        return 0;
    }
}
