<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmtpRelay extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'name',
        'provider',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'sender_email',
        'sender_name',
        'is_active',
        'is_default',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'port' => 'integer',
        'last_tested_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the active relay (either domain-specific or global).
     */
    public static function getActiveRelay(?int $domainId = null): ?self
    {
        if ($domainId) {
            $domainRelay = self::where('domain_id', $domainId)
                ->where('is_active', true)
                ->first();
            if ($domainRelay) {
                return $domainRelay;
            }
        }

        return self::whereNull('domain_id')
            ->where('is_active', true)
            ->where('is_default', true)
            ->first() ?? self::whereNull('domain_id')->where('is_active', true)->first();
    }
}
