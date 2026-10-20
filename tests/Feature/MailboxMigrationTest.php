<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Email;
use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Services\MailboxMigrationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class MailboxMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected Domain $domain;
    protected MailAccount $adminAccount;
    protected MailboxMigrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->domain = Domain::create([
            'name' => 'azkario.biz.id',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->adminAccount = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'admin',
            'email' => 'admin@azkario.biz.id',
            'display_name' => 'Administrator Apex',
            'password' => bcrypt('AdminPassword123!'),
            'quota_bytes' => 1073741824,
            'used_bytes' => 0,
            'is_admin' => true,
            'is_active' => true,
        ]);
        $this->adminAccount->createDefaultFolders();

        $this->service = app(MailboxMigrationService::class);
    }

    /**
     * Test raw EML parsing preserves original RFC 822 date, message-id, headers and body.
     */
    public function test_eml_parsing_preserves_original_date_and_metadata(): void
    {
        $rawEml = "Date: Mon, 12 Apr 2021 14:30:00 +0700\r\n" .
                  "From: \"Budi Santoso\" <budi@external.com>\r\n" .
                  "To: kucing@azkario.biz.id, direksi@azkario.biz.id\r\n" .
                  "Subject: =?UTF-8?B?TGFwb3JhbiBLZXVhbmdhbiBUYWh1biAyMDIx?=\r\n" .
                  "Message-ID: <unique-zimbra-12345@external.com>\r\n" .
                  "Content-Type: text/plain; charset=UTF-8\r\n" .
                  "Content-Transfer-Encoding: 7bit\r\n\r\n" .
                  "Halo Kucing, berikut adalah laporan keuangan resmi tahun 2021 dari server lama.";

        $parsed = $this->service->parseEml($rawEml);

        $this->assertEquals('<unique-zimbra-12345@external.com>', $parsed['message_id']);
        $this->assertEquals('Laporan Keuangan Tahun 2021', $parsed['subject']);
        $this->assertEquals('Budi Santoso', $parsed['from_name']);
        $this->assertEquals('budi@external.com', $parsed['from_email']);
        $this->assertCount(2, $parsed['to']);
        $this->assertEquals('kucing@azkario.biz.id', $parsed['to'][0]['address']);
        
        // Assert date is preserved as 2021-04-12, NOT current date!
        $this->assertEquals(2021, $parsed['sent_at']->year);
        $this->assertEquals(4, $parsed['sent_at']->month);
        $this->assertEquals(12, $parsed['sent_at']->day);
        $this->assertStringContainsString('laporan keuangan resmi tahun 2021', $parsed['body_text']);
    }

    /**
     * Test importing single EML to account stores email with original date and handles deduplication.
     */
    public function test_import_eml_to_account_and_deduplication(): void
    {
        $rawEml = "Date: Wed, 15 Jan 2020 09:15:00 +0000\r\n" .
                  "From: client@partner.com\r\n" .
                  "To: admin@azkario.biz.id\r\n" .
                  "Subject: Arsip Penting Kontrak 2020\r\n" .
                  "Message-ID: <contract-2020@partner.com>\r\n" .
                  "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
                  "<p>Ini adalah isi kontrak yang diarsipkan dari Zimbra.</p>";

        // First import -> should succeed
        $result1 = $this->service->importEmlToAccount($this->adminAccount, 'inbox', $rawEml);
        $this->assertEquals('imported', $result1['status']);

        $email = Email::where('message_id', '<contract-2020@partner.com>')->first();
        $this->assertNotNull($email);
        $this->assertEquals('Arsip Penting Kontrak 2020', $email->subject);
        $this->assertEquals(2020, $email->sent_at->year);
        $this->assertTrue($email->is_read);

        // Second import with same message-id -> should be skipped (no duplicate)
        $result2 = $this->service->importEmlToAccount($this->adminAccount, 'inbox', $rawEml);
        $this->assertEquals('skipped', $result2['status']);
        $this->assertEquals(1, Email::where('message_id', '<contract-2020@partner.com>')->count());
    }

    /**
     * Test importing ZIP archive with folder hierarchy (Inbox & Sent).
     */
    public function test_import_zip_archive_with_folder_mapping(): void
    {
        $tempZipPath = storage_path('app/test_zimbra_backup.zip');
        $zip = new ZipArchive();
        $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $emlInbox = "Date: Thu, 10 Feb 2022 10:00:00 +0700\r\n" .
                    "From: boss@azkario.biz.id\r\n" .
                    "To: admin@azkario.biz.id\r\n" .
                    "Subject: Email Masuk Lama\r\n" .
                    "Message-ID: <inbox-msg-1@azkario.biz.id>\r\n\r\n" .
                    "Isi pesan di kotak masuk";

        $emlSent = "Date: Fri, 11 Feb 2022 11:00:00 +0700\r\n" .
                   "From: admin@azkario.biz.id\r\n" .
                   "To: boss@azkario.biz.id\r\n" .
                   "Subject: Balasan Terkirim Lama\r\n" .
                   "Message-ID: <sent-msg-2@azkario.biz.id>\r\n\r\n" .
                   "Isi pesan yang sudah terkirim";

        $zip->addFromString('Inbox/msg1.eml', $emlInbox);
        $zip->addFromString('Sent/msg2.eml', $emlSent);
        $zip->close();

        $importResult = $this->service->importArchive($this->adminAccount, $tempZipPath);

        $this->assertEquals(2, $importResult['total_files']);
        $this->assertEquals(2, $importResult['imported_count']);
        $this->assertEquals(0, $importResult['failed_count']);

        // Verify inbox email is in inbox folder
        $inboxFolder = $this->adminAccount->folders()->where('slug', 'inbox')->first();
        $sentFolder = $this->adminAccount->folders()->where('slug', 'sent')->first();

        $inboxEmail = Email::where('message_id', '<inbox-msg-1@azkario.biz.id>')->first();
        $this->assertNotNull($inboxEmail);
        $this->assertEquals($inboxFolder->id, $inboxEmail->mail_folder_id);
        $this->assertEquals(2022, $inboxEmail->sent_at->year);

        $sentEmail = Email::where('message_id', '<sent-msg-2@azkario.biz.id>')->first();
        $this->assertNotNull($sentEmail);
        $this->assertEquals($sentFolder->id, $sentEmail->mail_folder_id);

        @unlink($tempZipPath);
    }

    /**
     * Test bulk user accounts provisioning from CSV.
     */
    public function test_import_accounts_from_csv(): void
    {
        $csvData = "email,display_name,password,quota_mb,is_admin\n" .
                   "pegawai1@azkario.biz.id,Pegawai Satu,Rahasia123!,2048,0\n" .
                   "manager@azkario.biz.id,Manager Operasional,Rahasia123!,4096,1";

        $result = $this->service->importAccountsFromCsv($csvData, $this->domain->id);

        $this->assertEquals(2, $result['created_count']);
        $this->assertEquals(0, $result['failed_count']);

        $pegawai = MailAccount::where('email', 'pegawai1@azkario.biz.id')->first();
        $this->assertNotNull($pegawai);
        $this->assertEquals('Pegawai Satu', $pegawai->display_name);
        $this->assertEquals(2048 * 1024 * 1024, $pegawai->quota_bytes);
        $this->assertFalse($pegawai->is_admin);
        // Default folders created automatically
        $this->assertTrue($pegawai->folders()->where('slug', 'inbox')->exists());

        $manager = MailAccount::where('email', 'manager@azkario.biz.id')->first();
        $this->assertNotNull($manager);
        $this->assertTrue($manager->is_admin);
    }

    /**
     * Test admin can access migration web page.
     */
    public function test_admin_can_access_migration_page(): void
    {
        $response = $this->actingAs($this->adminAccount)->get(route('admin.migration'));
        $response->assertStatus(200);
        $response->assertSee('Migrasi Data Mailbox');
        $response->assertSee('100% Lossless');
    }

    /**
     * Test admin can import mailbox archive via web upload.
     */
    public function test_admin_can_import_mailbox_via_web_upload(): void
    {
        $rawEml = "Date: Tue, 05 May 2020 12:00:00 +0700\r\n" .
                  "From: partner@corp.com\r\n" .
                  "To: admin@azkario.biz.id\r\n" .
                  "Subject: Dokumen Kerjasama\r\n" .
                  "Message-ID: <cooperation-doc@corp.com>\r\n\r\n" .
                  "Isi dokumen kerjasama penting.";

        $uploadedFile = UploadedFile::fake()->createWithContent('backup.eml', $rawEml);

        $response = $this->actingAs($this->adminAccount)->post(route('admin.migration.mailbox'), [
            'account_id' => $this->adminAccount->id,
            'archive_file' => $uploadedFile,
            'default_folder' => 'inbox',
            'skip_duplicates' => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $email = Email::where('message_id', '<cooperation-doc@corp.com>')->first();
        $this->assertNotNull($email);
        $this->assertEquals('Dokumen Kerjasama', $email->subject);
    }

    /**
     * Test admin can import accounts via web CSV submission.
     */
    public function test_admin_can_import_accounts_via_web_csv(): void
    {
        $csvText = "email,display_name,password,quota_mb,is_admin\n" .
                   "keuangan@azkario.biz.id,Bagian Keuangan,Sandi123!,1024,0";

        $response = $this->actingAs($this->adminAccount)->post(route('admin.migration.accounts'), [
            'domain_id' => $this->domain->id,
            'csv_text' => $csvText,
            'default_password' => 'Default123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('mail_accounts', [
            'email' => 'keuangan@azkario.biz.id',
            'display_name' => 'Bagian Keuangan',
        ]);
    }

    /**
     * Test CLI migrate-archive command execution.
     */
    public function test_cli_migrate_archive_command(): void
    {
        $tempEmlPath = storage_path('app/temp_cli_test.eml');
        $rawEml = "Date: Fri, 01 Jan 2021 08:00:00 +0000\r\n" .
                  "From: newyear@global.com\r\n" .
                  "To: admin@azkario.biz.id\r\n" .
                  "Subject: Selamat Tahun Baru 2021\r\n" .
                  "Message-ID: <cli-test-newyear-2021@global.com>\r\n\r\n" .
                  "Pesan dari CLI migration.";
        file_put_contents($tempEmlPath, $rawEml);

        $this->artisan('mail:migrate-archive', [
            'email' => $this->adminAccount->email,
            'archive_path' => $tempEmlPath,
        ])
        ->expectsOutputToContain('MIGRASI BERHASIL SELESAI')
        ->assertSuccessful();

        $this->assertDatabaseHas('emails', [
            'message_id' => '<cli-test-newyear-2021@global.com>',
            'subject' => 'Selamat Tahun Baru 2021',
        ]);

        @unlink($tempEmlPath);
    }

    /**
     * Test migrated attachment can be downloaded and viewed inline securely.
     */
    public function test_migrated_attachment_can_be_downloaded_and_viewed_inline(): void
    {
        // 1. Create EML with base64 attachment
        $fileContent = "%PDF-1.4 sample pdf content";
        $b64Content = base64_encode($fileContent);

        $rawEml = "Date: Wed, 10 Jun 2020 10:00:00 +0700\r\n" .
                  "From: candidate@job.com\r\n" .
                  "To: admin@azkario.biz.id\r\n" .
                  "Subject: Lamaran Pekerjaan\r\n" .
                  "Message-ID: <job-app-2020@job.com>\r\n" .
                  "MIME-Version: 1.0\r\n" .
                  "Content-Type: multipart/mixed; boundary=\"BOUNDARY123\"\r\n\r\n" .
                  "--BOUNDARY123\r\n" .
                  "Content-Type: text/plain; charset=UTF-8\r\n\r\n" .
                  "Berikut CV saya terlampir.\r\n" .
                  "--BOUNDARY123\r\n" .
                  "Content-Type: application/pdf; name=\"Curriculum_Vitae\"\r\n" .
                  "Content-Disposition: attachment; filename=\"Curriculum_Vitae\"\r\n" .
                  "Content-Transfer-Encoding: base64\r\n\r\n" .
                  chunk_split($b64Content) . "\r\n" .
                  "--BOUNDARY123--";

        $result = $this->service->importEmlToAccount($this->adminAccount, 'inbox', $rawEml);
        $this->assertEquals('imported', $result['status']);
        $this->assertEquals(1, $result['attachments_count']);

        $email = Email::where('message_id', '<job-app-2020@job.com>')->firstOrFail();
        $attachment = $email->attachments()->firstOrFail();

        $this->assertNotNull($attachment->real_path);
        $this->assertTrue(file_exists($attachment->real_path));
        $this->assertTrue($attachment->existsOnDisk());
        $this->assertEquals('Curriculum_Vitae.pdf', $attachment->download_filename);

        // 2. Test download endpoint
        $dlResponse = $this->actingAs($this->adminAccount)
            ->get(route('webmail.attachment.download', $attachment->id));
        $dlResponse->assertStatus(200);

        // 3. Test inline preview endpoint
        $inlineResponse = $this->actingAs($this->adminAccount)
            ->get(route('webmail.attachment.inline', $attachment->id));
        $inlineResponse->assertStatus(200);
        $inlineResponse->assertHeader('Content-Type', 'application/pdf');
    }
}

