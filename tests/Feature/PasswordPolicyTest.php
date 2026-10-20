<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MailAccount;
use App\Models\PasswordHistory;
use App\Services\MailRelayService;
use App\Services\PasswordPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;
    protected Domain $domain;
    protected PasswordPolicyService $policyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policyService = app(PasswordPolicyService::class);

        $relayMock = Mockery::mock(MailRelayService::class);
        $relayMock->shouldReceive('sendNewDeviceLoginAlert')->andReturn(['success' => true]);
        $relayMock->shouldReceive('sendPasswordResetOtp')->andReturn(['success' => true]);
        $relayMock->shouldReceive('maskEmail')->andReturn('r***@example.com');
        $this->app->instance(MailRelayService::class, $relayMock);

        $this->domain = Domain::firstOrCreate(
            ['name' => 'azkario.biz.id'],
            ['description' => 'Azkario Domain', 'is_active' => true]
        );
    }

    public function test_password_policy_unit_validations()
    {
        $account = MailAccount::firstOrCreate(
            ['email' => 'policytest@azkario.biz.id'],
            [
                'domain_id' => $this->domain->id,
                'username' => 'policytest',
                'password' => Hash::make('CurrentPass123!'),
                'display_name' => 'Policy Test',
                'recovery_email' => 'rec@azkario.biz.id',
                'is_active' => true,
                'quota_bytes' => 1073741824,
            ]
        );

        // 1. Less than 8 characters -> fail
        $res = $this->policyService->validatePassword('Ab1!', $account);
        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('minimal harus 8 karakter', implode(' ', $res['errors']));

        // 2. No uppercase letter -> fail
        $res = $this->policyService->validatePassword('lowercase123!', $account);
        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('huruf besar', implode(' ', $res['errors']));

        // 3. No special symbol -> fail
        $res = $this->policyService->validatePassword('Uppercase123', $account);
        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('simbol unik', implode(' ', $res['errors']));

        // 4. Same as current password -> fail!
        $res = $this->policyService->validatePassword('CurrentPass123!', $account);
        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('tidak boleh sama dengan kata sandi sebelumnya', implode(' ', $res['errors']));

        // 5. Valid new password
        $res = $this->policyService->validatePassword('NewSecurePass2026@', $account);
        $this->assertTrue($res['valid']);

        // 6. Record past password and test reuse prevention
        $this->policyService->recordPasswordHistory($account, Hash::make('HistoricPass99#'));
        $resHistory = $this->policyService->validatePassword('HistoricPass99#', $account);
        $this->assertFalse($resHistory['valid']);
        $this->assertStringContainsString('riwayat kata sandi sebelumnya', implode(' ', $resHistory['errors']));

        // Cleanup
        PasswordHistory::where('mail_account_id', $account->id)->delete();
        $account->delete();
    }

    public function test_reset_password_with_otp_rejects_same_password()
    {
        $currentPassword = 'OldInitialPass1!';
        $account = MailAccount::firstOrCreate(
            ['email' => 'otpreuse@azkario.biz.id'],
            [
                'domain_id' => $this->domain->id,
                'username' => 'otpreuse',
                'password' => Hash::make($currentPassword),
                'display_name' => 'OTP Reuse Test',
                'recovery_email' => 'recovery@azkario.biz.id',
                'is_active' => true,
                'quota_bytes' => 1073741824,
            ]
        );

        $otp = '123456';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $account->email],
            ['token' => Hash::make($otp), 'created_at' => now()]
        );

        // Try resetting to the SAME password
        $response = $this->post(route('password.reset-otp'), [
            'email' => $account->email,
            'otp' => $otp,
            'password' => $currentPassword,
            'password_confirmation' => $currentPassword,
        ]);

        $response->assertSessionHasErrors('password');
        // Password must still be old password
        $account->refresh();
        $this->assertTrue(Hash::check($currentPassword, $account->password));

        // Try resetting to a valid brand new password
        $newValidPass = 'BrandNewPass2026#';
        $successResponse = $this->post(route('password.reset-otp'), [
            'email' => $account->email,
            'otp' => $otp,
            'password' => $newValidPass,
            'password_confirmation' => $newValidPass,
        ]);

        $successResponse->assertRedirect(route('login'));
        $account->refresh();
        $this->assertTrue(Hash::check($newValidPass, $account->password));

        // History must record the old password
        $this->assertTrue(
            PasswordHistory::where('mail_account_id', $account->id)->exists()
        );

        // Cleanup
        PasswordHistory::where('mail_account_id', $account->id)->delete();
        $account->delete();
    }

    public function test_admin_store_account_enforces_password_rules()
    {
        $admin = MailAccount::firstOrCreate(
            ['email' => 'adminpol@azkario.biz.id'],
            [
                'domain_id' => $this->domain->id,
                'username' => 'adminpol',
                'password' => Hash::make('AdminPass123!'),
                'display_name' => 'Admin Pol',
                'recovery_email' => 'adm@azkario.biz.id',
                'is_admin' => true,
                'is_active' => true,
                'quota_bytes' => 1073741824,
            ]
        );

        // 1. Try creating account with weak password (no symbol)
        $respFail = $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'domain_id' => $this->domain->id,
            'username' => 'userweak',
            'display_name' => 'Weak User',
            'recovery_email' => 'weak@gmail.com',
            'password' => 'Uppercase123',
            'quota_gb' => 5,
        ]);

        $respFail->assertSessionHasErrors('password');

        // 2. Try creating account with valid compliant password
        $respSuccess = $this->actingAs($admin)->post(route('admin.accounts.store'), [
            'domain_id' => $this->domain->id,
            'username' => 'userstrong',
            'display_name' => 'Strong User',
            'recovery_email' => 'strong@gmail.com',
            'password' => 'StrongPass2026$',
            'quota_gb' => 5,
        ]);

        $respSuccess->assertRedirect(route('admin.accounts'));
        $created = MailAccount::where('username', 'userstrong')->first();
        $this->assertNotNull($created);

        // Cleanup
        PasswordHistory::where('mail_account_id', $created->id)->delete();
        $created->delete();
        $admin->delete();
    }
}
