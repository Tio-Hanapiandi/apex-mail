<?php

namespace App\Services;

class Google2FAService
{
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure Base32 secret (16 chars = 80 bits).
     */
    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        $charCount = strlen(self::$base32Chars);
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, $charCount - 1)];
        }
        return $secret;
    }

    /**
     * Calculate 6-digit TOTP code for a secret at a given timestamp according to RFC 6238.
     */
    public function calculateCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = (int) floor($timestamp / 30);
        $timePack = pack('N*', 0) . pack('N*', $timeSlice);
        $secretBinary = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', $timePack, $secretBinary, true);

        // Dynamic truncation (RFC 4226 Section 5.4)
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $binary % 1000000;
        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a 6-digit TOTP code against a secret key with time drift tolerance window.
     * Window = 1 means [-30s, 0s, +30s] (total 90s validity window).
     */
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $currentTime = time();
        $code = trim($code);

        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        for ($i = -$window; $i <= $window; $i++) {
            $testTime = $currentTime + ($i * 30);
            if (hash_equals($this->calculateCode($secret, $testTime), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate the standard Google Authenticator URI (otpauth://).
     */
    public function getOtpAuthUrl(string $companyName, string $email, string $secret): string
    {
        $encodedCompany = rawurlencode($companyName);
        $encodedEmail = rawurlencode($email);
        return "otpauth://totp/{$encodedCompany}:{$encodedEmail}?secret={$secret}&issuer={$encodedCompany}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate QuickChart QR code URL for easy rendering in browser without external dependencies.
     */
    public function getQrCodeUrl(string $otpAuthUrl, int $size = 200): string
    {
        return 'https://quickchart.io/qr?text=' . rawurlencode($otpAuthUrl) . '&size=' . $size . '&margin=2';
    }

    /**
     * Format secret into 4-character chunks for human-readable display.
     * E.g. "ABCD EFGH IJKL MNOP"
     */
    public function formatSecret(string $secret): string
    {
        return implode(' ', str_split($secret, 4));
    }

    /**
     * Decode a Base32 string to binary string.
     */
    private function base32Decode(string $base32): string
    {
        $base32 = strtoupper(trim($base32));
        $buffer = 0;
        $bufferBits = 0;
        $binary = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            if ($char === '=') break;

            $pos = strpos(self::$base32Chars, $char);
            if ($pos === false) continue;

            $buffer = ($buffer << 5) | $pos;
            $bufferBits += 5;

            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $binary .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }

        return $binary;
    }
}
