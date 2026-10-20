<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_account_id',
        'ip_address',
        'user_agent',
        'is_new_device',
        'is_new_location',
        'alert_sent',
        'login_at',
    ];

    protected $casts = [
        'is_new_device' => 'boolean',
        'is_new_location' => 'boolean',
        'alert_sent' => 'boolean',
        'login_at' => 'datetime',
    ];

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }
}
