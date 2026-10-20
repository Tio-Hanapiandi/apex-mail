<?php

namespace App\Services;

use App\Models\MailAccount;
use App\Models\AccountKnownDevice;
use App\Models\LoginHistory;
use App\Models\DeviceVerificationToken;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LoginSecurityService
{
    protected MailRelayService $relayService;

    public function __construct(MailRelayService $relayService)
    {
        $this->relayService = $relayService;
    }

    /**
     * Get client IP address string.
     */
    public function getClientIp(Request $request): string
    {
        $ips = $request->getClientIps();
        return !empty($ips) ? implode(', ', array_unique($ips)) : ($request->ip() ?: '127.0.0.1');
    }

    /**
     * Get client User-Agent string.
     */
    public function getUserAgent(Request $request): string
    {
        return $request->userAgent() ?: 'Unknown Device / Browser';
    }

    /**
     * Generate unique device signature from account id, IP, and User-Agent.
     */
    public function getDeviceSignature(int $accountId, string $ipAddress, string $userAgent): string
    {
        return hash('sha256', $accountId . '|' . $ipAddress . '|' . $userAgent);
    }

    /**
     * Check if current device & location is already verified/known for this account.
     */
    public function isDeviceKnown(MailAccount $account, Request $request): bool
    {
        $ipAddress = $this->getClientIp($request);
        $userAgent = $this->getUserAgent($request);
        $signature = $this->getDeviceSignature($account->id, $ipAddress, $userAgent);

        return AccountKnownDevice::where('mail_account_id', $account->id)
            ->where('device_signature', $signature)
            ->exists();
    }

    /**
     * Update last_login_at and log history for already known device.
     */
    public function recordKnownDeviceLogin(MailAccount $account, Request $request): void
    {
        try {
            $ipAddress = $this->getClientIp($request);
            $userAgent = $this->getUserAgent($request);
            $signature = $this->getDeviceSignature($account->id, $ipAddress, $userAgent);
            $loginTime = Carbon::now();

            AccountKnownDevice::where('mail_account_id', $account->id)
                ->where('device_signature', $signature)
                ->update(['last_login_at' => $loginTime]);

            LoginHistory::create([
                'mail_account_id' => $account->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'is_new_device' => false,
                'is_new_location' => false,
                'alert_sent' => false,
                'login_at' => $loginTime,
            ]);
        } catch (\Throwable $e) {
            Log::error('Gagal mencatat login perangkat dikenal: ' . $e->getMessage());
        }
    }

    /**
     * Initiate device verification challenge by generating 6-digit OTP,
     * storing the hashed token, and sending the alert email.
     */
    public function initiateDeviceVerification(MailAccount $account, Request $request, bool $remember = false, bool $isAdmin = false): array
    {
        $ipAddress = $this->getClientIp($request);
        $userAgent = $this->getUserAgent($request);
        $loginTime = Carbon::now();

        // Generate 6-digit numeric OTP
        $otp = (string) random_int(100000, 999999);

        // Delete any existing tokens for this account
        DeviceVerificationToken::where('mail_account_id', $account->id)->delete();

        // Create new device verification token record (15 minutes expiry)
        DeviceVerificationToken::create([
            'mail_account_id' => $account->id,
            'token' => Hash::make($otp),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        // Put pending challenge details in session
        $request->session()->put('device_pending_account_id', $account->id);
        $request->session()->put('device_remember', $remember);
        $request->session()->put('device_is_admin', $isAdmin);
        $request->session()->put('device_ip', $ipAddress);
        $request->session()->put('device_ua', $userAgent);

        // Dispatch alert email with OTP
        $sendResult = $this->relayService->sendNewDeviceLoginAlert($account, $ipAddress, $userAgent, $loginTime, $otp);

        $targetEmail = !empty($account->recovery_email) ? $account->recovery_email : $account->email;

        return [
            'success' => $sendResult['success'] ?? false,
            'message' => $sendResult['message'] ?? '',
            'target_email' => $targetEmail,
            'masked_email' => $this->relayService->maskEmail($targetEmail),
            'otp' => $otp,
        ];
    }

    /**
     * Resend device verification OTP.
     */
    public function resendDeviceOtp(MailAccount $account, Request $request): array
    {
        $ipAddress = $request->session()->get('device_ip') ?: $this->getClientIp($request);
        $userAgent = $request->session()->get('device_ua') ?: $this->getUserAgent($request);
        $loginTime = Carbon::now();

        $otp = (string) random_int(100000, 999999);

        DeviceVerificationToken::where('mail_account_id', $account->id)->delete();

        DeviceVerificationToken::create([
            'mail_account_id' => $account->id,
            'token' => Hash::make($otp),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        $sendResult = $this->relayService->sendNewDeviceLoginAlert($account, $ipAddress, $userAgent, $loginTime, $otp);
        $targetEmail = !empty($account->recovery_email) ? $account->recovery_email : $account->email;

        return [
            'success' => $sendResult['success'] ?? false,
            'message' => $sendResult['message'] ?? '',
            'target_email' => $targetEmail,
            'masked_email' => $this->relayService->maskEmail($targetEmail),
        ];
    }

    /**
     * Verify submitted OTP against stored token, register known device, and record login history.
     */
    public function verifyDeviceOtp(MailAccount $account, string $otp, Request $request): array
    {
        $record = DeviceVerificationToken::where('mail_account_id', $account->id)->first();

        if (!$record) {
            return [
                'success' => false,
                'message' => 'Permintaan verifikasi perangkat tidak ditemukan. Silakan login kembali.',
            ];
        }

        if (Carbon::parse($record->expires_at)->isPast()) {
            $record->delete();
            return [
                'success' => false,
                'message' => 'Kode OTP telah kedaluwarsa (lebih dari 15 menit). Silakan minta kode OTP baru.',
            ];
        }

        if (!Hash::check($otp, $record->token)) {
            return [
                'success' => false,
                'message' => 'Kode OTP yang Anda masukkan salah. Silakan periksa kembali email Anda.',
            ];
        }

        // OTP is valid!
        $record->delete();

        $ipAddress = $record->ip_address ?: $this->getClientIp($request);
        $userAgent = $record->user_agent ?: $this->getUserAgent($request);
        $loginTime = Carbon::now();
        $deviceSignature = $this->getDeviceSignature($account->id, $ipAddress, $userAgent);

        // Check if UA / IP were known before
        $knownUA = AccountKnownDevice::where('mail_account_id', $account->id)
            ->where('user_agent', $userAgent)
            ->exists();

        $knownIP = AccountKnownDevice::where('mail_account_id', $account->id)
            ->where('ip_address', $ipAddress)
            ->exists();

        $isNewDevice = !$knownUA;
        $isNewLocation = !$knownIP;

        // Register this new verified device & location
        AccountKnownDevice::updateOrCreate(
            [
                'mail_account_id' => $account->id,
                'device_signature' => $deviceSignature,
            ],
            [
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'last_login_at' => $loginTime,
            ]
        );

        // Record in LoginHistory
        LoginHistory::create([
            'mail_account_id' => $account->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'is_new_device' => $isNewDevice,
            'is_new_location' => $isNewLocation,
            'alert_sent' => true,
            'login_at' => $loginTime,
        ]);

        return [
            'success' => true,
            'message' => 'Perangkat dan lokasi baru berhasil diverifikasi.',
        ];
    }
}
