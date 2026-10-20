<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_account_id',
        'mail_folder_id',
        'message_id',
        'in_reply_to',
        'thread_id',
        'from_name',
        'from_email',
        'to',
        'cc',
        'bcc',
        'reply_to',
        'subject',
        'snippet',
        'body_text',
        'body_html',
        'is_read',
        'is_starred',
        'is_draft',
        'has_attachments',
        'delivery_status',
        'delivery_error',
        'queued_at',
        'size_bytes',
        'sent_at',
        'received_at',
    ];

    protected $casts = [
        'to' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'is_draft' => 'boolean',
        'has_attachments' => 'boolean',
        'size_bytes' => 'integer',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    /**
     * Get human-readable delivery status label.
     */
    public function getDeliveryStatusLabelAttribute(): string
    {
        return match ($this->delivery_status) {
            'deferred' => 'Tertahan (Deferred)',
            'held' => 'Ditahan (On Hold)',
            'bounced' => 'Mental (Bounced)',
            'failed' => 'Gagal Terkirim',
            'queued' => 'Dalam Antrean',
            default => 'Terkirim (Delivered)',
        };
    }

    /**
     * Get badge CSS classes for delivery status.
     */
    public function getDeliveryStatusBadgeClassAttribute(): string
    {
        return match ($this->delivery_status) {
            'deferred' => 'bg-amber-50 text-amber-700 border-amber-200',
            'held' => 'bg-blue-50 text-blue-700 border-blue-200',
            'bounced', 'failed' => 'bg-rose-50 text-rose-700 border-rose-200',
            'queued' => 'bg-purple-50 text-purple-700 border-purple-200',
            default => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        };
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MailFolder::class, 'mail_folder_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    /**
     * Get rendered HTML body with cid: URLs resolved to inline attachment endpoints.
     */
    public function getRenderedBodyHtmlAttribute(): string
    {
        $html = $this->body_html ?? '';
        if (empty($html)) {
            return '';
        }

        $attachments = $this->relationLoaded('attachments') 
            ? $this->attachments 
            : $this->attachments()->get();

        if ($attachments->isEmpty()) {
            return $html;
        }

        $matchedAttachmentIds = [];

        // Replace src="cid:xxx" and url('cid:xxx')
        $html = preg_replace_callback('/(src=["\']|url\(["\']?)cid:([^"\'\)\s>]+)(["\']?\)?)/i', function ($matches) use ($attachments, &$matchedAttachmentIds) {
            $prefix = $matches[1];
            $cid = trim($matches[2], '<> ');
            $suffix = $matches[3];

            // 1. Direct match on content_id
            $attachment = $attachments->first(function ($att) use ($cid) {
                return $att->content_id && (strtolower($att->content_id) === strtolower($cid));
            });

            // 2. Match by filename or attachment name
            if (!$attachment) {
                $attachment = $attachments->first(function ($att) use ($cid) {
                    return $att->filename && (
                        strtolower($att->filename) === strtolower($cid) ||
                        str_contains(strtolower($att->filename), strtolower($cid))
                    );
                });
            }

            // 3. Fallback: match first available image attachment not yet matched
            if (!$attachment) {
                $attachment = $attachments->first(function ($att) use ($matchedAttachmentIds) {
                    return str_starts_with($att->content_type, 'image/') && !in_array($att->id, $matchedAttachmentIds);
                });
            }

            if ($attachment) {
                $matchedAttachmentIds[] = $attachment->id;
                $url = route('webmail.attachment.inline', ['id' => $attachment->id]);
                return $prefix . $url . $suffix;
            }

            return $matches[0];
        }, $html);

        return $html;
    }

    /**
     * Extract clean email address list from mixed structure (strings, arrays, name <email>).
     */
    public static function extractEmailAddresses($raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $emails = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_array($item)) {
                    if (!empty($item['email'])) {
                        $emails[] = strtolower(trim($item['email']));
                    }
                } elseif (is_string($item)) {
                    preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $item, $matches);
                    if (!empty($matches[0])) {
                        foreach ($matches[0] as $em) {
                            $emails[] = strtolower(trim($em));
                        }
                    }
                }
            }
        } elseif (is_string($raw)) {
            preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $raw, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $em) {
                    $emails[] = strtolower(trim($em));
                }
            }
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Get structured recipients for single Reply action.
     * If user is viewing an email they sent (e.g. in Sent folder),
     * reply goes to the original recipient(s), not back to themselves.
     */
    public function getReplySingleRecipients(?string $currentUserEmail = null): array
    {
        $currentUserEmail = strtolower(trim($currentUserEmail ?? ''));
        $fromEmail = strtolower(trim($this->reply_to ?: $this->from_email ?: ''));

        $originalTo = self::extractEmailAddresses($this->to);

        $isSelfSender = ($currentUserEmail !== '' && $fromEmail === $currentUserEmail);

        if ($isSelfSender) {
            $replyTo = !empty($originalTo) ? $originalTo : [$fromEmail];
        } else {
            $replyTo = $fromEmail !== '' ? [$fromEmail] : $originalTo;
        }

        $uniqueTo = array_values(array_unique(array_filter($replyTo)));

        return [
            'to' => $uniqueTo,
            'to_string' => implode(', ', $uniqueTo),
            'first_email' => $uniqueTo[0] ?? $fromEmail,
            'is_self_sender' => $isSelfSender,
        ];
    }

    /**
     * Get structured recipients for Reply All action.
     * Excludes the current user's email and deduplicates.
     */
    public function getReplyAllRecipients(?string $currentUserEmail = null): array
    {
        $currentUserEmail = strtolower(trim($currentUserEmail ?? ''));
        $fromEmail = strtolower(trim($this->reply_to ?: $this->from_email ?: ''));

        $originalTo = self::extractEmailAddresses($this->to);
        $originalCc = self::extractEmailAddresses($this->cc);

        $replyTo = [];
        $replyCc = [];

        // If current user is the original sender (e.g., viewing sent folder)
        $isSelfSender = ($currentUserEmail !== '' && $fromEmail === $currentUserEmail);

        if ($isSelfSender) {
            $replyTo = $originalTo;
            $replyCc = array_values(array_filter($originalCc, fn($e) => $e !== $currentUserEmail));
        } else {
            if ($fromEmail !== '') {
                $replyTo[] = $fromEmail;
            }

            // CC includes all original To and CC, excluding sender and current user
            $candidates = array_merge($originalTo, $originalCc);
            foreach ($candidates as $cand) {
                if ($cand !== '' && $cand !== $currentUserEmail && !in_array($cand, $replyTo) && !in_array($cand, $replyCc)) {
                    $replyCc[] = $cand;
                }
            }
        }

        $uniqueTo = array_values(array_unique($replyTo));
        $uniqueCc = array_values(array_unique($replyCc));

        return [
            'to' => $uniqueTo,
            'cc' => $uniqueCc,
            'to_string' => implode(', ', $uniqueTo),
            'cc_string' => implode(', ', $uniqueCc),
            'total_count' => count($uniqueTo) + count($uniqueCc),
            'has_multiple' => (count($uniqueTo) + count($uniqueCc)) > 1,
        ];
    }
}

