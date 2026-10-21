<?php

namespace Tests\Feature;

use App\Models\BlockedSender;
use App\Models\Domain;
use App\Models\Email;
use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Services\InboundMailService;
use App\Services\MailRelayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class SpamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Domain $domain;
    protected MailAccount $user;
    protected MailAccount $admin;
    protected MailFolder $userInbox;
    protected MailFolder $userSpam;
    protected $mockRelay;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock MailRelayService to prevent actual network/mailer calls
        $this->mockRelay = Mockery::mock(MailRelayService::class);
        $this->mockRelay->shouldReceive('sendNewDeviceLoginAlert')->andReturn(['success' => true]);
        $this->mockRelay->shouldReceive('maskEmail')->andReturn('u***@mybbs.id');
        $this->mockRelay->shouldReceive('sendEmail')->andReturn(['success' => true]);
        $this->app->instance(MailRelayService::class, $this->mockRelay);

        $this->domain = Domain::create([
            'name' => 'mybbs.id',
            'is_active' => true,
        ]);

        $this->user = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'budi',
            'email' => 'budi@mybbs.id',
            'display_name' => 'Budi Santoso',
            'password' => Hash::make('Secret123!'),
            'quota_bytes' => 1073741824,
            'used_bytes' => 0,
            'is_active' => true,
            'is_admin' => false,
            'forward_active' => false,
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

        $this->userInbox = $this->user->folders()->create([
            'name' => 'Kotak Masuk',
            'slug' => 'inbox',
            'icon' => 'inbox',
            'color' => '#2563EB',
            'is_system' => true,
        ]);

        $this->userSpam = $this->user->folders()->create([
            'name' => 'Spam',
            'slug' => 'spam',
            'icon' => 'alert-octagon',
            'color' => '#EF4444',
            'is_system' => true,
        ]);
    }

    public function test_user_can_mark_email_as_spam_and_blocks_sender(): void
    {
        $email = Email::create([
            'mail_account_id' => $this->user->id,
            'mail_folder_id' => $this->userInbox->id,
            'message_id' => 'test-spam-1@suspicious.com',
            'from_name' => 'Spammer Name',
            'from_email' => 'spammer@suspicious.com',
            'to' => [['email' => $this->user->email]],
            'subject' => 'Menang Undian Berhadiah',
            'body_text' => 'Klik link ini segera untuk klaim hadiah.',
            'size_bytes' => 1024,
            'is_read' => true,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->post(route('webmail.mark-spam', $email->id));

        $response->assertRedirect(route('webmail.index', ['folder' => 'inbox']));
        $response->assertSessionHas('success');

        // Assert email moved to Spam folder
        $this->assertEquals($this->userSpam->id, $email->fresh()->mail_folder_id);

        // Assert BlockedSender entry was created for user
        $this->assertDatabaseHas('blocked_senders', [
            'mail_account_id' => $this->user->id,
            'sender_email' => 'spammer@suspicious.com',
        ]);

        $this->assertTrue(BlockedSender::isBlocked($this->user->id, 'spammer@suspicious.com'));
    }

    public function test_inbound_email_from_blocked_sender_is_routed_to_spam_folder(): void
    {
        // Pre-block the spam sender for this user
        BlockedSender::blockSender($this->user->id, 'badguy@lottery.org', 'Tandai spam manual');

        // Simulate incoming email using InboundMailService
        $service = new InboundMailService($this->mockRelay);
        $payload = [
            'From' => ['Address' => 'badguy@lottery.org', 'Name' => 'Bad Guy'],
            'To' => [['Address' => $this->user->email, 'Name' => $this->user->display_name]],
            'Subject' => 'Pinjaman Kilat Tanpa Jaminan',
            'RawTextBody' => 'Isi email spam promosi...',
            'MessageId' => '<spam-test-456@lottery.org>',
        ];

        $request = Request::create('/api/mail/inbound', 'POST', $payload);
        $result = $service->storeInboundEmail($payload, $request);

        $this->assertEquals('delivered', $result['status']);

        // Assert that the email arrived in the Spam folder, NOT Inbox
        $deliveredEmail = Email::where('mail_account_id', $this->user->id)
            ->where('from_email', 'badguy@lottery.org')
            ->first();

        $this->assertNotNull($deliveredEmail);
        $this->assertEquals($this->userSpam->id, $deliveredEmail->mail_folder_id);
    }

    public function test_admin_can_view_blocked_senders_page(): void
    {
        BlockedSender::blockSender($this->user->id, 'phishing@bank-palsu.com', 'Percobaan phishing');

        $response = $this->actingAs($this->admin)->get(route('admin.spam.index'));

        $response->assertStatus(200);
        $response->assertSee('Filter Spam');
        $response->assertSee('phishing@bank-palsu.com');
        $response->assertSee($this->user->email);
    }

    public function test_admin_can_unblock_sender_and_restore_emails_to_inbox(): void
    {
        // 1. User blocks sender
        $rule = BlockedSender::blockSender($this->user->id, 'partner@partnerdomain.com', 'Human error');

        // 2. Put an email in spam folder
        $spamEmail = Email::create([
            'mail_account_id' => $this->user->id,
            'mail_folder_id' => $this->userSpam->id,
            'message_id' => 'important-partner@partnerdomain.com',
            'from_name' => 'Partner Bisnis',
            'from_email' => 'partner@partnerdomain.com',
            'to' => [['email' => $this->user->email]],
            'subject' => 'Kontrak Kerjasama 2026',
            'body_text' => 'Lampiran surat kontrak.',
            'size_bytes' => 2048,
            'received_at' => now(),
        ]);

        // 3. Admin unblocks sender with restore_emails = 1
        $response = $this->actingAs($this->admin)->delete(route('admin.spam.unblock', $rule->id), [
            'restore_emails' => '1',
        ]);

        $response->assertSessionHas('success');

        // Assert rule deleted
        $this->assertDatabaseMissing('blocked_senders', [
            'id' => $rule->id,
        ]);
        $this->assertFalse(BlockedSender::isBlocked($this->user->id, 'partner@partnerdomain.com'));

        // Assert spam email was restored back to Inbox folder
        $this->assertEquals($this->userInbox->id, $spamEmail->fresh()->mail_folder_id);

        // 4. Inbound email from that sender now routes to inbox
        $service = new InboundMailService($this->mockRelay);
        $payload = [
            'From' => ['Address' => 'partner@partnerdomain.com', 'Name' => 'Partner Bisnis'],
            'To' => [['Address' => $this->user->email, 'Name' => $this->user->display_name]],
            'Subject' => 'Invoice Baru 2026',
            'RawTextBody' => 'Isi surat tagihan resmi...',
            'MessageId' => '<partner-inv-999@partnerdomain.com>',
        ];

        $request = Request::create('/api/mail/inbound', 'POST', $payload);
        $result = $service->storeInboundEmail($payload, $request);

        $this->assertEquals('delivered', $result['status']);
        $newDelivered = Email::where('mail_account_id', $this->user->id)
            ->where('subject', 'Invoice Baru 2026')
            ->first();
        $this->assertNotNull($newDelivered);
        $this->assertEquals($this->userInbox->id, $newDelivered->mail_folder_id);
    }

    public function test_admin_can_manually_block_sender_globally_and_per_account(): void
    {
        // Admin blocks sender globally (mail_account_id = null)
        $response = $this->actingAs($this->admin)->post(route('admin.spam.store'), [
            'mail_account_id' => '',
            'sender_email' => 'globalspammer@darknet.org',
            'reason' => 'Global ransomware campaign',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('blocked_senders', [
            'mail_account_id' => null,
            'sender_email' => 'globalspammer@darknet.org',
        ]);
        $this->assertTrue(BlockedSender::isBlocked(null, 'globalspammer@darknet.org'));
        $this->assertTrue(BlockedSender::isBlocked($this->user->id, 'globalspammer@darknet.org'));
    }

    public function test_user_can_unmark_spam_in_webmail(): void
    {
        // Block and put email in Spam
        BlockedSender::blockSender($this->user->id, 'info@client.com');

        $email = Email::create([
            'mail_account_id' => $this->user->id,
            'mail_folder_id' => $this->userSpam->id,
            'message_id' => 'client-msg-1@client.com',
            'from_name' => 'Client',
            'from_email' => 'info@client.com',
            'to' => [['email' => $this->user->email]],
            'subject' => 'Permintaan Penawaran',
            'body_text' => 'Halo, kami butuh penawaran...',
            'size_bytes' => 1500,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->post(route('webmail.unmark-spam', $email->id));

        $response->assertSessionHas('success');

        // Email moved back to inbox
        $this->assertEquals($this->userInbox->id, $email->fresh()->mail_folder_id);

        // Sender unblocked
        $this->assertFalse(BlockedSender::isBlocked($this->user->id, 'info@client.com'));
    }

    public function test_batch_action_mark_and_unmark_spam(): void
    {
        $email1 = Email::create([
            'mail_account_id' => $this->user->id,
            'mail_folder_id' => $this->userInbox->id,
            'message_id' => 'batch-1@spam.org',
            'from_email' => 'spammer1@spam.org',
            'to' => [['email' => $this->user->email]],
            'subject' => 'Spam 1',
            'body_text' => 'Tawaran',
            'size_bytes' => 500,
            'received_at' => now(),
        ]);

        $email2 = Email::create([
            'mail_account_id' => $this->user->id,
            'mail_folder_id' => $this->userInbox->id,
            'message_id' => 'batch-2@spam.org',
            'from_email' => 'spammer2@spam.org',
            'to' => [['email' => $this->user->email]],
            'subject' => 'Spam 2',
            'body_text' => 'Tawaran',
            'size_bytes' => 500,
            'received_at' => now(),
        ]);

        // Batch mark spam
        $res = $this->actingAs($this->user)->post(route('webmail.batch-action'), [
            'action' => 'mark_spam',
            'email_ids' => [$email1->id, $email2->id],
        ]);

        $res->assertSessionHas('success');
        $this->assertEquals($this->userSpam->id, $email1->fresh()->mail_folder_id);
        $this->assertEquals($this->userSpam->id, $email2->fresh()->mail_folder_id);
        $this->assertTrue(BlockedSender::isBlocked($this->user->id, 'spammer1@spam.org'));
        $this->assertTrue(BlockedSender::isBlocked($this->user->id, 'spammer2@spam.org'));

        // Batch unmark spam
        $res2 = $this->actingAs($this->user)->post(route('webmail.batch-action'), [
            'action' => 'unmark_spam',
            'email_ids' => [$email1->id, $email2->id],
        ]);

        $res2->assertSessionHas('success');
        $this->assertEquals($this->userInbox->id, $email1->fresh()->mail_folder_id);
        $this->assertEquals($this->userInbox->id, $email2->fresh()->mail_folder_id);
        $this->assertFalse(BlockedSender::isBlocked($this->user->id, 'spammer1@spam.org'));
        $this->assertFalse(BlockedSender::isBlocked($this->user->id, 'spammer2@spam.org'));
    }
}
