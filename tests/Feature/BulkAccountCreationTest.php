<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MailAccount;
use App\Services\MailRelayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class BulkAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    protected Domain $domain;
    protected MailAccount $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $relayMock = Mockery::mock(MailRelayService::class);
        $relayMock->shouldReceive('sendNewDeviceLoginAlert')->andReturn(['success' => true]);
        $relayMock->shouldReceive('maskEmail')->andReturn('a***@mybbs.id');
        $this->app->instance(MailRelayService::class, $relayMock);

        $this->domain = Domain::create([
            'name' => 'mybbs.id',
            'is_active' => true,
        ]);

        $this->admin = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'admin',
            'email' => 'admin@mybbs.id',
            'display_name' => 'Administrator',
            'password' => Hash::make('AdminPass123!'),
            'quota_bytes' => 1073741824,
            'used_bytes' => 0,
            'is_active' => true,
            'is_admin' => true,
        ]);
    }

    public function test_admin_accounts_page_renders_bulk_creation_elements(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.accounts'));

        $response->assertStatus(200);
        $response->assertSee('Penambahan Akun Email Massal');
        $response->assertSee('Unduh Template Excel/CSV');
        $response->assertSee('Upload & Buat Akun Massal', false);
        $response->assertSee('id="bulkImportModal"', false);
    }

    public function test_admin_can_download_csv_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.accounts.template'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="template_akun_apexmail.csv"');

        $content = $response->streamedContent();
        $this->assertStringContainsString('email,display_name,password,quota_mb,recovery_email,is_admin', $content);
        $this->assertStringContainsString('@mybbs.id', $content);
    }

    public function test_admin_can_bulk_upload_accounts_from_csv_file(): void
    {
        $csvContent = "email,display_name,password,quota_mb,recovery_email,is_admin\n" .
                      "staf1@mybbs.id,Staf Satu,SandiKuat2026!,2048,pemulihan1@gmail.com,0\n" .
                      "staf2@mybbs.id,Staf Dua,SandiKuat2026!,1024,pemulihan2@gmail.com,0\n" .
                      "manager.it@mybbs.id,Manager IT,SandiKuat2026!,5120,it.backup@gmail.com,1\n";

        $file = UploadedFile::fake()->createWithContent('daftar_akun.csv', $csvContent);

        $response = $this->actingAs($this->admin)->post(route('admin.accounts.import'), [
            'domain_id' => $this->domain->id,
            'csv_file' => $file,
            'default_password' => 'DefaultSandi123!',
        ]);

        $response->assertSessionHas('success');
        $this->assertStringContainsString('Dibuat: 3', session('success'));

        // Verify accounts in DB
        $this->assertDatabaseHas('mail_accounts', [
            'email' => 'staf1@mybbs.id',
            'display_name' => 'Staf Satu',
            'recovery_email' => 'pemulihan1@gmail.com',
            'is_admin' => 0,
        ]);

        $this->assertDatabaseHas('mail_accounts', [
            'email' => 'manager.it@mybbs.id',
            'display_name' => 'Manager IT',
            'is_admin' => 1,
        ]);

        // Verify default folders created
        $acc1 = MailAccount::where('email', 'staf1@mybbs.id')->first();
        $this->assertTrue($acc1->folders()->where('slug', 'inbox')->exists());
        $this->assertTrue($acc1->folders()->where('slug', 'sent')->exists());
        $this->assertTrue($acc1->folders()->where('slug', 'trash')->exists());
    }
}
