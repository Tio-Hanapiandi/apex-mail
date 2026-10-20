<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MailAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DomainAutoDnsTest extends TestCase
{
    use RefreshDatabase;

    protected Domain $domain;
    protected MailAccount $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domain = Domain::create([
            'name' => 'azkario.biz.id',
            'is_active' => true,
            'is_verified' => true,
        ]);
        $this->domain->generateDkimKeys();

        $this->admin = MailAccount::create([
            'domain_id' => $this->domain->id,
            'username' => 'admin',
            'email' => 'admin@azkario.biz.id',
            'display_name' => 'System Administrator',
            'password' => Hash::make('AdminPassword123!'),
            'is_active' => true,
            'is_admin' => true,
        ]);
        $this->admin->createDefaultFolders();
    }

    public function test_admin_can_view_domains_page_with_auto_generated_records(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.domains', ['domain_id' => $this->domain->id]));

        $response->assertStatus(200);
        $response->assertSee('azkario.biz.id');
        $response->assertSee('apexmail._domainkey');
        $response->assertSee('DKIM 2048-bit RSA');
        $response->assertSee('v=DKIM1; k=rsa; p=');
        $response->assertSee('103.156.88.95');
    }

    public function test_admin_store_new_domain_automatically_generates_dkim_and_dns_records(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.domains.store'), [
            'name' => 'baruberkembang.co.id',
            'description' => 'Domain Baru Organisasi',
            'dkim_selector' => 'apexmail',
        ]);

        $newDomain = Domain::where('name', 'baruberkembang.co.id')->first();
        $this->assertNotNull($newDomain, 'Domain should be created in database');
        $this->assertNotEmpty($newDomain->dkim_private_key, 'DKIM private key should be automatically generated');
        $this->assertNotEmpty($newDomain->dkim_public_key, 'DKIM public key should be automatically generated');
        $this->assertStringStartsWith('v=DKIM1; k=rsa; p=', $newDomain->dkim_dns_record);
        $this->assertStringContainsString('103.156.88.95', $newDomain->spf_dns_record);
        $this->assertStringContainsString('_dmarc', $newDomain->all_dns_records_list[2]['name']);

        $response->assertRedirect(route('admin.domains', ['domain_id' => $newDomain->id, 'created' => 1]));
        $response->assertSessionHas('success');
    }

    public function test_store_domain_rejects_invalid_domain_name(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.domains.store'), [
            'name' => 'invalid domain name with spaces',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('domains', ['name' => 'invalid domain name with spaces']);
    }

    public function test_admin_can_check_live_dns_status(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.domains.check-dns', $this->domain->id));

        $response->assertRedirect(route('admin.domains', ['domain_id' => $this->domain->id]));
        $response->assertSessionHas('dns_check_result');
        $response->assertSessionHas('success');
    }

    public function test_admin_can_export_dns_records_as_plaintext(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.domains.export-dns', $this->domain->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $content = $response->getContent();
        $this->assertStringContainsString('PANDUAN REKAM DNS APEXMAIL', $content);
        $this->assertStringContainsString('azkario.biz.id', $content);
        $this->assertStringContainsString('v=DKIM1; k=rsa; p=', $content);
        $this->assertStringContainsString('103.156.88.95', $content);
    }

    public function test_admin_can_regenerate_dkim_keys(): void
    {
        $oldPubKey = $this->domain->dkim_public_key;

        $response = $this->actingAs($this->admin)->post(route('admin.domains.dkim.regenerate', $this->domain->id));

        $response->assertRedirect(route('admin.domains', ['domain_id' => $this->domain->id]));
        $response->assertSessionHas('success');

        $this->domain->refresh();
        $this->assertNotEmpty($this->domain->dkim_public_key);
        $this->assertNotEquals($oldPubKey, $this->domain->dkim_public_key, 'DKIM public key should be regenerated');
    }

    public function test_cannot_delete_domain_with_active_accounts_without_force(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('admin.domains.destroy', $this->domain->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('domains', ['id' => $this->domain->id]);
    }

    public function test_can_delete_unused_domain(): void
    {
        $emptyDomain = Domain::create([
            'name' => 'unused-domain.org',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.domains.destroy', $emptyDomain->id));

        $response->assertRedirect(route('admin.domains'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('domains', ['id' => $emptyDomain->id]);
    }
}
