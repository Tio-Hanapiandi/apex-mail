<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailAccount extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'domain_id',
        'username',
        'email',
        'recovery_email',
        'display_name',
        'password',
        'quota_bytes',
        'used_bytes',
        'is_admin',
        'is_active',
        'signature',
        'auto_reply_active',
        'auto_reply_subject',
        'auto_reply_body',
        'forward_active',
        'forward_destination',
        'forward_keep_copy',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'auto_reply_active' => 'boolean',
        'forward_active' => 'boolean',
        'forward_keep_copy' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'quota_bytes' => 'integer',
        'used_bytes' => 'integer',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(MailFolder::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function knownDevices(): HasMany
    {
        return $this->hasMany(AccountKnownDevice::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function blockedSenders(): HasMany
    {
        return $this->hasMany(BlockedSender::class);
    }

    /**
     * Create default system folders for a newly registered mailbox.
     */
    public function createDefaultFolders(): void
    {
        $defaultFolders = [
            ['name' => 'Kotak Masuk', 'slug' => 'inbox', 'icon' => 'inbox', 'color' => '#2563EB', 'is_system' => true],
            ['name' => 'Terkirim', 'slug' => 'sent', 'icon' => 'send', 'color' => '#10B981', 'is_system' => true],
            ['name' => 'Draf', 'slug' => 'drafts', 'icon' => 'file-text', 'color' => '#F59E0B', 'is_system' => true],
            ['name' => 'Arsip', 'slug' => 'archive', 'icon' => 'archive', 'color' => '#6B7280', 'is_system' => true],
            ['name' => 'Spam', 'slug' => 'spam', 'icon' => 'alert-octagon', 'color' => '#EF4444', 'is_system' => true],
            ['name' => 'Sampah', 'slug' => 'trash', 'icon' => 'trash-2', 'color' => '#9CA3AF', 'is_system' => true],
        ];

        foreach ($defaultFolders as $folder) {
            $this->folders()->firstOrCreate(['slug' => $folder['slug']], $folder);
        }
    }
}
