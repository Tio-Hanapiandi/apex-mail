<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MailAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAndFaviconTest extends TestCase
{
    use RefreshDatabase;

    protected Domain $domain;
    protected MailAccount $account;

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
    }

    public function test_webmail_page_does_not_contain_live_badge(): void
    {
        $response = $this->actingAs($this->account)
            ->get(route('webmail.index'));

        $response->assertStatus(200);
        $response->assertDontSee('>Live</span>', false);
    }

    public function test_seo_meta_tags_and_favicons_rendered_in_layout(): void
    {
        $response = $this->actingAs($this->account)
            ->get(route('webmail.index'));

        $response->assertStatus(200);
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('<meta name="keywords"', false);
        $response->assertSee('<meta property="og:title"', false);
        $response->assertSee('<meta property="og:image"', false);
        $response->assertSee('<meta name="twitter:card"', false);
        $response->assertSee('href="' . asset('favicon.svg') . '"', false);
        $response->assertSee('href="' . asset('favicon.ico') . '"', false);
        $response->assertSee('href="' . asset('apple-touch-icon.png') . '"', false);
        $response->assertSee('href="' . asset('site.webmanifest') . '"', false);
    }

    public function test_login_page_renders_seo_and_favicons(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('href="' . asset('favicon.svg') . '"', false);
        $response->assertSee('href="' . asset('favicon.ico') . '"', false);
    }

    public function test_favicon_assets_exist_and_non_empty(): void
    {
        $this->assertFileExists(public_path('favicon.svg'));
        $this->assertGreaterThan(0, filesize(public_path('favicon.svg')));

        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertGreaterThan(0, filesize(public_path('favicon.ico')));

        $this->assertFileExists(public_path('favicon-32x32.png'));
        $this->assertGreaterThan(0, filesize(public_path('favicon-32x32.png')));

        $this->assertFileExists(public_path('apple-touch-icon.png'));
        $this->assertGreaterThan(0, filesize(public_path('apple-touch-icon.png')));

        $this->assertFileExists(public_path('site.webmanifest'));
        $this->assertGreaterThan(0, filesize(public_path('site.webmanifest')));
    }
}
