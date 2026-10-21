<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedSender extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_account_id',
        'sender_email',
        'sender_domain',
        'reason',
        'is_active',
        'unblocked_at',
        'unblocked_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'unblocked_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    /**
     * Check if a sender address is blocked for a specific mailbox account (or system-wide).
     */
    public static function isBlocked(?int $accountId, string $senderEmail): bool
    {
        $normalizedEmail = strtolower(trim($senderEmail));
        if (empty($normalizedEmail)) {
            return false;
        }

        return static::where('is_active', true)
            ->where(function ($q) use ($accountId, $normalizedEmail) {
                if ($accountId !== null) {
                    $q->where(function ($subQ) use ($accountId, $normalizedEmail) {
                        $subQ->where('mail_account_id', $accountId)
                             ->where('sender_email', $normalizedEmail);
                    })->orWhere(function ($globalQ) use ($normalizedEmail) {
                        $globalQ->whereNull('mail_account_id')
                                ->where('sender_email', $normalizedEmail);
                    });
                } else {
                    $q->where('sender_email', $normalizedEmail);
                }
            })
            ->exists();
    }

    /**
     * Block a sender for an account.
     */
    public static function blockSender(?int $accountId, string $senderEmail, string $reason = 'Ditandai sebagai spam oleh pengguna'): self
    {
        $normalizedEmail = strtolower(trim($senderEmail));
        $domain = substr(strrchr($normalizedEmail, '@'), 1) ?: '';

        return static::updateOrCreate([
            'mail_account_id' => $accountId,
            'sender_email' => $normalizedEmail,
        ], [
            'sender_domain' => $domain,
            'reason' => $reason,
            'is_active' => true,
            'unblocked_at' => null,
            'unblocked_by' => null,
        ]);
    }

    /**
     * Unblock a sender for an account.
     */
    public static function unblockSender(?int $accountId, string $senderEmail, string $unblockedBy = 'admin'): bool
    {
        $normalizedEmail = strtolower(trim($senderEmail));

        return static::where('mail_account_id', $accountId)
            ->where('sender_email', $normalizedEmail)
            ->delete() > 0;
    }
}
