<?php

namespace Tests\Feature;

use App\Models\MailAccount;
use App\Models\AccountKnownDevice;
use App\Models\DeviceVerificationToken;
use App\Models\SystemSetting;
use App\Services\MailRelayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class DeviceVerificationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('login_captcha_enabled', '0');

        // Mock MailRelayService to avoid sending real network emails during test
        $relayMock = Mockery::mock(MailRelayService::class);
        $relayMock->shouldReceive('sendNewDeviceLoginAlert')->andReturn(['success' => true, 'message' => 'Sent']);
        $relayMock->shouldReceive('maskEmail')->andReturnUsing(function ($email) {
            $parts = explode('@', $email);
            return substr($parts[0], 0, 1) . '***@' . ($parts[1] ?? 'domain.com');
        });
        $relayMock->shouldReceive('formatIndonesianDateTime')->andReturn('Senin, 5 Oktober 2026 10:00:00');
        $this->app->instance(MailRelayService::class, $relayMock);
    }

    protected function tearDown(): void
    {
        SystemSetting::set('login_captcha_enabled', '1');
        parent::tearDown();
    }

    public function test_full_device_verification_flow()
    {
        // 1. Prepare test account
        $password = 'TestPassword123!';
        $domain = \App\Models\Domain::firstOrCreate(
            ['name' => 'azkario.biz.id'],
            ['description' => 'Azkario Business', 'is_active' => true]
        );

        $account = MailAccount::firstOrCreate(
            ['email' => 'devicetest@azkario.biz.id'],
            [
                'domain_id' => $domain->id,
                'username' => 'devicetest',
                'password' => Hash::make($password),
                'display_name' => 'Device Test User',
                'recovery_email' => 'recovery@azkario.biz.id',
                'is_active' => true,
                'is_admin' => false,
                'quota_bytes' => 1073741824,
            ]
        );

        // Ensure clean state
        AccountKnownDevice::where('mail_account_id', $account->id)->delete();
        DeviceVerificationToken::where('mail_account_id', $account->id)->delete();

        $ip1 = '103.156.88.95';
        $ua1 = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TestBrowser/1.0';

        // 2. First login from new device / IP
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => $ip1,
            'HTTP_USER_AGENT' => $ua1,
        ])->post('/login', [
            'email' => $account->email,
            'password' => $password,
        ]);

        // Must redirect to device challenge
        $response->assertRedirect(route('device.challenge'));
        $this->assertGuest(); // Not authenticated yet!

        // Token must exist in DB
        $tokenRecord = DeviceVerificationToken::where('mail_account_id', $account->id)->first();
        $this->assertNotNull($tokenRecord);

        // 3. Challenge page rendered
        $challengeResponse = $this->withServerVariables([
            'REMOTE_ADDR' => $ip1,
            'HTTP_USER_AGENT' => $ua1,
        ])->withSession([
            'device_pending_account_id' => $account->id,
            'device_ip' => $ip1,
            'device_ua' => $ua1,
        ])->get(route('device.challenge'));

        $challengeResponse->assertStatus(200);
        $challengeResponse->assertSee('Verifikasi Perangkat Baru');
        $challengeResponse->assertSee($ip1);

        // 4. Submit invalid OTP
        $failResponse = $this->withServerVariables([
            'REMOTE_ADDR' => $ip1,
            'HTTP_USER_AGENT' => $ua1,
        ])->withSession([
            'device_pending_account_id' => $account->id,
            'device_ip' => $ip1,
            'device_ua' => $ua1,
        ])->post(route('device.verify'), [
            'otp' => '000000',
        ]);

        $failResponse->assertSessionHas('error');
        $this->assertGuest();

        // 5. Submit valid OTP (simulate known OTP by setting it)
        $validOtp = '654321';
        $tokenRecord->update(['token' => Hash::make($validOtp)]);

        $successResponse = $this->withServerVariables([
            'REMOTE_ADDR' => $ip1,
            'HTTP_USER_AGENT' => $ua1,
        ])->withSession([
            'device_pending_account_id' => $account->id,
            'device_ip' => $ip1,
            'device_ua' => $ua1,
        ])->post(route('device.verify'), [
            'otp' => $validOtp,
        ]);

        $successResponse->assertRedirect(route('webmail.index'));
        $this->assertAuthenticatedAs($account);

        // Device must now be recorded in account_known_devices
        $this->assertTrue(
            AccountKnownDevice::where('mail_account_id', $account->id)
                ->where('ip_address', $ip1)
                ->exists()
        );

        // Token must be deleted
        $this->assertNull(DeviceVerificationToken::where('mail_account_id', $account->id)->first());

        // 6. Next login from same device / IP -> Directly enters webmail without OTP!
        auth()->logout();
        $this->assertGuest();

        $secondLoginResponse = $this->withServerVariables([
            'REMOTE_ADDR' => $ip1,
            'HTTP_USER_AGENT' => $ua1,
        ])->post('/login', [
            'email' => $account->email,
            'password' => $password,
        ]);

        $secondLoginResponse->assertRedirect(route('webmail.index'));
        $this->assertAuthenticatedAs($account);

        // 7. Login from a NEW location / IP (e.g. 10.10.10.10) -> Prompts OTP again!
        auth()->logout();
        $this->assertGuest();

        $ip2 = '10.10.10.10';
        $newIpResponse = $this->withServerVariables([
            'REMOTE_ADDR' => $ip2,
            'HTTP_USER_AGENT' => $ua1,
        ])->post('/login', [
            'email' => $account->email,
            'password' => $password,
        ]);

        $newIpResponse->assertRedirect(route('device.challenge'));
        $this->assertGuest();

        // Cleanup test data
        AccountKnownDevice::where('mail_account_id', $account->id)->delete();
        DeviceVerificationToken::where('mail_account_id', $account->id)->delete();
        $account->delete();
    }

    public function test_admin_device_verification_resend_and_cancel()
    {
        $domain = \App\Models\Domain::firstOrCreate(
            ['name' => 'azkario.biz.id'],
            ['description' => 'Azkario Business', 'is_active' => true]
        );

        $password = 'AdminPassword123!';
        $admin = MailAccount::firstOrCreate(
            ['email' => 'admintest@azkario.biz.id'],
            [
                'domain_id' => $domain->id,
                'username' => 'admintest',
                'password' => Hash::make($password),
                'display_name' => 'Admin Test User',
                'recovery_email' => 'admin.recovery@azkario.biz.id',
                'is_active' => true,
                'is_admin' => true,
                'quota_bytes' => 1073741824,
            ]
        );

        AccountKnownDevice::where('mail_account_id', $admin->id)->delete();
        DeviceVerificationToken::where('mail_account_id', $admin->id)->delete();

        $ip = '103.156.88.99';
        $ua = 'Mozilla/5.0 AdminConsoleClient/1.0';

        // 1. Admin login from new device via unified /login
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $ua,
        ])->post('/login', [
            'email' => $admin->email,
            'password' => $password,
        ]);

        $response->assertRedirect(route('device.challenge'));
        $this->assertGuest();

        // 2. Test Resend OTP
        $resendResponse = $this->withServerVariables([
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $ua,
        ])->withSession([
            'device_pending_account_id' => $admin->id,
            'device_is_admin' => false,
            'device_ip' => $ip,
            'device_ua' => $ua,
        ])->post(route('device.resend'));

        $resendResponse->assertSessionHas('success');

        // 3. Test Cancel
        $cancelResponse = $this->withServerVariables([
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $ua,
        ])->withSession([
            'device_pending_account_id' => $admin->id,
            'device_is_admin' => false,
        ])->post(route('device.cancel'));

        $cancelResponse->assertRedirect(route('login'));
        $this->assertGuest();

        // 4. Test verify OTP -> redirects to webmail.index
        $tokenRecord = DeviceVerificationToken::where('mail_account_id', $admin->id)->first();
        $tokenRecord->update(['token' => Hash::make('888999')]);

        $verifyResponse = $this->withServerVariables([
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $ua,
        ])->withSession([
            'device_pending_account_id' => $admin->id,
            'device_is_admin' => false,
            'device_ip' => $ip,
            'device_ua' => $ua,
        ])->post(route('device.verify'), [
            'otp' => '888999',
        ]);

        $verifyResponse->assertRedirect(route('webmail.index'));
        $this->assertAuthenticatedAs($admin);

        // Cleanup
        AccountKnownDevice::where('mail_account_id', $admin->id)->delete();
        DeviceVerificationToken::where('mail_account_id', $admin->id)->delete();
        $admin->delete();
    }
}
