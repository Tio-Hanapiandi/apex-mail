<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceVerificationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_account_id',
        'token',
        'ip_address',
        'user_agent',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function mailAccount()
    {
        return $this->belongsTo(MailAccount::class);
    }
}
