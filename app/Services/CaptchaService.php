<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\Response;

class CaptchaService
{
    /**
     * Check if login CAPTCHA is enabled in system settings.
     */
    public function isEnabled(): bool
    {
        return (bool) (int) SystemSetting::get('login_captcha_enabled', 1);
    }

    /**
     * Generate random unambiguous alphanumeric code.
     */
    public function generateCode(int $length = 5): string
    {
        // Exclude ambiguous characters: 0, O, 1, I, l
        $pool = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        $max = strlen($pool) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= $pool[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Render and return CAPTCHA image HTTP response, storing answer in session.
     */
    public function generateImageResponse(): Response
    {
        $code = $this->generateCode(5);

        // Store lowercase code and 10-minute expiry in session
        session([
            'login_captcha' => strtolower($code),
            'login_captcha_expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        if (extension_loaded('gd')) {
            $imageData = $this->createGdImage($code);
            $mimeType = 'image/png';
        } else {
            $imageData = $this->createSvgImage($code);
            $mimeType = 'image/svg+xml';
        }

        return response($imageData, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Create PNG image binary using GD.
     */
    protected function createGdImage(string $code): string
    {
        $width = 150;
        $height = 42;
        $image = imagecreatetruecolor($width, $height);

        // Clean light background (#f8fafc)
        $bg = imagecolorallocate($image, 248, 250, 252);
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);

        // Random subtle interference lines
        $lineColors = [
            imagecolorallocate($image, 203, 213, 225), // slate-300
            imagecolorallocate($image, 191, 219, 254), // blue-200
            imagecolorallocate($image, 221, 214, 254), // purple-200
        ];

        for ($i = 0; $i < 4; $i++) {
            $c = $lineColors[array_rand($lineColors)];
            imageline(
                $image,
                random_int(0, 20),
                random_int(5, $height - 5),
                random_int($width - 30, $width),
                random_int(5, $height - 5),
                $c
            );
        }

        // Random subtle noise dots
        $dotColor = imagecolorallocate($image, 226, 232, 240);
        for ($i = 0; $i < 50; $i++) {
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $dotColor);
        }

        // Available fonts check
        $fontFile = null;
        $possibleFonts = [
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/segoeui.ttf',
            'C:/Windows/Fonts/calibri.ttf',
            'C:/Windows/Fonts/tahoma.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
        ];

        foreach ($possibleFonts as $f) {
            if (file_exists($f)) {
                $fontFile = $f;
                break;
            }
        }

        // Palette of high contrast, legible colors for letters
        $textColors = [
            imagecolorallocate($image, 30, 41, 59),   // slate-800
            imagecolorallocate($image, 29, 78, 216),  // blue-700
            imagecolorallocate($image, 67, 56, 202),  // indigo-700
            imagecolorallocate($image, 15, 118, 110), // teal-700
            imagecolorallocate($image, 180, 83, 9),   // amber-700
        ];

        $charCount = strlen($code);
        $spacing = ($width - 24) / $charCount;

        for ($i = 0; $i < $charCount; $i++) {
            $char = $code[$i];
            $color = $textColors[array_rand($textColors)];

            if ($fontFile && function_exists('imagettftext')) {
                $angle = random_int(-15, 15);
                $fontSize = random_int(16, 19);
                $x = (int) (12 + ($i * $spacing));
                $y = random_int(28, 33);
                imagettftext($image, $fontSize, $angle, $x, $y, $color, $fontFile, $char);
            } else {
                $x = (int) (14 + ($i * $spacing));
                $y = random_int(10, 16);
                imagestring($image, 5, $x, $y, $char, $color);
            }
        }

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    /**
     * SVG fallback image generator if GD is not present.
     */
    protected function createSvgImage(string $code): string
    {
        $chars = '';
        $spacing = 130 / strlen($code);

        for ($i = 0; $i < strlen($code); $i++) {
            $x = 15 + ($i * $spacing);
            $y = 28 + random_int(-3, 3);
            $rot = random_int(-12, 12);
            $colors = ['#1e293b', '#1d4ed8', '#4338ca', '#0f766e', '#b45309'];
            $color = $colors[array_rand($colors)];
            $chars .= "<text x=\"{$x}\" y=\"{$y}\" font-family=\"monospace\" font-size=\"20\" font-weight=\"bold\" fill=\"{$color}\" transform=\"rotate({$rot}, {$x}, {$y})\">{$code[$i]}</text>";
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="150" height="42" viewBox="0 0 150 42">
    <rect width="100%" height="100%" fill="#f8fafc" rx="8" />
    <path d="M 10 20 Q 50 5 90 25 T 140 18" stroke="#cbd5e1" stroke-width="1.5" fill="none" />
    <path d="M 5 28 Q 60 38 100 12 T 145 28" stroke="#bfdbfe" stroke-width="1.5" fill="none" />
    {$chars}
</svg>
SVG;
    }

    /**
     * Validate the submitted CAPTCHA answer against the session.
     */
    public function validate(?string $input): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if (empty($input)) {
            return false;
        }

        $sessionCaptcha = session('login_captcha');
        $expiresAt = session('login_captcha_expires_at');

        if (!$sessionCaptcha || ($expiresAt && time() > $expiresAt)) {
            session()->forget(['login_captcha', 'login_captcha_expires_at']);
            return false;
        }

        $isValid = (strtolower(trim($input)) === $sessionCaptcha);

        if ($isValid) {
            // Invalidate to prevent replay attacks
            session()->forget(['login_captcha', 'login_captcha_expires_at']);
        }

        return $isValid;
    }
}
