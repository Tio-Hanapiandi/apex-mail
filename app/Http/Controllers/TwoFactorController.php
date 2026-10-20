<?php

namespace App\Http\Controllers;

use App\Services\Google2FAService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    protected Google2FAService $google2fa;

    public function __construct(Google2FAService $google2fa)
    {
        $this->google2fa = $google2fa;
    }

    /**
     * Show the Two-Factor Authentication Security Settings page.
     */
    public function show()
    {
        $user = Auth::user();

        $secret = null;
        $qrCodeUrl = null;
        $otpAuthUrl = null;
        $formattedSecret = null;

        if (!$user->two_factor_enabled) {
            // Retrieve or generate a temporary setup secret stored in session
            $secret = session('2fa_setup_secret');
            if (!$secret) {
                $secret = $this->google2fa->generateSecret(16);
                session(['2fa_setup_secret' => $secret]);
            }

            $otpAuthUrl = $this->google2fa->getOtpAuthUrl('ApexMail', $user->email, $secret);
            $qrCodeUrl = $this->google2fa->getQrCodeUrl($otpAuthUrl, 220);
            $formattedSecret = $this->google2fa->formatSecret($secret);
        }

        return view('auth.two-factor-settings', compact(
            'user',
            'secret',
            'qrCodeUrl',
            'otpAuthUrl',
            'formattedSecret'
        ));
    }

    /**
     * Confirm and activate Two-Factor Authentication with a 6-digit OTP from the app.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ], [
            'code.required' => 'Masukkan 6 digit kode dari aplikasi Google Authenticator.',
            'code.digits' => 'Kode verifikasi harus tepat 6 digit angka.',
        ]);

        $user = Auth::user();
        $secret = session('2fa_setup_secret');

        if (!$secret) {
            return redirect()->route('security.two-factor')
                ->with('error', 'Sesi aktivasi telah berakhir. Silakan muat ulang halaman untuk mencoba kembali.');
        }

        if (!$this->google2fa->verifyCode($secret, $request->input('code'))) {
            return redirect()->back()
                ->with('error', 'Kode verifikasi yang Anda masukkan salah atau telah kedaluwarsa. Pastikan jam di ponsel Anda akurat dan coba lagi.');
        }

        // Enable 2FA for the account
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => Carbon::now(),
        ]);

        session()->forget('2fa_setup_secret');

        return redirect()->route('security.two-factor')
            ->with('success', 'Verifikasi 2 Langkah Google Authenticator berhasil diaktifkan! Akun Anda kini dilindungi lapisan keamanan ganda saat login.');
    }

    /**
     * Disable Two-Factor Authentication with user password verification.
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ], [
            'password.required' => 'Masukkan kata sandi akun Anda untuk mengonfirmasi penonaktifan 2FA.',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->input('password'), $user->password)) {
            return redirect()->back()
                ->with('error', 'Kata sandi yang Anda masukkan salah. Penonaktifan 2FA dibatalkan.');
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        session()->forget('2fa_setup_secret');

        return redirect()->route('security.two-factor')
            ->with('warning', 'Verifikasi 2 Langkah Google Authenticator telah dinonaktifkan.');
    }
}
