<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Email;
use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\SmtpRelay;
use App\Services\MailRelayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AdminMailQueueTest extends TestCase
{
    use RefreshDatabase;

    protected Domain $domain;
    protected MailAccount $admin;
    protected MailFolder $sentFolder;

    protected function setUp(): void
    {
        parent::setUp();

        $relayMock = Mockery::mock(MailRelayService::class);
        $relayMock->shouldReceive('sendNewDeviceLoginAlert')->andReturn(['success' => true]);
        $relayMock->shouldReceive('maskEmail')->andReturn('a***@azkario.biz.id');
        $relayMock->shouldReceive('retryEmail')->andReturn(['success' => true, 'message' => 'Pesan dalam antrean berhasil dikirimkan.']);
        $this->app->instance(MailRelayService::class, $relayMock);

        $this->domain = Domain::create([
            'name' => 'azkario.biz.id',
            'description' => 'Azkario Domain',
            'is_active' => true,
        ]);

        $this->admin = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'adminqueue',
            'email' => 'adminqueue@azkario.biz.id',
            'password' => Hash::make('AdminSecret2026!'),
            'display_name' => 'Admin Queue Test',
            'is_active' => true,
            'is_admin' => true,
        ]);

        $this->sentFolder = MailFolder::create([
            'mail_account_id' => $this->admin->id,
            'slug' => 'sent',
            'name' => 'Terkirim',
            'is_system' => true,
        ]);

        SmtpRelay::create([
            'domain_id' => $this->domain->id,
            'name' => 'Production SMTP Relay',
            'provider' => 'standard',
            'host' => 'smtp-relay.local',
            'port' => 587,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function test_admin_dashboard_shows_queue_statistics(): void
    {
        // Create sample emails with different delivery statuses
        Email::create([
            'mail_account_id' => $this->admin->id,
            'mail_folder_id' => $this->sentFolder->id,
            'from_email' => $this->admin->email,
            'to' => [['email' => 'client1@example.com']],
            'subject' => 'Surat Sukses',
            'delivery_status' => 'delivered',
            'sent_at' => Carbon::now(),
        ]);

        Email::create([
            'mail_account_id' => $this->admin->id,
            'mail_folder_id' => $this->sentFolder->id,
            'from_email' => $this->admin->email,
            'to' => [['email' => 'client2@example.com']],
            'subject' => 'Surat Tertahan',
            'delivery_status' => 'deferred',
            'delivery_error' => 'Connection timeout to relay host',
            'sent_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Antrean Surat (Queue)');
        $response->assertSee('1 Tertahan');
        $response->assertSee('Surat Tertahan');
        $response->assertSee('Tertahan (Deferred)');
    }

    public function test_admin_queue_page_displays_metrics_and_status_filtering(): void
    {
        Email::create([
            'mail_account_id' => $this->admin->id,
            'mail_folder_id' => $this->sentFolder->id,
            'from_email' => $this->admin->email,
            'to' => [['email' => 'client1@example.com']],
            'subject' => 'Email Terkirim',
            'delivery_status' => 'delivered',
            'sent_at' => Carbon::now(),
        ]);

        Email::create([
            'mail_account_id' => $this->admin->id,
            'mail_folder_id' => $this->sentFolder->id,
            'from_email' => $this->admin->email,
            'to' => [['email' => 'client2@example.com']],
            'subject' => 'Email Tertahan Sementara',
            'delivery_status' => 'deferred',
            'delivery_error' => 'Greylisted by remote server',
            'sent_at' => Carbon::now(),
        ]);

        Email::create([
            'mail_account_id' => $this->admin->id,
            'mail_folder_id' => $this->sentFolder->id,
            'from_email' => $this->admin->email,
            'to' => [['email' => 'client3@example.com']],
            'subject' => 'Email Ditahan Admin',
            'delivery_status' => 'held',
            'sent_at' => Carbon::now(),
        ]);

        // Access all queue
        $responseAll = $this->actingAs($this->admin)->get(route('admin.queue'));
        $responseAll->assertStatus(200);
        $responseAll->assertSee('Total Antrean / Log');
        $responseAll->assertSee('Tertahan (Deferred)');
        $responseAll->assertSee('Ditahan (On Hold)');
        $responseAll->assertSee('Email Terkirim');
        $responseAll->assertSee('Email Tertahan Sementara');
        $responseAll->assertSee('Email Ditahan Admin');

        // Filter: only deferred
        $responseDeferred = $this->actingAs($this->admin)->get(route('admin.queue', ['status' => 'deferred']));
        $responseDeferred->assertStatus(200);
        $responseDeferred->assertSee('Email Tertahan Sementara');
        $responseDeferred->assertDontSee('Email Ditahan Admin');

        // Filter: only held
        $responseHeld = $this->actingAs($this->admin)->get(route('admin.queue', ['status' => 'held']));
        $responseHeld->assertStatus(200);
        $responseHeld->assertSee('Email Ditahan Admin');
        $responseHeld->assertDontSee('Email Tertahan Sementara');
    }

    public function test_admin_can_flush_deferred_queue(): void
    {
        $deferredMail = Email::create([
            'mail_account_id' => $this->admin->id,
            'mail_folder_id' => $this->sentFolder->id,
            'from_email' => $this->admin->email,
            'to' => [['email' => 'client@example.com']],
            'subject' => 'Pesanan Tertahan',
            'delivery_status' => 'deferred',
            'sent_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.queue.flush'));
        $response->assertSessionHas('success');
    }

    public function test_admin_can_toggle_hold_on_queue_item(): void
    {
        $mail = Email::create([
            'mail_account_id' => $this->admin->id,
            'mail_folder_id' => $this->sentFolder->id,
            'from_email' => $this->admin->email,
            'to' => [['email' => 'client@example.com']],
            'subject' => 'Test Hold',
            'delivery_status' => 'deferred',
            'sent_at' => Carbon::now(),
        ]);

        // Hold item
        $response = $this->actingAs($this->admin)->post(route('admin.queue.toggle-hold', $mail->id));
        $response->assertSessionHas('success');
        $this->assertEquals('held', $mail->fresh()->delivery_status);

        // Unhold item
        $response2 = $this->actingAs($this->admin)->post(route('admin.queue.toggle-hold', $mail->id));
        $response2->assertSessionHas('success');
        $this->assertEquals('deferred', $mail->fresh()->delivery_status);
    }

    public function test_admin_can_delete_queue_item(): void
    {
        $mail = Email::create([
            'mail_account_id' => $this->admin->id,
            'mail_folder_id' => $this->sentFolder->id,
            'from_email' => $this->admin->email,
            'to' => [['email' => 'delete@example.com']],
            'subject' => 'Delete Me',
            'delivery_status' => 'deferred',
            'sent_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.queue.delete', $mail->id));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('emails', ['id' => $mail->id]);
    }
}
