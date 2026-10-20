<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use App\Services\Google2FAService;
use App\Services\MailRelayService;
use App\Services\LoginSecurityService;
use App\Services\PasswordPolicyService;
use App\Services\CaptchaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    protected LoginSecurityService $loginSecurity;
    protected PasswordPolicyService $policyService;
    protected CaptchaService $captchaService;

    public function __construct(
        LoginSecurityService $loginSecurity,
        PasswordPolicyService $policyService,
        CaptchaService $captchaService
    ) {
        $this->loginSecurity = $loginSecurity;
        $this->policyService = $policyService;
        $this->captchaService = $captchaService;
    }

    /**
     * Generate dynamic CAPTCHA image response.
     */
    public function captchaImage()
    {
        return $this->captchaService->generateImageResponse();
    }

    /**
     * Display the standard Corporate Webmail Login Page.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('webmail.index');
        }

        $captchaEnabled = $this->captchaService->isEnabled();

        return view('auth.login', compact('captchaEnabled'));
    }

    /**
     * Handle user login authentication.
     */
    public function login(Request $request)
    {
        $validationRules = [
            'email' => 'required|email',
            'password' => 'required|string',
        ];
        $validationMessages = [];

        if ($this->captchaService->isEnabled()) {
            $validationRules['captcha'] = 'required|string';
            $validationMessages['captcha.required'] = 'Kode verifikasi CAPTCHA wajib diisi.';
        }

        $credentials = $request->validate($validationRules, $validationMessages);

        // Verify CAPTCHA security
        if ($this->captchaService->isEnabled() && !$this->captchaService->validate($request->input('captcha'))) {
            return redirect()->back()
                ->withInput($request->only('email'))
                ->with('error', 'Kode CAPTCHA yang Anda masukkan salah atau telah kedaluwarsa. Silakan coba kembali.');
        }

        $remember = $request->has('remember');

        // Check active status
        $account = MailAccount::where('email', strtolower($credentials['email']))->first();
        if ($account && !$account->is_active) {
            return redirect()->back()
                ->withInput($request->only('email'))
                ->with('error', 'Akun Anda sedang dinonaktifkan / ditangguhkan. Silakan hubungi Administrator IT.');
        }

        // Validate credentials without logging in immediately
        if (Auth::validate(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            // Check if 2FA (Google Authenticator) is enabled for this user
            if ($account && $account->two_factor_enabled && !empty($account->two_factor_secret)) {
                $request->session()->put('2fa_pending_account_id', $account->id);
                $request->session()->put('2fa_remember', $remember);
                $request->session()->put('2fa_is_admin', false);

                return redirect()->route('two-factor.challenge');
            }

            // Check if device / location is known
            if (!$this->loginSecurity->isDeviceKnown($account, $request)) {
                $initResult = $this->loginSecurity->initiateDeviceVerification($account, $request, $remember, false);
                $maskedEmail = $initResult['masked_email'] ?? 'email Anda';

                return redirect()->route('device.challenge')
                    ->with('info', "Perangkat atau lokasi baru terdeteksi. Kode OTP verifikasi telah dikirimkan ke {$maskedEmail}.");
            }

            // Normal login for verified known device
            Auth::login($account, $remember);
            $request->session()->regenerate();
            Session::put('active_account_id', $account->id);

            // Record login history for known device
            $this->loginSecurity->recordKnownDeviceLogin($account, $request);

            return redirect()->intended(route('webmail.index'))
                ->with('success', 'Selamat datang kembali, ' . $account->display_name);
        }

        return redirect()->back()
            ->withInput($request->only('email'))
            ->with('error', 'Alamat email atau kata sandi yang Anda masukkan salah.');
    }

    /**
     * Logout and destroy session.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah berhasil keluar dari akun email.');
    }

    /**
     * Display the Forgot Password page.
     */
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send OTP verification code to user's recovery email.
     */
    public function sendResetOtp(Request $request, MailRelayService $relayService)
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Silakan masukkan alamat email akun ApexMail Anda.',
            'email.email' => 'Format alamat email tidak valid.',
        ]);

        $email = strtolower(trim($request->input('email')));
        $account = MailAccount::where('email', $email)
            ->orWhere('recovery_email', $email)
            ->first();

        if (!$account) {
            return redirect()->back()
                ->withInput()
                ->with('error', "Alamat email '{$email}' tidak terdaftar di sistem.");
        }

        if (empty($account->recovery_email)) {
            return redirect()->back()
                ->withInput()
                ->with('error', "Akun ini belum memiliki email pemulihan yang didaftarkan oleh Administrator. Silakan hubungi Administrator IT Anda untuk mereset kata sandi.");
        }

        // Generate 6-digit numeric OTP code
        $otp = (string) random_int(100000, 999999);

        // Store hashed OTP code in password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $account->email],
            [
                'token' => Hash::make($otp),
                'created_at' => Carbon::now(),
            ]
        );

        // Dispatch OTP to recovery email via SMTP Relay
        $sendResult = $relayService->sendPasswordResetOtp($account, $otp);

        if (!$sendResult['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $sendResult['message']);
        }

        session(['reset_password_email' => $account->email]);

        return redirect()->route('password.verify-otp')->with(
            'success',
            "Kode OTP verifikasi berhasil dikirimkan ke email pemulihan Anda (" . $relayService->maskEmail($account->recovery_email) . "). Kode ini berlaku selama 15 menit."
        );
    }

    /**
     * Display the OTP Verification and New Password form.
     */
    public function showVerifyOtp(Request $request, MailRelayService $relayService)
    {
        $email = session('reset_password_email') ?? $request->query('email');

        if (!$email) {
            return redirect()->route('password.forgot')
                ->with('error', 'Sesi verifikasi telah berakhir. Silakan masukkan alamat email Anda kembali.');
        }

        $account = MailAccount::where('email', $email)->first();
        if (!$account) {
            return redirect()->route('password.forgot')->with('error', 'Akun tidak ditemukan.');
        }

        $maskedRecovery = $account->recovery_email ? $relayService->maskEmail($account->recovery_email) : null;
        $passwordPolicy = $this->policyService->getRules();

        return view('auth.verify-otp', compact('account', 'maskedRecovery', 'passwordPolicy'));
    }

    /**
     * Verify OTP and reset account password.
     */
    public function resetPasswordWithOtp(Request $request)
    {
        $email = strtolower(trim($request->input('email')));
        $account = MailAccount::where('email', $email)->first();

        if (!$account) {
            return redirect()->route('password.forgot')->with('error', 'Akun tidak ditemukan.');
        }

        $passwordRules = $this->policyService->getLaravelValidationRules($account, true);
        $passwordRules[] = 'confirmed';

        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => $passwordRules,
        ], [
            'otp.required' => 'Kode OTP 6 digit wajib diisi.',
            'otp.size' => 'Kode OTP harus tepat 6 digit angka.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal harus :min karakter.',
            'password.regex' => 'Kata sandi baru wajib mengandung huruf besar (A-Z) dan simbol unik.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record) {
            return redirect()->back()
                ->withInput($request->only('email', 'otp'))
                ->with('error', 'Permintaan reset sandi tidak ditemukan atau belum diajukan. Silakan ajukan ulang.');
        }

        // Check 15-minute expiration
        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return redirect()->route('password.forgot')
                ->with('error', 'Kode OTP telah kedaluwarsa (lebih dari 15 menit). Silakan minta kode OTP baru.');
        }

        // Verify OTP hash
        if (!Hash::check($request->input('otp'), $record->token)) {
            return redirect()->back()
                ->withInput($request->only('email', 'otp'))
                ->with('error', 'Kode OTP yang Anda masukkan salah. Silakan periksa kembali email pemulihan Anda.');
        }

        // Validate password against policy & previous passwords
        $policyCheck = $this->policyService->validatePassword($request->input('password'), $account);
        if (!$policyCheck['valid']) {
            return redirect()->back()
                ->withInput($request->only('email', 'otp'))
                ->with('error', implode(' ', $policyCheck['errors']));
        }

        // Record old password in history before updating
        if (!empty($account->password)) {
            $this->policyService->recordPasswordHistory($account, $account->password);
        }

        // Success: Update user password
        $newHash = Hash::make($request->input('password'));
        $account->update([
            'password' => $newHash,
        ]);
        $this->policyService->recordPasswordHistory($account, $newHash);

        // Clean up reset token and session
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        session()->forget('reset_password_email');

        return redirect()->route('login')->with(
            'success',
            'Kata sandi Anda berhasil diperbarui! Silakan masuk menggunakan kata sandi baru Anda.'
        );
    }

    /**
     * Show the Two-Factor Authentication Challenge page during login.
     */
    public function showTwoFactorChallenge(Request $request)
    {
        $accountId = $request->session()->get('2fa_pending_account_id');

        if (!$accountId) {
            return redirect()->route('login')
                ->with('error', 'Sesi verifikasi dua langkah tidak ditemukan atau telah berakhir.');
        }

        $account = MailAccount::find($accountId);
        if (!$account) {
            return redirect()->route('login')->with('error', 'Akun tidak ditemukan.');
        }

        $isAdminLogin = (bool) $request->session()->get('2fa_is_admin', false);

        return view('auth.two-factor-challenge', compact('account', 'isAdminLogin'));
    }

    /**
     * Verify the 6-digit TOTP code during Two-Factor Authentication challenge.
     */
    public function verifyTwoFactorChallenge(Request $request, Google2FAService $google2fa)
    {
        $accountId = $request->session()->get('2fa_pending_account_id');

        if (!$accountId) {
            return redirect()->route('login')
                ->with('error', 'Sesi verifikasi dua langkah telah berakhir. Silakan login kembali.');
        }

        $request->validate([
            'code' => 'required|digits:6',
        ], [
            'code.required' => 'Masukkan 6 digit kode dari aplikasi Google Authenticator.',
            'code.digits' => 'Kode verifikasi harus tepat 6 digit angka.',
        ]);

        $account = MailAccount::findOrFail($accountId);

        // Verify with TOTP RFC 6238
        if (!$google2fa->verifyCode($account->two_factor_secret, $request->input('code'))) {
            return redirect()->back()
                ->with('error', 'Kode verifikasi Google Authenticator tidak valid atau telah kedaluwarsa. Pastikan jam di ponsel Anda telah disinkronkan otomatis.');
        }

        $remember = (bool) $request->session()->get('2fa_remember', false);
        $isAdmin = (bool) $request->session()->get('2fa_is_admin', false);

        // Clear temporary 2FA session variables
        $request->session()->forget(['2fa_pending_account_id', '2fa_remember', '2fa_is_admin']);

        // Check if device / location is known after 2FA passes
        if (!$this->loginSecurity->isDeviceKnown($account, $request)) {
            $initResult = $this->loginSecurity->initiateDeviceVerification($account, $request, $remember, $isAdmin);
            $maskedEmail = $initResult['masked_email'] ?? 'email Anda';

            return redirect()->route('device.challenge')
                ->with('info', "Perangkat atau lokasi baru terdeteksi. Kode OTP verifikasi telah dikirimkan ke {$maskedEmail}.");
        }

        // Authenticate the user for known device
        Auth::login($account, $remember);
        $request->session()->regenerate();
        Session::put('active_account_id', $account->id);

        $this->loginSecurity->recordKnownDeviceLogin($account, $request);

        if ($isAdmin) {
            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'Verifikasi 2 langkah berhasil. Selamat datang di Administration Console, ' . $account->display_name);
        }

        return redirect()->intended(route('webmail.index'))
            ->with('success', 'Verifikasi 2 langkah berhasil. Selamat datang kembali, ' . $account->display_name);
    }

    /**
     * Cancel Two-Factor Authentication challenge and return to login.
     */
    public function cancelTwoFactorChallenge(Request $request)
    {
        $request->session()->forget(['2fa_pending_account_id', '2fa_remember', '2fa_is_admin']);

        return redirect()->route('login');
    }

    /**
     * Show the Device Verification Challenge page (New device or location OTP).
     */
    public function showDeviceVerificationChallenge(Request $request, MailRelayService $relayService)
    {
        $accountId = $request->session()->get('device_pending_account_id');

        if (!$accountId) {
            return redirect()->route('login')
                ->with('error', 'Sesi verifikasi perangkat tidak ditemukan atau telah berakhir.');
        }

        $account = MailAccount::find($accountId);
        if (!$account) {
            return redirect()->route('login')->with('error', 'Akun tidak ditemukan.');
        }

        $isAdminLogin = (bool) $request->session()->get('device_is_admin', false);
        $ipAddress = $request->session()->get('device_ip') ?: $this->loginSecurity->getClientIp($request);
        $userAgent = $request->session()->get('device_ua') ?: $this->loginSecurity->getUserAgent($request);
        $targetEmail = !empty($account->recovery_email) ? $account->recovery_email : $account->email;
        $maskedEmail = $relayService->maskEmail($targetEmail);
        $isRecovery = !empty($account->recovery_email);
        $formattedDate = $relayService->formatIndonesianDateTime(Carbon::now());

        return view('auth.device-verification-challenge', compact(
            'account',
            'isAdminLogin',
            'ipAddress',
            'userAgent',
            'maskedEmail',
            'isRecovery',
            'formattedDate'
        ));
    }

    /**
     * Verify the 6-digit OTP code for new device / location verification.
     */
    public function verifyDeviceVerificationChallenge(Request $request)
    {
        $accountId = $request->session()->get('device_pending_account_id');

        if (!$accountId) {
            return redirect()->route('login')
                ->with('error', 'Sesi verifikasi perangkat telah berakhir. Silakan login kembali.');
        }

        $request->validate([
            'otp' => 'required|digits:6',
        ], [
            'otp.required' => 'Masukkan 6 digit kode OTP yang dikirimkan ke email Anda.',
            'otp.digits' => 'Kode OTP harus tepat 6 digit angka.',
        ]);

        $account = MailAccount::findOrFail($accountId);

        $res = $this->loginSecurity->verifyDeviceOtp($account, $request->input('otp'), $request);

        if (!$res['success']) {
            return redirect()->back()->with('error', $res['message']);
        }

        $remember = (bool) $request->session()->get('device_remember', false);
        $isAdmin = (bool) $request->session()->get('device_is_admin', false);

        // Clear temporary session variables
        $request->session()->forget([
            'device_pending_account_id',
            'device_remember',
            'device_is_admin',
            'device_ip',
            'device_ua',
        ]);

        // Complete user login
        Auth::login($account, $remember);
        $request->session()->regenerate();
        Session::put('active_account_id', $account->id);

        if ($isAdmin) {
            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'Perangkat berhasil diverifikasi. Selamat datang di Administration Console, ' . $account->display_name);
        }

        return redirect()->intended(route('webmail.index'))
            ->with('success', 'Perangkat berhasil diverifikasi. Selamat datang di ApexMail, ' . $account->display_name);
    }

    /**
     * Resend device verification OTP.
     */
    public function resendDeviceVerificationOtp(Request $request)
    {
        $accountId = $request->session()->get('device_pending_account_id');

        if (!$accountId) {
            return redirect()->route('login')
                ->with('error', 'Sesi verifikasi perangkat telah berakhir. Silakan login kembali.');
        }

        $account = MailAccount::findOrFail($accountId);
        $res = $this->loginSecurity->resendDeviceOtp($account, $request);

        return redirect()->back()
            ->with('success', "Kode OTP baru telah berhasil dikirimkan ke {$res['masked_email']}. Silakan periksa kotak masuk atau spam.");
    }

    /**
     * Cancel device verification challenge and return to login.
     */
    public function cancelDeviceVerificationChallenge(Request $request)
    {
        $request->session()->forget([
            'device_pending_account_id',
            'device_remember',
            'device_is_admin',
            'device_ip',
            'device_ua',
        ]);

        return redirect()->route('login');
    }
}
