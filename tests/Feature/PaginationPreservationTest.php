<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Email;
use App\Models\MailAccount;
use App\Models\MailFolder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationPreservationTest extends TestCase
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
            'username' => 'office',
            'email' => 'office@mybbs.id',
            'display_name' => 'Office MyBBS',
            'password' => bcrypt('OfficeSecret123!'),
            'quota_bytes' => 1073741824,
            'used_bytes' => 0,
            'is_admin' => false,
            'is_active' => true,
        ]);
        $this->account->createDefaultFolders();
        $this->inbox = $this->account->folders()->where('slug', 'inbox')->firstOrFail();

        // Create 60 emails in inbox so there are 3 pages (25 + 25 + 10)
        for ($i = 1; $i <= 60; $i++) {
            Email::create([
                'mail_account_id' => $this->account->id,
                'mail_folder_id' => $this->inbox->id,
                'message_id' => "<msg-{$i}@external.com>",
                'from_name' => "Pengirim {$i}",
                'from_email' => "sender{$i}@external.com",
                'to' => [['address' => 'office@mybbs.id', 'name' => 'Office']],
                'subject' => "Surat Masuk Ke-{$i}",
                'body_text' => "Isi surat pesan ke {$i}",
                'body_html' => "<p>Isi surat pesan ke {$i}</p>",
                'is_read' => true,
                'delivery_status' => 'delivered',
                'sent_at' => Carbon::now()->subMinutes(60 - $i),
                'received_at' => Carbon::now()->subMinutes(60 - $i),
            ]);
        }
    }

    /**
     * Test page parameter is preserved when accessing page 3 and clicking an email.
     */
    public function test_email_row_click_retains_page_query_parameter(): void
    {
        // 1. Visit page 3 directly
        $response = $this->actingAs($this->account)
            ->get(route('webmail.index', ['folder' => 'inbox', 'page' => 3]));

        $response->assertStatus(200);
        $response->assertSee('Hal 3 dari 3');

        // Verify that the email rows generated in page 3 have page=3 in their onclick route
        $emailsOnPage3 = $response->viewData('emails');
        $this->assertEquals(3, $emailsOnPage3->currentPage());
        $this->assertEquals(10, $emailsOnPage3->count()); // 60 total, page 3 has 10 items

        $firstEmailPage3 = $emailsOnPage3->first();
        $expectedUrlSegment = "email={$firstEmailPage3->id}";
        $expectedPageSegment = "page=3";

        $response->assertSee($expectedUrlSegment, false);
        $response->assertSee($expectedPageSegment, false);

        // 2. Click the email on page 3 -> Visit with email & page=3
        $clickResponse = $this->actingAs($this->account)
            ->get(route('webmail.index', [
                'folder' => 'inbox',
                'email' => $firstEmailPage3->id,
                'page' => 3,
            ]));

        $clickResponse->assertStatus(200);
        // Verify current page remains 3, not reset to page 1!
        $this->assertEquals(3, $clickResponse->viewData('emails')->currentPage());
        $this->assertEquals($firstEmailPage3->id, $clickResponse->viewData('selectedEmail')->id);
        $clickResponse->assertSee('Hal 3 dari 3');
    }

    /**
     * Test deleting an email from page 3 redirects back to page 3.
     */
    public function test_deleting_email_from_page_3_preserves_page_query(): void
    {
        $emails = Email::where('mail_folder_id', $this->inbox->id)->orderBy('sent_at', 'desc')->paginate(25, ['*'], 'page', 3);
        $targetEmail = $emails->first();

        $response = $this->actingAs($this->account)
            ->delete(route('webmail.destroy', $targetEmail->id), [
                'page' => 3,
                'folder' => 'inbox',
            ]);

        $response->assertRedirect(route('webmail.index', ['folder' => 'inbox', 'page' => 3]));
    }
}
