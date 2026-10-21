<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MailAccount;
use App\Models\MailFolder;
use App\Services\MailRelayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Domain $domain;
    protected MailAccount $user;
    protected MailFolder $inbox;

    protected function setUp(): void
    {
        parent::setUp();

        $relayMock = Mockery::mock(MailRelayService::class);
        $relayMock->shouldReceive('sendNewDeviceLoginAlert')->andReturn(['success' => true]);
        $relayMock->shouldReceive('maskEmail')->andReturn('u***@mybbs.id');
        $this->app->instance(MailRelayService::class, $relayMock);

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
        ]);

        $this->inbox = $this->user->folders()->create([
            'name' => 'Kotak Masuk',
            'slug' => 'inbox',
            'icon' => 'inbox',
            'color' => '#2563EB',
            'is_system' => true,
        ]);
    }

    public function test_session_cookie_configuration_attributes(): void
    {
        $this->assertEquals(15, config('session.lifetime'));
        $this->assertTrue(config('session.http_only'));
        $this->assertEquals('strict', config('session.same_site'));
        $this->assertEquals(900, config('session.idle_timeout'));
        $this->assertEquals(28800, config('session.max_absolute_lifetime'));
    }

    public function test_authenticated_request_initializes_security_metadata(): void
    {
        $response = $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get(route('webmail.index'));

        $response->assertStatus(200);
        $response->assertSessionHas('session_bound_ip', '198.51.100.10');
        $response->assertSessionHas('session_login_time');
        $response->assertSessionHas('session_last_activity');
    }

    public function test_ip_binding_destroys_session_on_ip_change(): void
    {
        // Initial session with IP 198.51.100.10
        $this->actingAs($this->user)
            ->withSession([
                'session_bound_ip' => '198.51.100.10',
                'session_login_time' => now()->timestamp,
                'session_last_activity' => now()->timestamp,
            ]);

        // Next request originates from hijacked / changed IP 203.0.113.99
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->get(route('webmail.index'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('perubahan alamat IP', session('error'));
        $this->assertFalse(Auth::check());
    }

    public function test_ip_binding_returns_401_json_for_ajax_request_on_ip_change(): void
    {
        $this->actingAs($this->user)
            ->withSession([
                'session_bound_ip' => '198.51.100.10',
                'session_login_time' => now()->timestamp,
                'session_last_activity' => now()->timestamp,
            ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->json('POST', route('session.keep-alive'));

        $response->assertStatus(401);
        $response->assertJson([
            'session_expired' => true,
            'reason' => 'ip_binding',
        ]);
        $this->assertFalse(Auth::check());
    }

    public function test_idle_timeout_destroys_session_after_15_minutes_inactivity(): void
    {
        $fifteenMinutesAgo = now()->timestamp - (16 * 60);

        $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withSession([
                'session_bound_ip' => '198.51.100.10',
                'session_login_time' => now()->timestamp - 1000,
                'session_last_activity' => $fifteenMinutesAgo,
            ]);

        $response = $this->get(route('webmail.index'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('15 menit', session('error'));
        $this->assertFalse(Auth::check());
    }

    public function test_absolute_timeout_forces_relogin_after_8_hours(): void
    {
        $eightHoursAgo = now()->timestamp - (8 * 3600 + 10);

        $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withSession([
                'session_bound_ip' => '198.51.100.10',
                'session_login_time' => $eightHoursAgo,
                'session_last_activity' => now()->timestamp - 30, // active recently
            ]);

        $response = $this->get(route('webmail.index'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('8 jam', session('error'));
        $this->assertFalse(Auth::check());
    }

    public function test_passive_sync_does_not_update_last_activity_timestamp(): void
    {
        $initialActivity = now()->timestamp - 300; // 5 mins ago

        $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withSession([
                'session_bound_ip' => '198.51.100.10',
                'session_login_time' => now()->timestamp - 600,
                'session_last_activity' => $initialActivity,
            ]);

        // Background poll request
        $response = $this->withHeaders(['X-Passive-Sync' => '1'])
            ->json('GET', route('webmail.check-updates'));

        $response->assertStatus(200);

        // session_last_activity should NOT have been updated
        $this->assertEquals($initialActivity, session('session_last_activity'));
    }

    public function test_keep_alive_endpoint_extends_session(): void
    {
        $initialActivity = now()->timestamp - 300;

        $this->actingAs($this->user)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->withSession([
                'session_bound_ip' => '198.51.100.10',
                'session_login_time' => now()->timestamp - 1000,
                'session_last_activity' => $initialActivity,
            ]);

        $response = $this->json('POST', route('session.keep-alive'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'idle_timeout_seconds' => 900,
            'warning_threshold_seconds' => 60,
        ]);

        $this->assertGreaterThan($initialActivity, session('session_last_activity'));
    }

    public function test_webmail_renders_session_timeout_modal_and_countdown(): void
    {
        $response = $this->actingAs($this->user)->get(route('webmail.index'));

        $response->assertStatus(200);
        $response->assertSee('id="sessionTimeoutModal"', false);
        $response->assertSee('id="sessionCountdownTimer"', false);
        $response->assertSee('Perpanjang Sesi');
        $response->assertSee('Keluar Sekarang');
    }
}
