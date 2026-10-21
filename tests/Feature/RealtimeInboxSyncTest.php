<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Email;
use App\Models\MailAccount;
use App\Models\MailFolder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeInboxSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Domain $domain;
    protected MailAccount $account;
    protected MailFolder $inbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domain = Domain::create([
            'name' => 'mybbs.id',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->account = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'budi',
            'email' => 'budi@mybbs.id',
            'display_name' => 'Budi Santoso',
            'password' => bcrypt('Secret123!@#'),
            'quota_bytes' => 1073741824,
            'used_bytes' => 0,
            'is_admin' => false,
            'is_active' => true,
        ]);
        $this->account->createDefaultFolders();
        $this->inbox = $this->account->folders()->where('slug', 'inbox')->firstOrFail();
    }

    public function test_unauthenticated_user_cannot_access_check_updates(): void
    {
        $response = $this->getJson(route('webmail.check-updates'));
        $response->assertStatus(401);
    }

    public function test_webmail_page_contains_realtime_sync_elements(): void
    {
        $response = $this->actingAs($this->account)
            ->get(route('webmail.index'));

        $response->assertStatus(200);
        $response->assertSee('id="realtimeToastContainer"', false);
        $response->assertSee('id="newEmailNoticeBanner"', false);
        $response->assertSee('id="refreshInboxBtn"', false);
        $response->assertSee('checkMailboxUpdates', false);
        $response->assertSee('playNotificationChime', false);
    }

    public function test_check_updates_returns_no_changes_when_up_to_date(): void
    {
        $email = Email::create([
            'mail_account_id' => $this->account->id,
            'mail_folder_id' => $this->inbox->id,
            'message_id' => '<initial-1@external.com>',
            'from_name' => 'Client A',
            'from_email' => 'client@external.com',
            'to' => [['address' => 'budi@mybbs.id', 'name' => 'Budi']],
            'subject' => 'Surat Pengantar Awal',
            'snippet' => 'Ini surat awal',
            'body_text' => 'Ini surat awal',
            'body_html' => '<p>Ini surat awal</p>',
            'is_read' => true,
            'delivery_status' => 'delivered',
            'sent_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->actingAs($this->account)
            ->getJson(route('webmail.check-updates', [
                'folder' => 'inbox',
                'last_email_id' => $email->id,
                'known_total' => 1,
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'changed' => false,
            'has_new' => false,
            'new_count' => 0,
            'latest_id' => $email->id,
            'total' => 1,
        ]);
        $this->assertArrayNotHasKey('html', $response->json());
    }

    public function test_check_updates_detects_new_incoming_email_and_returns_html(): void
    {
        $email1 = Email::create([
            'mail_account_id' => $this->account->id,
            'mail_folder_id' => $this->inbox->id,
            'message_id' => '<old-msg@external.com>',
            'from_name' => 'Pengirim Lama',
            'from_email' => 'lama@external.com',
            'to' => [['address' => 'budi@mybbs.id', 'name' => 'Budi']],
            'subject' => 'Pesan Lama',
            'snippet' => 'Ini cuplikan lama',
            'body_text' => 'Ini pesan lama',
            'body_html' => '<p>Ini pesan lama</p>',
            'is_read' => true,
            'delivery_status' => 'delivered',
            'sent_at' => Carbon::now()->subMinutes(10),
        ]);

        // Client is currently displaying $email1
        // Now simulate new incoming email arriving in inbox (e.g. via webhook or SMTP)
        $newEmail = Email::create([
            'mail_account_id' => $this->account->id,
            'mail_folder_id' => $this->inbox->id,
            'message_id' => '<new-inbound-msg@external.com>',
            'from_name' => 'Bos Besar',
            'from_email' => 'bos@external.com',
            'to' => [['address' => 'budi@mybbs.id', 'name' => 'Budi']],
            'subject' => 'Penting: Konfirmasi Proyek Baru',
            'snippet' => 'Tolong review dokumen terlampir hari ini',
            'body_text' => 'Tolong review dokumen terlampir hari ini',
            'body_html' => '<p>Tolong review dokumen terlampir hari ini</p>',
            'is_read' => false,
            'delivery_status' => 'delivered',
            'sent_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->account)
            ->getJson(route('webmail.check-updates', [
                'folder' => 'inbox',
                'last_email_id' => $email1->id,
                'known_total' => 1,
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'changed' => true,
            'has_new' => true,
            'new_count' => 1,
            'latest_id' => $newEmail->id,
            'total' => 2,
            'newest_email' => [
                'id' => $newEmail->id,
                'from' => 'Bos Besar',
                'subject' => 'Penting: Konfirmasi Proyek Baru',
            ],
        ]);

        $this->assertArrayHasKey('html', $response->json());
        $this->assertStringContainsString('Penting: Konfirmasi Proyek Baru', $response->json('html'));
        $this->assertStringContainsString('Bos Besar', $response->json('html'));
        $this->assertEquals(1, $response->json('inbox_unread'));
    }

    public function test_check_updates_force_refresh_returns_fresh_html(): void
    {
        $email = Email::create([
            'mail_account_id' => $this->account->id,
            'mail_folder_id' => $this->inbox->id,
            'message_id' => '<force-msg@external.com>',
            'from_name' => 'Tester',
            'from_email' => 'tester@external.com',
            'to' => [['address' => 'budi@mybbs.id', 'name' => 'Budi']],
            'subject' => 'Test Manual Refresh',
            'snippet' => 'Test',
            'body_text' => 'Test',
            'body_html' => '<p>Test</p>',
            'is_read' => true,
            'delivery_status' => 'delivered',
            'sent_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->account)
            ->getJson(route('webmail.check-updates', [
                'folder' => 'inbox',
                'last_email_id' => $email->id,
                'known_total' => 1,
                'force' => '1',
            ]));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'changed' => true,
        ]);
        $this->assertArrayHasKey('html', $response->json());
    }
}
