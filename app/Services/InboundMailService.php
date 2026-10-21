<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\MailAccount;
use App\Models\MailAlias;
use App\Models\MailFolder;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InboundMailService
{
    protected MailRelayService $relayService;

    public function __construct(MailRelayService $relayService)
    {
        $this->relayService = $relayService;
    }

    /**
     * Process incoming webhook request from Brevo Inbound Parsing or generic webhook.
     *
     * @param Request $request
     * @return array
     */
    public function processInbound(Request $request): array
    {
        $payload = $request->all();
        Log::info('InboundMailService: Received incoming webhook payload.', ['keys' => array_keys($payload)]);

        // 1. Identify email items in payload (Brevo sends {"items": [...]})
        $items = [];
        if (isset($payload['items']) && is_array($payload['items'])) {
            $items = $payload['items'];
        } elseif (isset($payload['item']) && is_array($payload['item'])) {
            $items = [$payload['item']];
        } else {
            // Flat payload (single email)
            $items = [$payload];
        }

        $deliveredEmails = [];
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                $result = $this->storeInboundEmail($item, $request);
                if (!empty($result['emails'])) {
                    $deliveredEmails = array_merge($deliveredEmails, $result['emails']);
                }
            } catch (Exception $e) {
                Log::error("InboundMailService: Failed processing item #{$index}: " . $e->getMessage());
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => count($deliveredEmails) > 0 || empty($errors),
            'delivered_count' => count($deliveredEmails),
            'delivered_emails' => $deliveredEmails,
            'errors' => $errors,
        ];
    }

    /**
     * Process and store a single inbound email item.
     *
     * @param array $item
     * @param Request $request
     * @return array
     */
    public function storeInboundEmail(array $item, Request $request): array
    {
        // 0. If raw_mime is provided (e.g. from Cloudflare Email Worker), parse raw MIME
        if (!empty($item['raw_mime'])) {
            $parsedMime = $this->parseRawMime($item['raw_mime']);

            $isBounceAddress = false;
            $rawFromStr = is_string($item['from'] ?? null) ? $item['from'] : ($item['from']['address'] ?? $item['from']['email'] ?? '');
            if (!empty($rawFromStr)) {
                $isBounceAddress = str_starts_with(strtolower($rawFromStr), 'bounces-') 
                    || str_contains(strtolower($rawFromStr), 'sender-sib.com') 
                    || str_contains(strtolower($rawFromStr), 'bounce');
            }

            if (!empty($parsedMime['from_email']) && ((empty($item['from']) && empty($item['from_email']) && empty($item['From'])) || $isBounceAddress)) {
                $item['from_email'] = $parsedMime['from_email'];
                $item['from_name'] = $parsedMime['from_name'];
                $item['From'] = [
                    'Address' => $parsedMime['from_email'],
                    'Name' => $parsedMime['from_name'],
                ];
                unset($item['from']);
            }
            if ((empty($item['to']) && empty($item['To'])) && !empty($parsedMime['to'])) {
                $item['To'] = $parsedMime['to'];
            }
            if ((empty($item['cc']) && empty($item['Cc'])) && !empty($parsedMime['cc'])) {
                $item['Cc'] = $parsedMime['cc'];
            }
            if ((empty($item['subject']) && empty($item['Subject'])) || in_array($item['subject'] ?? $item['Subject'] ?? '', ['(Tanpa Subjek)', ''])) {
                $item['subject'] = $parsedMime['subject'] ?: '(Tanpa Subjek)';
            }
            if (empty($item['body_html']) && empty($item['RawHtmlBody']) && !empty($parsedMime['body_html'])) {
                $item['body_html'] = $parsedMime['body_html'];
            }
            if (empty($item['body_text']) && empty($item['RawTextBody']) && !empty($parsedMime['body_text'])) {
                $item['body_text'] = $parsedMime['body_text'];
            }
            if (empty($item['message_id']) && empty($item['MessageId']) && !empty($parsedMime['message_id'])) {
                $item['message_id'] = $parsedMime['message_id'];
            }
            if (!empty($parsedMime['attachments'])) {
                $item['Attachments'] = array_merge($item['Attachments'] ?? [], $parsedMime['attachments']);
            }
        }

        // 1. Extract Sender info
        $fromEmail = '';
        $fromName = '';

        if (isset($item['From']) && is_array($item['From'])) {
            $fromEmail = $item['From']['Address'] ?? $item['From']['email'] ?? '';
            $fromName = $item['From']['Name'] ?? $item['From']['name'] ?? '';
        } elseif (isset($item['from_email'])) {
            $fromEmail = $item['from_email'];
            $fromName = $item['from_name'] ?? '';
        } elseif (isset($item['from'])) {
            if (is_array($item['from'])) {
                $fromEmail = $item['from']['address'] ?? $item['from']['email'] ?? '';
                $fromName = $item['from']['name'] ?? '';
            } else {
                $parsed = $this->parseAddressString($item['from']);
                $fromEmail = $parsed['email'];
                $fromName = $parsed['name'];
            }
        }

        $fromEmail = strtolower(trim($fromEmail));
        if (empty($fromEmail)) {
            throw new Exception('Alamat pengirim (from) tidak ditemukan dalam payload inbound.');
        }

        // 2. Extract Recipients (To, Cc, Bcc)
        $rawTo = $this->extractRecipientList($item['To'] ?? $item['to'] ?? []);
        $rawCc = $this->extractRecipientList($item['Cc'] ?? $item['cc'] ?? []);
        $rawBcc = $this->extractRecipientList($item['Bcc'] ?? $item['bcc'] ?? []);

        if (empty($rawTo)) {
            throw new Exception('Tidak ada alamat penerima (to) yang ditemukan dalam payload.');
        }

        // 3. Extract Subject & Bodies
        $subject = $item['Subject'] ?? $item['subject'] ?? '(Tanpa Subjek)';
        $bodyHtml = $item['RawHtmlBody'] ?? $item['body_html'] ?? $item['html'] ?? '';
        $bodyText = $item['RawTextBody'] ?? $item['body_text'] ?? $item['text'] ?? '';

        if (empty($bodyText) && !empty($bodyHtml)) {
            $bodyText = strip_tags($bodyHtml);
        }
        if (empty($bodyHtml) && !empty($bodyText)) {
            $bodyHtml = nl2br(e($bodyText));
        }

        $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', $bodyText)), 0, 120);

        // 4. Identifiers & Threading
        $messageId = $item['MessageId'] ?? $item['Headers']['message-id'] ?? $item['message_id'] ?? null;
        if (!$messageId) {
            $messageId = '<inbound-' . uniqid('', true) . '@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'azkario.biz.id') . '>';
        }

        $inReplyTo = $item['InReplyTo'] ?? $item['Headers']['in-reply-to'] ?? $item['in_reply_to'] ?? null;
        $threadId = $inReplyTo ?: $messageId;

        // Sent and Received timestamps
        $sentAt = null;
        if (!empty($item['SentAtDate'])) {
            try { $sentAt = Carbon::parse($item['SentAtDate']); } catch (\Exception $e) {}
        } elseif (!empty($item['Headers']['date'])) {
            try { $sentAt = Carbon::parse($item['Headers']['date']); } catch (\Exception $e) {}
        }
        $sentAt = $sentAt ?: Carbon::now();
        $receivedAt = Carbon::now();

        // 5. Extract and store attachments
        $savedAttachments = $this->processAttachments($item, $request);

        // 6. Find all target MailAccounts in our system
        $targetAccounts = $this->resolveTargetAccounts($rawTo, $rawCc, $rawBcc);

        if (empty($targetAccounts)) {
            Log::warning("InboundMailService: No local accounts or aliases matched for recipients: " . json_encode($rawTo));
            return [
                'status' => 'skipped',
                'message' => 'Tidak ditemukan akun mailbox lokal yang cocok.',
                'emails' => [],
            ];
        }

        // 7. Deliver email to each resolved local account's Inbox
        $deliveredRecords = [];

        foreach ($targetAccounts as $account) {
            // Find or create 'inbox' folder
            $inboxFolder = $account->folders()->firstOrCreate(
                ['slug' => 'inbox'],
                ['name' => 'Kotak Masuk', 'icon' => 'inbox', 'color' => '#2563EB', 'is_system' => true]
            );

            // Check if sender is marked as spam / blocked for this account (or globally)
            $isSpamBlocked = \App\Models\BlockedSender::isBlocked($account->id, $fromEmail);

            $targetFolder = $isSpamBlocked
                ? $account->folders()->firstOrCreate(
                    ['slug' => 'spam'],
                    ['name' => 'Spam', 'icon' => 'alert-octagon', 'color' => '#EF4444', 'is_system' => true]
                )
                : $inboxFolder;

            // Deduplication check: prevent inserting same message in target folder
            $existing = Email::where('mail_account_id', $account->id)
                ->where('mail_folder_id', $targetFolder->id)
                ->where(function ($q) use ($messageId, $subject, $fromEmail, $snippet) {
                    $q->where('message_id', $messageId);
                    if (!empty($subject) && $subject !== '(Tanpa Subjek)') {
                        $q->orWhere(function ($subQ) use ($subject, $fromEmail, $snippet) {
                            $subQ->where('subject', $subject)
                                ->where('from_email', $fromEmail)
                                ->where('created_at', '>=', Carbon::now()->subMinutes(3));
                            if (!empty($snippet)) {
                                $subQ->where('snippet', $snippet);
                            }
                        });
                    }
                })
                ->first();

            if ($existing) {
                Log::info("InboundMailService: Duplicate email skipped for {$account->email} (Subject: {$subject}, Message-ID: {$messageId}).");
                continue;
            }

            if ($isSpamBlocked) {
                Log::info("InboundMailService: Email from {$fromEmail} routed directly to Spam folder for {$account->email} because sender is blocked.");
            }

            $emailRecord = $targetFolder->emails()->create([
                'mail_account_id' => $account->id,
                'message_id' => $messageId,
                'in_reply_to' => $inReplyTo,
                'thread_id' => $threadId,
                'from_name' => $fromName ?: $fromEmail,
                'from_email' => $fromEmail,
                'to' => $rawTo,
                'cc' => !empty($rawCc) ? $rawCc : null,
                'bcc' => !empty($rawBcc) ? $rawBcc : null,
                'subject' => $subject,
                'snippet' => $snippet,
                'body_text' => $bodyText,
                'body_html' => $bodyHtml,
                'is_read' => false,
                'is_starred' => false,
                'is_draft' => false,
                'has_attachments' => !empty($savedAttachments),
                'size_bytes' => strlen($bodyHtml) + array_sum(array_column($savedAttachments, 'size_bytes')),
                'sent_at' => $sentAt,
                'received_at' => $receivedAt,
            ]);

            // Save attachment records linked to this email
            foreach ($savedAttachments as $att) {
                $emailRecord->attachments()->create([
                    'filename' => $att['filename'],
                    'content_type' => $att['content_type'],
                    'size_bytes' => $att['size_bytes'],
                    'storage_path' => $att['storage_path'],
                    'is_inline' => $att['is_inline'] ?? false,
                    'content_id' => $att['content_id'] ?? null,
                ]);
            }

            // Increment unread count for target folder
            $targetFolder->increment('unread_count');

            // Update account used storage
            $account->increment('used_bytes', $emailRecord->size_bytes);

            // Automatic Email Forwarding (hanya jika bukan spam)
            if (!$isSpamBlocked && $account->forward_active && !empty($account->forward_destination)) {
                $this->dispatchAutoForward($account, $emailRecord, $savedAttachments);

                if (!$account->forward_keep_copy) {
                    $targetFolder->decrement('unread_count');
                    $account->decrement('used_bytes', $emailRecord->size_bytes);
                    $emailRecord->delete();
                }
            }

            $deliveredRecords[] = [
                'email_id' => $emailRecord->id,
                'account_email' => $account->email,
                'subject' => $emailRecord->subject,
                'received_at' => $receivedAt->toDateTimeString(),
            ];
        }

        return [
            'status' => 'delivered',
            'emails' => $deliveredRecords,
        ];
    }

    /**
     * Resolve target MailAccounts from recipient lists (direct match or alias expansion).
     *
     * @param array $to
     * @param array $cc
     * @param array $bcc
     * @return array<MailAccount>
     */
    protected function resolveTargetAccounts(array $to, array $cc = [], array $bcc = []): array
    {
        $allRecipients = array_merge($to, $cc, $bcc);
        $resolvedAccounts = [];
        $resolvedIds = [];

        foreach ($allRecipients as $recipient) {
            $email = strtolower(trim($recipient['email'] ?? ''));
            if (!$email) {
                continue;
            }

            // 1. Direct match with MailAccount
            $account = MailAccount::where('email', $email)->where('is_active', true)->first();
            if ($account && !isset($resolvedIds[$account->id])) {
                $resolvedAccounts[] = $account;
                $resolvedIds[$account->id] = true;
                continue;
            }

            // 2. Check MailAlias (Distribution list / forwarder)
            $alias = MailAlias::where('source_email', $email)->where('is_active', true)->first();
            if ($alias) {
                $destinations = array_map('trim', explode(',', $alias->destination_emails));
                foreach ($destinations as $destEmail) {
                    $destAccount = MailAccount::where('email', strtolower($destEmail))->where('is_active', true)->first();
                    if ($destAccount && !isset($resolvedIds[$destAccount->id])) {
                        $resolvedAccounts[] = $destAccount;
                        $resolvedIds[$destAccount->id] = true;
                    }
                }
                continue;
            }

            // 3. Fallback check: if recipient domain belongs to one of our active domains, check for domain admin
            $parts = explode('@', $email);
            if (count($parts) === 2) {
                $domainName = $parts[1];
                $domain = Domain::where('name', $domainName)->where('is_active', true)->first();
                if ($domain) {
                    // Route to first admin account of this domain as catch-all fallback
                    $adminAccount = MailAccount::where('domain_id', $domain->id)
                        ->where('is_admin', true)
                        ->where('is_active', true)
                        ->first();

                    if ($adminAccount && !isset($resolvedIds[$adminAccount->id])) {
                        Log::info("InboundMailService: Catch-all fallback applied. Delivered {$email} to admin {$adminAccount->email}.");
                        $resolvedAccounts[] = $adminAccount;
                        $resolvedIds[$adminAccount->id] = true;
                    }
                }
            }
        }

        return $resolvedAccounts;
    }

    /**
     * Process attachments from Brevo JSON payload (Base64) or Multipart uploads.
     *
     * @param array $item
     * @param Request $request
     * @return array
     */
    protected function processAttachments(array $item, Request $request): array
    {
        $saved = [];
        $subDir = 'mail_attachments/inbound/' . date('Y/m/d');
        Storage::disk('local')->makeDirectory($subDir);

        // A. Check Base64 Attachments in JSON (Brevo standard)
        $rawAttachments = $item['Attachments'] ?? $item['attachments'] ?? [];
        if (is_array($rawAttachments)) {
            foreach ($rawAttachments as $att) {
                $filename = $att['Name'] ?? $att['name'] ?? ('attachment_' . uniqid() . '.bin');
                $contentType = $att['ContentType'] ?? $att['content_type'] ?? 'application/octet-stream';
                $base64Content = $att['Content'] ?? $att['content'] ?? null;

                $rawCid = $att['ContentId'] ?? $att['content_id'] ?? $att['content-id'] ?? null;
                $contentId = $rawCid ? trim($rawCid, '<> ') : null;

                if ($base64Content) {
                    $decoded = base64_decode($base64Content);
                    $storageFilename = Str::uuid()->toString() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
                    $storagePath = $subDir . '/' . $storageFilename;

                    Storage::disk('local')->put($storagePath, $decoded);

                    $saved[] = [
                        'filename' => $filename,
                        'content_type' => $contentType,
                        'size_bytes' => strlen($decoded),
                        'storage_path' => storage_path('app/' . $storagePath),
                        'is_inline' => !empty($contentId) || !empty($att['is_inline']),
                        'content_id' => $contentId,
                    ];
                }
            }
        }

        // B. Check Multipart files uploaded with request
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file instanceof UploadedFile) {
                    $path = $file->store($subDir, 'local');
                    $saved[] = [
                        'filename' => $file->getClientOriginalName(),
                        'content_type' => $file->getClientMimeType(),
                        'size_bytes' => $file->getSize(),
                        'storage_path' => storage_path('app/' . $path),
                        'is_inline' => false,
                        'content_id' => null,
                    ];
                }
            }
        }

        return $saved;
    }

    /**
     * Extract recipient array into structured format [{'name': '...', 'email': '...'}, ...].
     *
     * @param mixed $recipients
     * @return array
     */
    protected function extractRecipientList($recipients): array
    {
        $list = [];

        if (is_string($recipients)) {
            $emails = explode(',', $recipients);
            foreach ($emails as $em) {
                $parsed = $this->parseAddressString($em);
                if (filter_var($parsed['email'], FILTER_VALIDATE_EMAIL)) {
                    $list[] = $parsed;
                }
            }
            return $list;
        }

        if (is_array($recipients)) {
            foreach ($recipients as $rec) {
                if (is_string($rec)) {
                    $parsed = $this->parseAddressString($rec);
                    if (filter_var($parsed['email'], FILTER_VALIDATE_EMAIL)) {
                        $list[] = $parsed;
                    }
                } elseif (is_array($rec)) {
                    $email = $rec['Address'] ?? $rec['email'] ?? $rec['address'] ?? '';
                    $name = $rec['Name'] ?? $rec['name'] ?? '';
                    $email = strtolower(trim($email));
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $list[] = ['name' => $name, 'email' => $email];
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Parse string like "Budi Pratama <budi@azkario.biz.id>" into name and email.
     *
     * @param string $str
     * @return array
     */
    protected function parseAddressString(string $str): array
    {
        $str = trim($str);
        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $str, $matches)) {
            return [
                'name' => trim($matches[1], " '\""),
                'email' => strtolower(trim($matches[2])),
            ];
        }

        return [
            'name' => '',
            'email' => strtolower(trim($str, " '\"")),
        ];
    }

    /**
     * Parse raw RFC 822/2822/5322 MIME message string into structured email fields.
     */
    public function parseRawMime(string $rawMime): array
    {
        $parts = preg_split("/\r?\n\r?\n/", $rawMime, 2);
        $headerText = $parts[0] ?? '';
        $bodyText = $parts[1] ?? '';

        $headers = $this->parseHeaders($headerText);

        $result = [
            'from_email' => '',
            'from_name' => '',
            'to' => [],
            'cc' => [],
            'subject' => '',
            'message_id' => '',
            'date' => null,
            'body_text' => '',
            'body_html' => '',
            'attachments' => [],
        ];

        if (!empty($headers['from'])) {
            $parsed = $this->parseAddressString($this->decodeMimeHeader($headers['from']));
            $result['from_email'] = $parsed['email'];
            $result['from_name'] = $parsed['name'];
        }
        if (!empty($headers['to'])) {
            $result['to'] = $this->extractRecipientList($this->decodeMimeHeader($headers['to']));
        }
        if (!empty($headers['cc'])) {
            $result['cc'] = $this->extractRecipientList($this->decodeMimeHeader($headers['cc']));
        }
        if (!empty($headers['subject'])) {
            $result['subject'] = $this->decodeMimeHeader($headers['subject']);
        }
        if (!empty($headers['message-id'])) {
            $result['message_id'] = trim($headers['message-id']);
        }
        if (!empty($headers['date'])) {
            try { $result['date'] = Carbon::parse($headers['date']); } catch (\Exception $e) {}
        }

        $contentType = $headers['content-type'] ?? 'text/plain';
        $transferEncoding = strtolower($headers['content-transfer-encoding'] ?? '7bit');

        $this->parseMimePart($contentType, $transferEncoding, $bodyText, $result);

        return $result;
    }

    /**
     * Parse headers into key-value array.
     */
    protected function parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = preg_split("/\r?\n/", $headerText);
        $currentKey = '';

        foreach ($lines as $line) {
            if (preg_match('/^[ \t]+(.+)$/', $line, $m) && $currentKey) {
                $headers[$currentKey] .= ' ' . trim($m[1]);
            } elseif (preg_match('/^([a-zA-Z0-9_-]+):[ \t]*(.*)$/', $line, $m)) {
                $currentKey = strtolower($m[1]);
                $headers[$currentKey] = trim($m[2]);
            }
        }

        return $headers;
    }

    /**
     * Parse MIME part, recursively handling multiparts.
     */
    protected function parseMimePart(string $contentType, string $transferEncoding, string $body, array &$result): void
    {
        if (preg_match('/multipart\/[a-z]+/i', $contentType)) {
            if (preg_match('/boundary=["\']?([^"\'\r\n;]+)["\']?/i', $contentType, $bm)) {
                $boundary = trim($bm[1]);
                $sections = explode('--' . $boundary, $body);

                foreach ($sections as $section) {
                    $section = trim($section);
                    if (empty($section) || $section === '--') {
                        continue;
                    }

                    $subParts = preg_split("/\r?\n\r?\n/", $section, 2);
                    $subHeaderRaw = $subParts[0] ?? '';
                    $subBodyRaw = $subParts[1] ?? '';

                    $subHeaders = $this->parseHeaders($subHeaderRaw);
                    $subContentType = $subHeaders['content-type'] ?? 'text/plain';
                    $subEncoding = strtolower($subHeaders['content-transfer-encoding'] ?? '7bit');

                    $disposition = $subHeaders['content-disposition'] ?? '';
                    $contentId = isset($subHeaders['content-id']) ? trim($subHeaders['content-id'], '<> ') : null;

                    $isAttachment = (stripos($disposition, 'attachment') !== false) ||
                                    !empty($contentId) ||
                                    (stripos($disposition, 'inline') !== false && !str_starts_with(strtolower($subContentType), 'text/')) ||
                                    (preg_match('/(?:filename|name)=["\']?([^"\'\r\n;]+)["\']?/i', $disposition . ';' . $subContentType));

                    if ($isAttachment) {
                        $filename = 'lampiran';
                        if (preg_match('/filename\*?=["\']?(?:UTF-8\'\')?([^"\'\r\n;]+)["\']?/i', $disposition . ';' . $subContentType, $fn)) {
                            $filename = rawurldecode(trim($fn[1], " '\""));
                        } elseif (preg_match('/name=["\']?([^"\'\r\n;]+)["\']?/i', $subContentType, $nm)) {
                            $filename = rawurldecode(trim($nm[1], " '\""));
                        } elseif (!empty($contentId)) {
                            $ext = 'bin';
                            if (preg_match('/image\/([a-zA-Z0-9]+)/i', $subContentType, $im)) {
                                $ext = strtolower($im[1]) === 'jpeg' ? 'jpg' : strtolower($im[1]);
                            }
                            $filename = $contentId . '.' . $ext;
                        }

                        $filename = $this->decodeMimeHeader($filename);
                        $decodedData = $this->decodeContent($subBodyRaw, $subEncoding);
                        $result['attachments'][] = [
                            'Name' => $filename,
                            'ContentType' => trim(explode(';', $subContentType)[0]),
                            'Content' => base64_encode($decodedData),
                            'ContentId' => $contentId,
                        ];
                    } else {
                        $this->parseMimePart($subContentType, $subEncoding, $subBodyRaw, $result);
                    }
                }
                return;
            }
        }

        // Single part content
        $decoded = $this->decodeContent($body, $transferEncoding);

        // Convert charset to UTF-8 if specified and not already UTF-8
        if (preg_match('/charset=["\']?([^"\'\r\n;]+)["\']?/i', $contentType, $cm)) {
            $charset = trim($cm[1]);
            if (!empty($charset) && strtolower($charset) !== 'utf-8' && strtolower($charset) !== 'utf8') {
                $converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);
                if ($converted !== false && !empty($converted)) {
                    $decoded = $converted;
                }
            }
        }
        // Ensure valid UTF-8 byte sequences
        $decoded = mb_convert_encoding($decoded, 'UTF-8', 'UTF-8');

        if (stripos($contentType, 'text/html') !== false) {
            $result['body_html'] .= $decoded;
        } elseif (stripos($contentType, 'text/plain') !== false) {
            $result['body_text'] .= $decoded;
        }
    }

    /**
     * Decode content based on Content-Transfer-Encoding.
     */
    protected function decodeContent(string $data, string $encoding): string
    {
        $encoding = strtolower(trim($encoding));
        if ($encoding === 'base64') {
            return base64_decode(preg_replace('/\s+/', '', $data)) ?: $data;
        }
        if ($encoding === 'quoted-printable') {
            return quoted_printable_decode($data);
        }
        return $data;
    }

    /**
     * Decode encoded MIME headers.
     */
    protected function decodeMimeHeader(string $str): string
    {
        if (function_exists('iconv_mime_decode')) {
            return iconv_mime_decode($str, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        }
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($str);
        }
        return $str;
    }

    /**
     * Dispatch automatic email forwarding to configured destination(s).
     */
    public function dispatchAutoForward(MailAccount $account, Email $emailRecord, array $savedAttachments = []): void
    {
        try {
            $destinations = $this->relayService->parseRecipients($account->forward_destination);
            if (empty($destinations)) {
                return;
            }

            // Loop prevention: remove recipient if it matches account's own email
            $destinations = array_values(array_filter($destinations, function ($d) use ($account) {
                return strtolower(trim($d['email'] ?? '')) !== strtolower(trim($account->email));
            }));

            if (empty($destinations)) {
                Log::warning("InboundMailService: Auto-forward skipped to prevent loop for {$account->email}.");
                return;
            }

            $subject = str_starts_with(strtolower($emailRecord->subject ?? ''), 'fwd:')
                ? $emailRecord->subject
                : 'Fwd: ' . ($emailRecord->subject ?: '(Tanpa Subjek)');

            $fromName = $emailRecord->from_name ?: $emailRecord->from_email;
            $fromFormatted = "{$fromName} &lt;{$emailRecord->from_email}&gt;";
            $dateFormatted = $emailRecord->sent_at ? $emailRecord->sent_at->toDayDateTimeString() : date('r');
            $toList = is_array($emailRecord->to) ? implode(', ', array_map(function($t) {
                if (!is_array($t)) return (string) $t;
                $e = $t['email'] ?? $t['address'] ?? '';
                $n = $t['name'] ?? '';
                return ($n && $e) ? "{$n} <{$e}>" : ($e ?: $n);
            }, $emailRecord->to)) : $emailRecord->to;

            $forwardHeaderHtml = '<div style="margin-bottom: 16px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 11px; color: #475569;">'
                . '<strong>[Penerusan Otomatis ApexMail]</strong> Email ini diterima oleh <strong>' . htmlspecialchars($account->email) . '</strong> dan diteruskan secara otomatis ke alamat Anda.'
                . '</div>'
                . '<div style="border-left: 2px solid #cbd5e1; padding-left: 14px; margin-top: 10px;">'
                . '<p style="margin: 0 0 4px 0; font-size: 12px;"><strong>Dari:</strong> ' . htmlspecialchars($fromFormatted) . '</p>'
                . '<p style="margin: 0 0 4px 0; font-size: 12px;"><strong>Tanggal:</strong> ' . htmlspecialchars($dateFormatted) . '</p>'
                . '<p style="margin: 0 0 4px 0; font-size: 12px;"><strong>Subjek:</strong> ' . htmlspecialchars($emailRecord->subject) . '</p>'
                . '<p style="margin: 0 0 12px 0; font-size: 12px;"><strong>Kepada:</strong> ' . htmlspecialchars($toList) . '</p>'
                . '<div>' . ($emailRecord->body_html ?: nl2br(e($emailRecord->body_text))) . '</div>'
                . '</div>';

            $forwardText = "[Penerusan Otomatis ApexMail dari {$account->email}]\n"
                . "---------- Pesan Asli ----------\n"
                . "Dari: {$fromName} <{$emailRecord->from_email}>\n"
                . "Tanggal: {$dateFormatted}\n"
                . "Subjek: {$emailRecord->subject}\n"
                . "Kepada: {$toList}\n\n"
                . ($emailRecord->body_text ?: strip_tags($emailRecord->body_html));

            // Attachments to forward
            $forwardAttachments = [];
            foreach ($savedAttachments as $att) {
                if (!empty($att['storage_path'])) {
                    $fullPath = storage_path('app/' . $att['storage_path']);
                    if (file_exists($fullPath)) {
                        $forwardAttachments[] = [
                            'name' => $att['filename'],
                            'mime' => $att['content_type'],
                            'size' => $att['size_bytes'],
                            'path' => $fullPath,
                        ];
                    }
                }
            }

            if (empty($forwardAttachments) && $emailRecord->attachments()->count() > 0) {
                foreach ($emailRecord->attachments as $ea) {
                    $fullPath = storage_path('app/' . $ea->storage_path);
                    if (file_exists($fullPath)) {
                        $forwardAttachments[] = [
                            'name' => $ea->filename,
                            'mime' => $ea->content_type,
                            'size' => $ea->size_bytes,
                            'path' => $fullPath,
                        ];
                    }
                }
            }

            $customReplyTo = [
                'email' => $emailRecord->from_email,
                'name' => $emailRecord->from_name,
            ];

            $this->relayService->sendEmail(
                $account,
                $destinations,
                $subject,
                $forwardHeaderHtml,
                $forwardText,
                $forwardAttachments,
                null,
                [],
                [],
                $customReplyTo
            );

            Log::info("InboundMailService: Auto-forwarded email #{$emailRecord->id} for {$account->email} to {$account->forward_destination}");
        } catch (Exception $e) {
            Log::error("InboundMailService: Auto-forwarding failed for account {$account->email}: " . $e->getMessage());
        }
    }
}

