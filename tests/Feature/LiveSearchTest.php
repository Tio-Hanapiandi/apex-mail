<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Email;
use App\Models\MailAccount;
use App\Models\MailFolder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveSearchTest extends TestCase
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
            'username' => 'sarah',
            'email' => 'sarah@mybbs.id',
            'display_name' => 'Sarah Connor',
            'password' => bcrypt('SarahSecret123!'),
            'quota_bytes' => 1073741824,
            'used_bytes' => 0,
            'is_admin' => false,
            'is_active' => true,
        ]);
        $this->account->createDefaultFolders();
        $this->inbox = $this->account->folders()->where('slug', 'inbox')->firstOrFail();

        // Create sample emails
        Email::create([
            'mail_account_id' => $this->account->id,
            'mail_folder_id' => $this->inbox->id,
            'message_id' => '<msg-1@client.com>',
            'from_name' => 'Budi Santoso',
            'from_email' => 'budi@client.com',
            'to' => [['address' => 'sarah@mybbs.id', 'name' => 'Sarah']],
            'subject' => 'Laporan Keuangan Q3',
            'snippet' => 'Berikut ringkasan kinerja keuangan kuartal 3',
            'body_text' => 'Berikut ringkasan kinerja keuangan kuartal 3...',
            'body_html' => '<p>Berikut ringkasan kinerja keuangan kuartal 3...</p>',
            'is_read' => true,
            'is_starred' => false,
            'delivery_status' => 'delivered',
            'sent_at' => Carbon::now()->subHours(2),
        ]);

        Email::create([
            'mail_account_id' => $this->account->id,
            'mail_folder_id' => $this->inbox->id,
            'message_id' => '<msg-2@vendor.com>',
            'from_name' => 'Citra Lestari',
            'from_email' => 'citra@vendor.com',
            'to' => [['address' => 'sarah@mybbs.id', 'name' => 'Sarah']],
            'subject' => 'Penawaran Kerjasama Cloud Server',
            'snippet' => 'Proposal penyediaan dedicated hosting server',
            'body_text' => 'Proposal penyediaan dedicated hosting server...',
            'body_html' => '<p>Proposal penyediaan dedicated hosting server...</p>',
            'is_read' => false,
            'is_starred' => true,
            'delivery_status' => 'delivered',
            'sent_at' => Carbon::now()->subHour(),
        ]);
    }

    public function test_webmail_page_contains_live_search_ui_elements(): void
    {
        $response = $this->actingAs($this->account)
            ->get(route('webmail.index'));

        $response->assertStatus(200);
        $response->assertSee('id="searchInput"', false);
        $response->assertSee('id="searchSpinner"', false);
        $response->assertSee('id="searchClearBtn"', false);
        $response->assertSee('id="searchKbdBadge"', false);
        $response->assertSee('id="searchStatusBar"', false);
        $response->assertSee('id="emailTotalBadge"', false);
        $response->assertSee('performLiveSearch', false);
    }

    public function test_ajax_live_search_returns_json_with_filtered_results(): void
    {
        // Search for 'Keuangan'
        $response = $this->actingAs($this->account)
            ->getJson(route('webmail.index', ['folder' => 'inbox', 'q' => 'Keuangan']));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'html',
            'paginationHtml',
            'total',
            'hasPages',
            'query',
        ]);

        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('Keuangan', $response->json('query'));
        $this->assertStringContainsString('Laporan Keuangan Q3', $response->json('html'));
        $this->assertStringContainsString('Budi Santoso', $response->json('html'));
        $this->assertStringNotContainsString('Penawaran Kerjasama Cloud Server', $response->json('html'));
    }

    public function test_ajax_live_search_supports_from_name_and_sender_search(): void
    {
        // Search for sender name 'Citra'
        $response = $this->actingAs($this->account)
            ->getJson(route('webmail.index', ['folder' => 'inbox', 'q' => 'Citra']));

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
        $this->assertStringContainsString('Penawaran Kerjasama Cloud Server', $response->json('html'));
        $this->assertStringContainsString('Citra Lestari', $response->json('html'));
        $this->assertStringNotContainsString('Laporan Keuangan Q3', $response->json('html'));
    }

    public function test_ajax_live_search_empty_query_returns_all_folder_emails(): void
    {
        $response = $this->actingAs($this->account)
            ->getJson(route('webmail.index', ['folder' => 'inbox']));

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total'));
        $this->assertStringContainsString('Laporan Keuangan Q3', $response->json('html'));
        $this->assertStringContainsString('Penawaran Kerjasama Cloud Server', $response->json('html'));
    }

    public function test_ajax_live_search_with_no_matches_shows_empty_state_and_reset_button(): void
    {
        $response = $this->actingAs($this->account)
            ->getJson(route('webmail.index', ['folder' => 'inbox', 'q' => 'KataKunciYangTidakAdaSamaSekali']));

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('total'));
        $this->assertStringContainsString('Tidak ada email ditemukan', $response->json('html'));
        $this->assertStringContainsString('clearLiveSearch()', $response->json('html'));
    }
}
