<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_account_id',
        'title',
        'description',
        'location',
        'meeting_link',
        'start_time',
        'end_time',
        'is_all_day',
        'color',
        'attendees',
    ];

    protected $casts = [
        'is_all_day' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'attendees' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }
}
