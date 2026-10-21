<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Services\InboundMailService;
use App\Services\MailRelayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailForwardingTest extends TestCase
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
            'is_active' => true,
        ]);

        $this->account = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'budi',
            'email' => 'budi@mybbs.id',
            'display_name' => 'Budi Santoso',
            'password' => Hash::make('Secret123!'),
            'quota_bytes' => 1073741824,
            'used_bytes' => 0,
            'is_active' => true,
            'forward_active' => false,
            'forward_destination' => null,
            'forward_keep_copy' => true,
        ]);

        $this->inbox = $this->account->folders()->create([
            'name' => 'Kotak Masuk',
            'slug' => 'inbox',
            'icon' => 'inbox',
            'color' => '#2563EB',
            'is_system' => true,
        ]);
    }

    public function test_user_can_view_forwarding_settings_page(): void
    {
        $response = $this->actingAs($this->account)->get(route('webmail.forwarding'));

        $response->assertStatus(200);
        $response->assertSee('Penerusan Email (Email Forwarding)');
        $response->assertSee('Alamat Email Tujuan Penerusan');
    }

    public function test_user_can_enable_and_update_forwarding_settings(): void
    {
        $response = $this->actingAs($this->account)->post(route('webmail.forwarding.update'), [
            'forward_active' => '1',
            'forward_destination' => 'budi.personal@gmail.com, backup@yahoo.com',
            'forward_keep_copy' => '1',
        ]);

        $response->assertRedirect(route('webmail.forwarding'));
        $response->assertSessionHas('success');

        $this->account->refresh();
        $this->assertTrue($this->account->forward_active);
        $this->assertEquals('budi.personal@gmail.com, backup@yahoo.com', $this->account->forward_destination);
        $this->assertTrue($this->account->forward_keep_copy);
    }

    public function test_forwarding_rejects_self_email_to_prevent_loop(): void
    {
        $response = $this->actingAs($this->account)->post(route('webmail.forwarding.update'), [
            'forward_active' => '1',
            'forward_destination' => 'budi@mybbs.id',
            'forward_keep_copy' => '1',
        ]);

        $response->assertSessionHas('warning');

        $this->account->refresh();
        $this->assertFalse($this->account->forward_active);
    }

    public function test_forwarding_requires_destination_when_active(): void
    {
        $response = $this->actingAs($this->account)->post(route('webmail.forwarding.update'), [
            'forward_active' => '1',
            'forward_destination' => '',
            'forward_keep_copy' => '1',
        ]);

        $response->assertSessionHas('warning');
    }

    public function test_inbound_mail_auto_forwards_when_enabled(): void
    {
        $this->account->update([
            'forward_active' => true,
            'forward_destination' => 'destination@gmail.com',
            'forward_keep_copy' => true,
        ]);

        // Mock MailRelayService to verify sendEmail is invoked for auto-forward
        $mockRelay = $this->createMock(MailRelayService::class);
        $mockRelay->method('parseRecipients')->willReturn([
            ['email' => 'destination@gmail.com', 'name' => 'destination@gmail.com']
        ]);
        $mockRelay->expects($this->once())
            ->method('sendEmail')
            ->with(
                $this->callback(fn($sender) => $sender->id === $this->account->id),
                $this->callback(fn($dest) => count($dest) === 1 && $dest[0]['email'] === 'destination@gmail.com'),
                $this->stringContains('Fwd: Halo dari Pengirim'),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->callback(fn($replyTo) => $replyTo['email'] === 'pengirim@external.com')
            )
            ->willReturn(['success' => true]);

        $inboundService = new InboundMailService($mockRelay);

        $payload = [
            'From' => ['Address' => 'pengirim@external.com', 'Name' => 'Pengirim Luar'],
            'To' => [['Address' => 'budi@mybbs.id', 'Name' => 'Budi']],
            'Subject' => 'Halo dari Pengirim',
            'RawTextBody' => 'Ini isi pesan asli dari pengirim luar.',
            'MessageId' => '<inbound-test-123@external.com>',
        ];

        $request = Request::create('/api/mail/inbound', 'POST', $payload);
        $res = $inboundService->storeInboundEmail($payload, $request);

        $this->assertEquals('delivered', $res['status']);
        $this->assertDatabaseHas('emails', [
            'mail_account_id' => $this->account->id,
            'subject' => 'Halo dari Pengirim',
        ]);
    }

    public function test_inbound_mail_auto_forwards_and_removes_copy_when_keep_copy_false(): void
    {
        $this->account->update([
            'forward_active' => true,
            'forward_destination' => 'destination@gmail.com',
            'forward_keep_copy' => false,
        ]);

        $mockRelay = $this->createMock(MailRelayService::class);
        $mockRelay->method('parseRecipients')->willReturn([
            ['email' => 'destination@gmail.com', 'name' => 'destination@gmail.com']
        ]);
        $mockRelay->expects($this->once())
            ->method('sendEmail')
            ->willReturn(['success' => true]);

        $inboundService = new InboundMailService($mockRelay);

        $payload = [
            'From' => ['Address' => 'pengirim@external.com', 'Name' => 'Pengirim Luar'],
            'To' => [['Address' => 'budi@mybbs.id', 'Name' => 'Budi']],
            'Subject' => 'Pesan Rahasia Diteruskan',
            'RawTextBody' => 'Pesan ini tidak boleh disimpan di server.',
            'MessageId' => '<inbound-test-secret@external.com>',
        ];

        $request = Request::create('/api/mail/inbound', 'POST', $payload);
        $res = $inboundService->storeInboundEmail($payload, $request);

        $this->assertEquals('delivered', $res['status']);
        // Check that email is NOT present in database because keep_copy is false
        $this->assertDatabaseMissing('emails', [
            'mail_account_id' => $this->account->id,
            'subject' => 'Pesan Rahasia Diteruskan',
        ]);
    }

    public function test_webmail_detail_shows_teruskan_button_and_forward_script(): void
    {
        $email = $this->inbox->emails()->create([
            'mail_account_id' => $this->account->id,
            'message_id' => '<test-1@external.com>',
            'from_name' => 'Alice Client',
            'from_email' => 'alice@client.com',
            'to' => [['email' => 'budi@mybbs.id', 'name' => 'Budi Santoso']],
            'subject' => 'Penawaran Kerjasama Bisnis',
            'snippet' => 'Halo Budi, berikut penawaran...',
            'body_text' => 'Halo Budi, berikut penawaran lengkap kami.',
            'body_html' => '<p>Halo Budi, berikut penawaran lengkap kami.</p>',
            'sent_at' => now(),
            'received_at' => now(),
        ]);

        $response = $this->actingAs($this->account)->get(route('webmail.index', [
            'folder' => 'inbox',
            'email' => $email->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Teruskan');
        $response->assertSee('window.currentEmailForward', false);
        $response->assertSee('Fwd: Penawaran Kerjasama Bisnis');
    }
}
