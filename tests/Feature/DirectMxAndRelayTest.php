<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Email;
use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Models\SmtpRelay;
use App\Services\MailRelayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Tests\TestCase;

class DirectMxAndRelayTest extends TestCase
{
    use RefreshDatabase;

    protected Domain $domainMyBbs;
    protected Domain $domainAzkario;
    protected MailAccount $senderRecruitment;
    protected MailAccount $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domainMyBbs = Domain::create([
            'name' => 'mybbs.id',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->domainAzkario = Domain::create([
            'name' => 'azkario.biz.id',
            'is_active' => true,
            'is_verified' => true,
        ]);

        $this->senderRecruitment = MailAccount::create([
            'domain_id' => $this->domainMyBbs->id,
            'username' => 'recruitment',
            'email' => 'recruitment@mybbs.id',
            'display_name' => 'Recruitment Team',
            'password' => Hash::make('SecretPassword123!'),
            'is_active' => true,
            'is_admin' => false,
        ]);
        $this->senderRecruitment->createDefaultFolders();

        $this->admin = MailAccount::create([
            'domain_id' => $this->domainAzkario->id,
            'username' => 'admin',
            'email' => 'admin@azkario.biz.id',
            'display_name' => 'System Admin',
            'password' => Hash::make('AdminPassword123!'),
            'is_active' => true,
            'is_admin' => true,
        ]);
        $this->admin->createDefaultFolders();
    }

    public function test_get_active_relay_returns_null_when_relay_is_inactive(): void
    {
        SmtpRelay::create([
            'domain_id' => null,
            'name' => 'External Relay',
            'host' => 'smtp.example.com',
            'port' => 587,
            'is_active' => false,
            'is_default' => false,
        ]);

        $activeRelay = SmtpRelay::getActiveRelay($this->domainMyBbs->id);
        $this->assertNull($activeRelay);
    }

    public function test_domain_specific_relay_does_not_leak_to_other_domains(): void
    {
        // Relay only for domain Azkario
        SmtpRelay::create([
            'domain_id' => $this->domainAzkario->id,
            'name' => 'Azkario Relay',
            'host' => 'smtp.azkario.local',
            'port' => 587,
            'is_active' => true,
            'is_default' => false,
        ]);

        // Azkario domain should get the relay
        $azkarioRelay = SmtpRelay::getActiveRelay($this->domainAzkario->id);
        $this->assertNotNull($azkarioRelay);
        $this->assertEquals('smtp.azkario.local', $azkarioRelay->host);

        // MyBbs domain should NOT get the Azkario relay
        $mybbsRelay = SmtpRelay::getActiveRelay($this->domainMyBbs->id);
        $this->assertNull($mybbsRelay);
    }

    public function test_get_mx_hosts_resolves_valid_hosts_for_known_domain(): void
    {
        $service = app(MailRelayService::class);
        $hosts = $service->getMxHosts('gmail.com');

        $this->assertNotEmpty($hosts);
        $this->assertContains('gmail-smtp-in.l.google.com', $hosts);
    }

    public function test_send_email_uses_direct_mx_when_no_active_relay_and_preserves_sender(): void
    {
        $service = app(MailRelayService::class);

        // Mock dispatchDirectMx to verify it is called and sender is preserved
        $mockService = $this->getMockBuilder(MailRelayService::class)
            ->onlyMethods(['dispatchDirectMx'])
            ->getMock();

        $mockService->expects($this->once())
            ->method('dispatchDirectMx')
            ->with(
                $this->isInstanceOf(\Symfony\Component\Mime\RawMessage::class),
                $this->callback(function ($recipients) {
                    return count($recipients) === 1 && $recipients[0]['email'] === 'partner@external.com';
                }),
                'recruitment@mybbs.id',
                'Recruitment Team',
                'mybbs.id'
            )
            ->willReturn(['success' => true, 'message' => null, 'details' => []]);

        $result = $mockService->sendEmail(
            $this->senderRecruitment,
            'partner@external.com',
            'Lowongan Pekerjaan',
            '<p>Halo rekan, ini penawaran kami.</p>'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('Pesan terkirim', $result['message']);

        // Verify sent email in database
        $sentEmail = Email::where('subject', 'Lowongan Pekerjaan')->first();
        $this->assertNotNull($sentEmail);
        $this->assertEquals('recruitment@mybbs.id', $sentEmail->from_email);
        $this->assertEquals('delivered', $sentEmail->delivery_status);
    }

    public function test_admin_relays_page_renders_with_outbound_mode(): void
    {
        $relay = SmtpRelay::create([
            'domain_id' => null,
            'name' => 'External SMTP Relay / Smart Host',
            'provider' => 'smarthost',
            'host' => 'mail.relay-example.com',
            'port' => 587,
            'encryption' => 'tls',
            'is_active' => false,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.relays'));
        $response->assertStatus(200);
        $response->assertSee('Direct MX Delivery (DNS Port 25)');
        $response->assertSee('External SMTP Relay / Smart Host');
        $response->assertSee('Semua Domain (Global Smart Host)');
    }

    public function test_admin_can_toggle_relay_active_status(): void
    {
        $relay = SmtpRelay::create([
            'domain_id' => null,
            'name' => 'External Relay',
            'provider' => 'smarthost',
            'host' => 'mail.relay-example.com',
            'port' => 587,
            'is_active' => false,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.relays.update', $relay->id), [
            'name' => 'Production Smart Host',
            'provider' => 'smarthost',
            'host' => 'smtp.custom-smarthost.com',
            'port' => 587,
            'encryption' => 'tls',
            'is_active' => '1',
            'is_default' => '1',
        ]);

        $response->assertRedirect(route('admin.relays'));

        $relay->refresh();
        $this->assertTrue($relay->is_active);
        $this->assertTrue($relay->is_default);
        $this->assertEquals('smtp.custom-smarthost.com', $relay->host);
    }

    public function test_admin_dashboard_shows_direct_mx_when_relay_inactive(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Direct MX Delivery');
        $response->assertSee('Port 25 (Direct DNS Resolution)');
    }

    public function test_admin_can_run_direct_mx_diagnostic(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.relays.test-direct'), [
            'test_email' => 'check@gmail.com',
        ]);

        $response->assertRedirect(route('admin.relays'));
        $response->assertSessionHas('success');
    }

    public function test_domain_generates_dkim_keys_and_signs_email(): void
    {
        $domain = $this->domainMyBbs;
        $generated = $domain->generateDkimKeys();
        $this->assertTrue($generated);
        $this->assertNotEmpty($domain->dkim_private_key);
        $this->assertNotEmpty($domain->dkim_public_key);
        $this->assertStringStartsWith('v=DKIM1; k=rsa; p=', $domain->dkim_dns_record);

        $service = app(MailRelayService::class);
        $message = (new SymfonyEmail())
            ->from('recruitment@mybbs.id')
            ->to('test@gmail.com')
            ->subject('Test DKIM Signing')
            ->text('Testing cryptographic DKIM signature');

        $signedMessage = $service->signWithDkim($message, $this->senderRecruitment);
        $hasDkimHeader = false;
        foreach ($signedMessage->getHeaders()->all() as $header) {
            if (strtolower($header->getName()) === 'dkim-signature') {
                $hasDkimHeader = true;
                $this->assertStringContainsString('d=mybbs.id', $header->getBodyAsString());
                $this->assertStringContainsString('s=apexmail', $header->getBodyAsString());
            }
        }
        $this->assertTrue($hasDkimHeader, 'Message should contain DKIM-Signature header');
    }

    public function test_multi_domain_relays_operate_independently_without_interference(): void
    {
        // 1. Create dedicated relay for domain MyBbs (domain A)
        $relayA = SmtpRelay::create([
            'domain_id' => $this->domainMyBbs->id,
            'name' => 'MyBbs Relay A',
            'provider' => 'standard',
            'host' => 'smtp.relay-domain-a.com',
            'port' => 587,
            'encryption' => 'tls',
            'is_active' => true,
            'is_default' => true,
        ]);

        // 2. Create dedicated relay for domain Azkario (domain B)
        $relayB = SmtpRelay::create([
            'domain_id' => $this->domainAzkario->id,
            'name' => 'Azkario Relay B',
            'provider' => 'custom',
            'host' => 'smtp.relay-domain-b.com',
            'port' => 465,
            'encryption' => 'ssl',
            'is_active' => true,
            'is_default' => true,
        ]);

        // 3. Verify domain A gets Relay A and domain B gets Relay B
        $activeA = SmtpRelay::getActiveRelay($this->domainMyBbs->id);
        $activeB = SmtpRelay::getActiveRelay($this->domainAzkario->id);

        $this->assertNotNull($activeA);
        $this->assertEquals('smtp.relay-domain-a.com', $activeA->host);
        $this->assertEquals($relayA->id, $activeA->id);

        $this->assertNotNull($activeB);
        $this->assertEquals('smtp.relay-domain-b.com', $activeB->host);
        $this->assertEquals($relayB->id, $activeB->id);

        // 4. Update Relay B through Admin Controller (e.g. changing host or re-saving)
        $response = $this->actingAs($this->admin)->put(route('admin.relays.update', $relayB->id), [
            'name' => 'Azkario Relay B Updated',
            'provider' => 'custom',
            'host' => 'smtp.updated-relay-b.com',
            'port' => 587,
            'encryption' => 'tls',
            'domain_id' => $this->domainAzkario->id,
            'is_active' => '1',
            'is_default' => '1',
        ]);

        $response->assertRedirect(route('admin.relays'));

        // 5. CRUCIAL: Verify Relay A for domain A was NOT reset or deactivated
        $relayA->refresh();
        $this->assertTrue($relayA->is_active, 'Relay A must remain active');
        $this->assertTrue($relayA->is_default, 'Relay A must remain default for its domain scope');
        $this->assertEquals($this->domainMyBbs->id, $relayA->domain_id);

        // 6. SmtpRelay::getActiveRelay for domain A still returns Relay A (not null / Direct MX)
        $activeAAfter = SmtpRelay::getActiveRelay($this->domainMyBbs->id);
        $this->assertNotNull($activeAAfter, 'Domain A must still use Relay A and not reset to Direct MX');
        $this->assertEquals('smtp.relay-domain-a.com', $activeAAfter->host);

        // 7. SmtpRelay::getActiveRelay for domain B returns updated Relay B
        $activeBAfter = SmtpRelay::getActiveRelay($this->domainAzkario->id);
        $this->assertNotNull($activeBAfter);
        $this->assertEquals('smtp.updated-relay-b.com', $activeBAfter->host);
    }

    public function test_admin_can_store_new_relay_via_route(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.relays.store'), [
            'name' => 'New Dedicated Relay B',
            'provider' => 'standard',
            'host' => 'smtp.new-relay.com',
            'port' => 587,
            'encryption' => 'tls',
            'domain_id' => $this->domainAzkario->id,
            'is_active' => '1',
            'is_default' => '1',
        ]);

        $response->assertRedirect(route('admin.relays'));
        $this->assertDatabaseHas('smtp_relays', [
            'name' => 'New Dedicated Relay B',
            'domain_id' => $this->domainAzkario->id,
            'host' => 'smtp.new-relay.com',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_delete_relay(): void
    {
        $relay = SmtpRelay::create([
            'domain_id' => $this->domainAzkario->id,
            'name' => 'Temporary Relay',
            'provider' => 'standard',
            'host' => 'smtp.temp.com',
            'port' => 587,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.relays.destroy', $relay->id));
        $response->assertRedirect(route('admin.relays'));

        $this->assertDatabaseMissing('smtp_relays', [
            'id' => $relay->id,
        ]);
    }

    public function test_sending_emails_from_different_domains_uses_respective_relays(): void
    {
        $relayA = SmtpRelay::create([
            'domain_id' => $this->domainMyBbs->id,
            'name' => 'Relay Domain A',
            'provider' => 'standard',
            'host' => 'smtp.domain-a.com',
            'port' => 587,
            'is_active' => true,
            'is_default' => true,
        ]);

        $relayB = SmtpRelay::create([
            'domain_id' => $this->domainAzkario->id,
            'name' => 'Relay Domain B',
            'provider' => 'custom',
            'host' => 'smtp.domain-b.com',
            'port' => 465,
            'is_active' => true,
            'is_default' => true,
        ]);

        $nullTransport = new \Symfony\Component\Mailer\Transport\NullTransport();
        $fakeMailer = new \Symfony\Component\Mailer\Mailer($nullTransport);

        $mockService = $this->getMockBuilder(MailRelayService::class)
            ->onlyMethods(['buildSymfonyMailer'])
            ->getMock();

        $relaysUsed = [];
        $mockService->expects($this->exactly(2))
            ->method('buildSymfonyMailer')
            ->willReturnCallback(function ($relay) use (&$relaysUsed, $fakeMailer) {
                $relaysUsed[] = $relay->host;
                return $fakeMailer;
            });

        // Send email 1 from domain A
        $res1 = $mockService->sendEmail(
            $this->senderRecruitment,
            'external1@test.com',
            'Test Domain A Outbound',
            'Body domain A'
        );
        $this->assertTrue($res1['success']);

        // Send email 2 from domain B
        $res2 = $mockService->sendEmail(
            $this->admin,
            'external2@test.com',
            'Test Domain B Outbound',
            'Body domain B'
        );
        $this->assertTrue($res2['success']);

        // Verify that email 1 used relay A, and email 2 used relay B
        $this->assertCount(2, $relaysUsed);
        $this->assertEquals('smtp.domain-a.com', $relaysUsed[0]);
        $this->assertEquals('smtp.domain-b.com', $relaysUsed[1]);
    }
}
