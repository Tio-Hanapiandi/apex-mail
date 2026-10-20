<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MailAccount;
use App\Models\SmtpRelay;
use App\Models\Email;
use App\Services\MailRelayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiRecipientSendTest extends TestCase
{
    use RefreshDatabase;

    protected MailAccount $sender;
    protected Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domain = Domain::create([
            'name' => 'apexmail.local',
            'is_active' => true,
            'is_verified' => true,
        ]);

        SmtpRelay::create([
            'domain_id' => $this->domain->id,
            'name' => 'Test Relay',
            'provider' => 'brevo',
            'host' => '127.0.0.1',
            'port' => 1025,
            'encryption' => 'none',
            'sender_email' => 'noreply@apexmail.local',
            'sender_name' => 'ApexMail Test',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->sender = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'sender',
            'email' => 'sender@apexmail.local',
            'display_name' => 'Sender User',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'is_admin' => false,
        ]);
        $this->sender->createDefaultFolders();
    }

    public function test_parse_recipients_various_formats()
    {
        $service = app(MailRelayService::class);

        // 1. Comma separated
        $res1 = $service->parseRecipients('user1@domain.com, user2@domain.com');
        $this->assertCount(2, $res1);
        $this->assertEquals('user1@domain.com', $res1[0]['email']);
        $this->assertEquals('user2@domain.com', $res1[1]['email']);

        // 2. Semicolon separated
        $res2 = $service->parseRecipients('user1@domain.com; user2@domain.com');
        $this->assertCount(2, $res2);
        $this->assertEquals('user1@domain.com', $res2[0]['email']);
        $this->assertEquals('user2@domain.com', $res2[1]['email']);

        // 3. Space separated
        $res3 = $service->parseRecipients('user1@domain.com user2@domain.com');
        $this->assertCount(2, $res3);
        $this->assertEquals('user1@domain.com', $res3[0]['email']);
        $this->assertEquals('user2@domain.com', $res3[1]['email']);

        // 4. Formatted with name and quotes
        $res4 = $service->parseRecipients('"Budi Pratama" <budi@apexmail.local>; sarah@apexmail.local');
        $this->assertCount(2, $res4);
        $this->assertEquals('budi@apexmail.local', $res4[0]['email']);
        $this->assertEquals('Budi Pratama', $res4[0]['name']);
        $this->assertEquals('sarah@apexmail.local', $res4[1]['email']);

        // 5. Deduplication
        $res5 = $service->parseRecipients('user1@domain.com, user1@domain.com');
        $this->assertCount(1, $res5);
    }

    public function test_send_email_controller_with_multiple_recipients()
    {
        $response = $this->actingAs($this->sender)
            ->withSession(['active_account_id' => $this->sender->id])
            ->post(route('webmail.send'), [
                'to' => 'partner1@example.com; partner2@example.com partner3@example.com',
                'cc' => 'supervisor@example.com',
                'bcc' => 'audit@example.com',
                'subject' => 'Multiple Recipient Test',
                'body_html' => '<p>Hello everyone</p>',
            ]);

        // Email is recorded in Sent folder regardless of whether test SMTP daemon is online
        $email = Email::where('subject', 'Multiple Recipient Test')->first();
        $this->assertNotNull($email);

        $toEmails = array_column($email->to, 'email');
        $this->assertContains('partner1@example.com', $toEmails);
        $this->assertContains('partner2@example.com', $toEmails);
        $this->assertContains('partner3@example.com', $toEmails);

        $ccEmails = array_column($email->cc, 'email');
        $this->assertContains('supervisor@example.com', $ccEmails);

        $bccEmails = array_column($email->bcc, 'email');
        $this->assertContains('audit@example.com', $bccEmails);
    }

    public function test_send_email_controller_rejects_empty_or_invalid_recipients()
    {
        $response = $this->actingAs($this->sender)
            ->withSession(['active_account_id' => $this->sender->id])
            ->from(route('webmail.index'))
            ->post(route('webmail.send'), [
                'to' => 'notanemail, anotherinvalid',
                'subject' => 'Invalid Test',
                'body_html' => '<p>Test</p>',
            ]);

        $response->assertRedirect(route('webmail.index'));
        $response->assertSessionHas('warning');
    }
}
