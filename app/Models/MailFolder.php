<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_account_id',
        'name',
        'slug',
        'icon',
        'color',
        'parent_id',
        'is_system',
        'unread_count',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'unread_count' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MailFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MailFolder::class, 'parent_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class, 'mail_folder_id');
    }
}
