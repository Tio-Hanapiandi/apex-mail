<?php

namespace App\Services;

use App\Models\MailAccount;
use App\Models\PasswordHistory;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Hash;

class PasswordPolicyService
{
    /**
     * Get active password policy rules.
     */
    public function getRules(): array
    {
        return [
            'min_length' => (int) SystemSetting::get('password_min_length', 8),
            'require_uppercase' => (bool) (int) SystemSetting::get('password_require_uppercase', 1),
            'require_symbol' => (bool) (int) SystemSetting::get('password_require_symbol', 1),
            'prevent_reuse' => (bool) (int) SystemSetting::get('password_prevent_reuse', 1),
            'history_limit' => (int) SystemSetting::get('password_history_limit', 5),
        ];
    }

    /**
     * Update password policy rules in system_settings.
     */
    public function updateRules(array $data): void
    {
        if (isset($data['min_length'])) {
            SystemSetting::set('password_min_length', max(6, (int) $data['min_length']), 'Panjang minimal karakter untuk kata sandi');
        }

        if (array_key_exists('require_uppercase', $data)) {
            SystemSetting::set('password_require_uppercase', $data['require_uppercase'] ? '1' : '0', 'Wajib menyertakan huruf kapital / besar (A-Z)');
        }

        if (array_key_exists('require_symbol', $data)) {
            SystemSetting::set('password_require_symbol', $data['require_symbol'] ? '1' : '0', 'Wajib menyertakan simbol unik / karakter khusus');
        }

        if (array_key_exists('prevent_reuse', $data)) {
            SystemSetting::set('password_prevent_reuse', $data['prevent_reuse'] ? '1' : '0', 'Mencegah penggunaan kata sandi yang sama dengan sebelumnya');
        }

        if (isset($data['history_limit'])) {
            SystemSetting::set('password_history_limit', max(1, (int) $data['history_limit']), 'Jumlah riwayat kata sandi yang dicegah');
        }
    }

    /**
     * Check if a candidate password satisfies the password policy.
     *
     * @param string $newPassword
     * @param MailAccount|null $account
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validatePassword(string $newPassword, ?MailAccount $account = null): array
    {
        $rules = $this->getRules();
        $errors = [];

        // Check minimum length
        if (mb_strlen($newPassword) < $rules['min_length']) {
            $errors[] = "Kata sandi minimal harus {$rules['min_length']} karakter.";
        }

        // Check uppercase requirement
        if ($rules['require_uppercase'] && !preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = 'Kata sandi wajib mengandung setidaknya 1 huruf besar / kapital (A-Z).';
        }

        // Check special symbol requirement
        if ($rules['require_symbol'] && !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?~`]/', $newPassword)) {
            $errors[] = 'Kata sandi wajib mengandung setidaknya 1 simbol unik atau karakter khusus.';
        }

        // Check against current and previous passwords
        if ($account && !empty($account->password)) {
            if (Hash::check($newPassword, $account->password)) {
                $errors[] = 'Kata sandi baru tidak boleh sama dengan kata sandi sebelumnya. Silakan gunakan kata sandi yang berbeda.';
            } elseif ($rules['prevent_reuse']) {
                $pastHistories = PasswordHistory::where('mail_account_id', $account->id)
                    ->latest()
                    ->take($rules['history_limit'])
                    ->get();

                foreach ($pastHistories as $past) {
                    if (Hash::check($newPassword, $past->password_hash)) {
                        $errors[] = 'Kata sandi baru tidak boleh sama dengan salah satu dari riwayat kata sandi sebelumnya.';
                        break;
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get Laravel validation rule array for requests.
     */
    public function getLaravelValidationRules(?MailAccount $account = null, bool $required = true): array
    {
        $policy = $this->getRules();
        $rules = [];

        $rules[] = $required ? 'required' : 'nullable';
        $rules[] = 'string';
        $rules[] = 'min:' . $policy['min_length'];

        if ($policy['require_uppercase']) {
            $rules[] = 'regex:/[A-Z]/';
        }

        if ($policy['require_symbol']) {
            $rules[] = 'regex:/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?~`]/';
        }

        $rules[] = function ($attribute, $value, $fail) use ($account, $policy) {
            if (empty($value) || !$account || empty($account->password)) {
                return;
            }

            if (Hash::check($value, $account->password)) {
                $fail('Kata sandi baru tidak boleh sama dengan kata sandi sebelumnya.');
                return;
            }

            if ($policy['prevent_reuse']) {
                $histories = PasswordHistory::where('mail_account_id', $account->id)
                    ->latest()
                    ->take($policy['history_limit'])
                    ->get();

                foreach ($histories as $history) {
                    if (Hash::check($value, $history->password_hash)) {
                        $fail('Kata sandi baru tidak boleh sama dengan salah satu dari riwayat kata sandi sebelumnya.');
                        return;
                    }
                }
            }
        };

        return $rules;
    }

    /**
     * Record a password hash in the history table.
     */
    public function recordPasswordHistory(MailAccount $account, string $passwordHash): void
    {
        PasswordHistory::create([
            'mail_account_id' => $account->id,
            'password_hash' => $passwordHash,
        ]);

        // Prune older history beyond limit
        $limit = (int) SystemSetting::get('password_history_limit', 5);
        $keepIds = PasswordHistory::where('mail_account_id', $account->id)
            ->latest()
            ->take($limit)
            ->pluck('id');

        PasswordHistory::where('mail_account_id', $account->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
