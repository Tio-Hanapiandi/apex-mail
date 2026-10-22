<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MailAccount;
use App\Models\SystemSetting;
use App\Models\AccountKnownDevice;
use App\Services\MailRelayService;
use App\Services\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class CaptchaLoginTest extends TestCase
{
    use RefreshDatabase;
    protected Domain $domain;
    protected MailAccount $user;
    protected MailAccount $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $relayMock = Mockery::mock(MailRelayService::class);
        $relayMock->shouldReceive('sendNewDeviceLoginAlert')->andReturn(['success' => true]);
        $relayMock->shouldReceive('sendPasswordResetOtp')->andReturn(['success' => true]);
        $relayMock->shouldReceive('maskEmail')->andReturn('r***@azkario.biz.id');
        $this->app->instance(MailRelayService::class, $relayMock);

        $this->domain = Domain::firstOrCreate(
            ['name' => 'azkario.biz.id'],
            ['description' => 'Azkario Domain', 'is_active' => true]
        );

        $this->user = MailAccount::firstOrCreate(
            ['email' => 'captchatest@azkario.biz.id'],
            [
                'domain_id' => $this->domain->id,
                'username' => 'captchatest',
                'password' => Hash::make('UserPassword2026!'),
                'display_name' => 'Captcha User Test',
                'recovery_email' => 'rec.captchatest@azkario.biz.id',
                'is_active' => true,
                'is_admin' => false,
                'quota_bytes' => 1073741824,
            ]
        );

        $this->admin = MailAccount::firstOrCreate(
            ['email' => 'admincaptcha@azkario.biz.id'],
            [
                'domain_id' => $this->domain->id,
                'username' => 'admincaptcha',
                'password' => Hash::make('AdminPassword2026!'),
                'display_name' => 'Admin Captcha Test',
                'recovery_email' => 'rec.admincaptcha@azkario.biz.id',
                'is_active' => true,
                'is_admin' => true,
                'quota_bytes' => 1073741824,
            ]
        );

        // Pre-register known device for testing regular login bypass
        AccountKnownDevice::firstOrCreate([
            'mail_account_id' => $this->user->id,
            'device_signature' => hash('sha256', $this->user->id . '|127.0.0.1|PHPUnit'),
        ], [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'last_login_at' => now(),
        ]);

        AccountKnownDevice::firstOrCreate([
            'mail_account_id' => $this->admin->id,
            'device_signature' => hash('sha256', $this->admin->id . '|127.0.0.1|PHPUnit'),
        ], [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'last_login_at' => now(),
        ]);

        SystemSetting::set('login_captcha_enabled', '1');
    }

    public function test_captcha_image_route_generates_image_and_session()
    {
        $response = $this->get(route('captcha.image'));

        $response->assertStatus(200);
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
        $this->assertNotEmpty(session('login_captcha'));
    }

    public function test_user_login_fails_with_invalid_captcha()
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ])->withSession([
            'login_captcha' => 'ABCDE',
            'login_captcha_expires_at' => now()->addMinutes(10)->timestamp,
        ])->post('/login', [
            'email' => $this->user->email,
            'password' => 'UserPassword2026!',
            'captcha' => 'WRONG1',
        ]);

        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_user_login_succeeds_with_valid_captcha()
    {
        $validCaptcha = 'x7k9p';

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ])->withSession([
            'login_captcha' => strtolower($validCaptcha),
            'login_captcha_expires_at' => now()->addMinutes(10)->timestamp,
        ])->post('/login', [
            'email' => $this->user->email,
            'password' => 'UserPassword2026!',
            'captcha' => strtoupper($validCaptcha), // Case-insensitive test
        ]);

        $response->assertRedirect(route('webmail.index'));
        $this->assertAuthenticatedAs($this->user);

        // Captcha must be consumed
        $this->assertNull(session('login_captcha'));
    }

    public function test_admin_login_via_unified_login_succeeds_with_valid_captcha()
    {
        $validCaptcha = 'adm99';

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ])->withSession([
            'login_captcha' => strtolower($validCaptcha),
            'login_captcha_expires_at' => now()->addMinutes(10)->timestamp,
        ])->post('/login', [
            'email' => $this->admin->email,
            'password' => 'AdminPassword2026!',
            'captcha' => 'ADM99',
        ]);

        $response->assertRedirect(route('webmail.index'));
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_unauthenticated_access_to_admin_redirects_to_login()
    {
        $response = $this->get('/admin');
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_admin_can_access_admin_dashboard()
    {
        $response = $this->actingAs($this->admin)->get('/admin');
        $response->assertStatus(200);
    }

    public function test_authenticated_non_admin_cannot_access_admin_dashboard()
    {
        $response = $this->actingAs($this->user)->get('/admin');
        $response->assertRedirect(route('webmail.index'));
        $response->assertSessionHas('error');
    }

    public function test_login_works_when_captcha_is_disabled()
    {
        SystemSetting::set('login_captcha_enabled', '0');

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ])->post('/login', [
            'email' => $this->user->email,
            'password' => 'UserPassword2026!',
            // No captcha passed
        ]);

        $response->assertRedirect(route('webmail.index'));
        $this->assertAuthenticatedAs($this->user);
    }

    protected function tearDown(): void
    {
        SystemSetting::set('login_captcha_enabled', '1');

        AccountKnownDevice::where('mail_account_id', $this->user->id)->delete();
        AccountKnownDevice::where('mail_account_id', $this->admin->id)->delete();
        $this->user->delete();
        $this->admin->delete();

        parent::tearDown();
    }
}
