<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\SmtpRelay;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReplyAllTest extends TestCase
{
    use RefreshDatabase;

    protected MailAccount $currentUser;
    protected Domain $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domain = Domain::create([
            'name' => 'azkario.biz.id',
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
            'sender_email' => 'noreply@azkario.biz.id',
            'sender_name' => 'ApexMail Test',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->currentUser = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'kucing',
            'email' => 'kucing@azkario.biz.id',
            'display_name' => 'Kucing Azkario',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'is_admin' => false,
        ]);
        $this->currentUser->createDefaultFolders();
    }

    public function test_email_extract_addresses_and_reply_all_recipients()
    {
        $email = new Email([
            'from_name' => 'Direktur Utama',
            'from_email' => 'direktur@partner.com',
            'to' => [
                'kucing@azkario.biz.id',
                'luthfi@azkario.biz.id',
                'finance@partner.com',
            ],
            'cc' => [
                'supervisor@external.com',
                'kucing@azkario.biz.id', // duplicate/self
            ],
            'subject' => 'Laporan Koordinasi Tim Proyek',
        ]);

        $replyAll = $email->getReplyAllRecipients('kucing@azkario.biz.id');

        // To should be the original sender
        $this->assertEquals(['direktur@partner.com'], $replyAll['to']);

        // CC should contain other To and CC, excluding sender and current user
        $expectedCc = ['luthfi@azkario.biz.id', 'finance@partner.com', 'supervisor@external.com'];
        $this->assertEquals($expectedCc, $replyAll['cc']);
        $this->assertTrue($replyAll['has_multiple']);
        $this->assertEquals(4, $replyAll['total_count']);
    }

    public function test_quick_reply_single_sends_only_to_sender()
    {
        $inbox = $this->currentUser->folders()->where('slug', 'inbox')->first();

        // Create sender local account
        $colleague = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'luthfi',
            'email' => 'luthfi@azkario.biz.id',
            'display_name' => 'Luthfi Azkario',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $colleague->createDefaultFolders();

        $originalEmail = $inbox->emails()->create([
            'mail_account_id' => $this->currentUser->id,
            'message_id' => '<test-single@azkario.biz.id>',
            'from_name' => 'Luthfi',
            'from_email' => 'luthfi@azkario.biz.id',
            'to' => ['kucing@azkario.biz.id'],
            'cc' => ['other@azkario.biz.id'],
            'subject' => 'Project Kickoff',
            'body_text' => 'Discussion topic',
            'body_html' => '<p>Discussion topic</p>',
        ]);

        $response = $this->actingAs($this->currentUser)
            ->withSession(['active_account_id' => $this->currentUser->id])
            ->from(route('webmail.index'))
            ->post(route('webmail.quick-reply'), [
                'email_id' => $originalEmail->id,
                'is_reply_all' => 0,
                'body_html' => '<p>Baik Pak, sudah saya terima.</p>',
            ]);

        $response->assertSessionHas('success');

        // Verify sent folder
        $sentFolder = $this->currentUser->folders()->where('slug', 'sent')->first();
        $sentEmail = $sentFolder->emails()->latest('id')->first();

        $this->assertNotNull($sentEmail);
        $this->assertEquals('Re: Project Kickoff', $sentEmail->subject);
        $this->assertCount(1, $sentEmail->to);
        $this->assertEquals('luthfi@azkario.biz.id', $sentEmail->to[0]['email'] ?? $sentEmail->to[0]);
        $this->assertEmpty($sentEmail->cc);
    }

    public function test_quick_reply_all_sends_to_sender_and_all_other_recipients()
    {
        $inbox = $this->currentUser->folders()->where('slug', 'inbox')->first();

        // Create colleague account
        $colleague = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'luthfi2',
            'email' => 'luthfi2@azkario.biz.id',
            'display_name' => 'Luthfi 2',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $colleague->createDefaultFolders();

        $originalEmail = $inbox->emails()->create([
            'mail_account_id' => $this->currentUser->id,
            'message_id' => '<test-all@azkario.biz.id>',
            'from_name' => 'Luthfi',
            'from_email' => 'luthfi2@azkario.biz.id',
            'to' => ['kucing@azkario.biz.id', 'supervisor@partner.com'],
            'cc' => ['audit@partner.com', 'kucing@azkario.biz.id'],
            'subject' => 'Team Sync Meeting',
            'body_text' => 'Discussion topic',
            'body_html' => '<p>Discussion topic</p>',
        ]);

        $response = $this->actingAs($this->currentUser)
            ->withSession(['active_account_id' => $this->currentUser->id])
            ->from(route('webmail.index'))
            ->post(route('webmail.quick-reply'), [
                'email_id' => $originalEmail->id,
                'is_reply_all' => 1,
                'body_html' => '<p>Siap, saya dan tim hadir lengkap!</p>',
            ]);

        // Verify sent folder record is created with correct To and Cc
        $sentFolder = $this->currentUser->folders()->where('slug', 'sent')->first();
        $sentEmail = $sentFolder->emails()->latest('id')->first();

        $this->assertNotNull($sentEmail);
        $this->assertEquals('Re: Team Sync Meeting', $sentEmail->subject);

        // To must be luthfi2@azkario.biz.id
        $this->assertCount(1, $sentEmail->to);
        $this->assertEquals('luthfi2@azkario.biz.id', $sentEmail->to[0]['email'] ?? $sentEmail->to[0]);

        // CC must contain supervisor@partner.com and audit@partner.com, but NOT current user kucing@azkario.biz.id
        $this->assertNotEmpty($sentEmail->cc);
        $ccEmails = array_map(fn($item) => is_array($item) ? ($item['email'] ?? '') : $item, $sentEmail->cc);
        $this->assertContains('supervisor@partner.com', $ccEmails);
        $this->assertContains('audit@partner.com', $ccEmails);
        $this->assertNotContains('kucing@azkario.biz.id', $ccEmails);

        // Sender From email must be kucing@azkario.biz.id, NOT no-reply@azkario.biz.id
        $this->assertEquals('kucing@azkario.biz.id', $sentEmail->from_email);
        $this->assertNotEquals('no-reply@azkario.biz.id', $sentEmail->from_email);
    }

    public function test_quick_reply_from_sent_folder_targets_original_recipient_not_self()
    {
        $sentFolder = $this->currentUser->folders()->where('slug', 'sent')->first();

        $colleague = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'luthfi_sent',
            'email' => 'luthfi@azkario.biz.id',
            'display_name' => 'Luthfi Azkario',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $colleague->createDefaultFolders();

        // Email previously sent by currentUser to colleague
        $sentOriginal = $sentFolder->emails()->create([
            'mail_account_id' => $this->currentUser->id,
            'message_id' => '<sent-origin@azkario.biz.id>',
            'from_name' => 'Kucing Azkario',
            'from_email' => 'kucing@azkario.biz.id',
            'to' => ['luthfi@azkario.biz.id'],
            'cc' => ['partner@external.com'],
            'subject' => 'Instruksi Tugas Baru',
            'body_text' => 'Tolong kerjakan task A',
            'body_html' => '<p>Tolong kerjakan task A</p>',
            'is_read' => true,
        ]);

        // 1. Single reply helper must target original recipient, not self
        $singleRecipients = $sentOriginal->getReplySingleRecipients($this->currentUser->email);
        $this->assertEquals(['luthfi@azkario.biz.id'], $singleRecipients['to']);
        $this->assertTrue($singleRecipients['is_self_sender']);

        // 2. Perform quick single reply via HTTP request
        $response = $this->actingAs($this->currentUser)
            ->withSession(['active_account_id' => $this->currentUser->id])
            ->from(route('webmail.index', ['folder' => 'sent']))
            ->post(route('webmail.quick-reply'), [
                'email_id' => $sentOriginal->id,
                'is_reply_all' => 0,
                'body_html' => '<p>Menambahkan update instruksi...</p>',
            ]);

        $response->assertSessionHas('success');

        // Check newest sent email
        $followUp = $sentFolder->emails()->latest('id')->first();
        $this->assertNotNull($followUp);
        $this->assertEquals('Re: Instruksi Tugas Baru', $followUp->subject);
        $this->assertEquals('kucing@azkario.biz.id', $followUp->from_email);

        // Recipient must be luthfi@azkario.biz.id, NOT kucing@azkario.biz.id (self)
        $this->assertCount(1, $followUp->to);
        $this->assertEquals('luthfi@azkario.biz.id', $followUp->to[0]['email'] ?? $followUp->to[0]);
        $this->assertEmpty($followUp->cc);
    }
}
