<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\MailAccount;
use App\Models\MailFolder;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PharData;
use ZipArchive;

class MailboxMigrationService
{
    /**
     * Parse raw RFC 822 EML string into structured email metadata, body, and attachments.
     *
     * @param string $rawEml
     * @return array
     */
    public function parseEml(string $rawEml): array
    {
        $rawEml = ltrim($rawEml);
        
        // Split raw email into headers and body
        $parts = preg_split("/\r?\n\r?\n/", $rawEml, 2);
        $headerRaw = $parts[0] ?? '';
        $bodyRaw = $parts[1] ?? '';

        $headers = $this->parseHeaders($headerRaw);

        // Subject decoding
        $subject = trim($headers['subject'] ?? '');
        if (!empty($subject)) {
            $subject = $this->decodeMimeHeader($subject);
        } else {
            $subject = '(Tanpa Subjek)';
        }

        // From parsing
        $rawFrom = $headers['from'] ?? '';
        $fromData = $this->parseAddressString($rawFrom);

        // To, Cc, Bcc parsing
        $toData = $this->parseAddressList($headers['to'] ?? '');
        $ccData = $this->parseAddressList($headers['cc'] ?? '');
        $bccData = $this->parseAddressList($headers['bcc'] ?? '');

        // Reply-To
        $replyTo = !empty($headers['reply-to']) ? $this->parseAddressString($headers['reply-to'])['address'] : null;

        // Message-ID
        $messageId = null;
        if (!empty($headers['message-id'])) {
            $messageId = trim($headers['message-id']);
            if (!str_starts_with($messageId, '<')) {
                $messageId = '<' . trim($messageId, "<> \t\n\r\0\x0B") . '>';
            }
        }
        if (empty($messageId)) {
            $messageId = '<migrated-' . md5($headerRaw . substr($bodyRaw, 0, 100)) . '@migrated.local>';
        }

        // In-Reply-To & References
        $inReplyTo = !empty($headers['in-reply-to']) ? trim($headers['in-reply-to']) : null;
        $threadId = !empty($headers['references']) ? trim(explode(' ', trim($headers['references']))[0]) : $messageId;

        // Date parsing (RFC 2822 / RFC 822)
        $date = Carbon::now();
        if (!empty($headers['date'])) {
            try {
                $date = Carbon::parse($headers['date']);
            } catch (Exception $e) {
                $date = Carbon::now();
            }
        }

        // MIME Body & Attachments Extraction
        $contentType = $headers['content-type'] ?? 'text/plain';
        $transferEncoding = $headers['content-transfer-encoding'] ?? '7bit';

        $parsedContent = [
            'body_html' => '',
            'body_text' => '',
            'attachments' => [],
        ];

        $this->parseMimePart($contentType, $transferEncoding, $bodyRaw, $parsedContent);

        // If body_html is empty but body_text is present, create clean HTML
        if (empty(trim($parsedContent['body_html'])) && !empty(trim($parsedContent['body_text']))) {
            $parsedContent['body_html'] = '<div style="font-family: sans-serif; font-size: 14px; line-height: 1.5; white-space: pre-wrap;">' 
                . htmlspecialchars($parsedContent['body_text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') 
                . '</div>';
        }

        // Generate snippet
        $snippetSource = !empty($parsedContent['body_text']) ? $parsedContent['body_text'] : strip_tags($parsedContent['body_html']);
        $snippet = Str::limit(trim(preg_replace('/\s+/', ' ', $snippetSource)), 120);

        return [
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'thread_id' => $threadId,
            'from_name' => $fromData['name'] ?: $fromData['address'],
            'from_email' => $fromData['address'] ?: 'unknown@migrated.local',
            'to' => $toData,
            'cc' => $ccData,
            'bcc' => $bccData,
            'reply_to' => $replyTo,
            'subject' => $subject,
            'snippet' => $snippet,
            'body_text' => $parsedContent['body_text'],
            'body_html' => $parsedContent['body_html'],
            'attachments' => $parsedContent['attachments'],
            'sent_at' => $date,
            'received_at' => $date,
            'raw_headers' => $headers,
        ];
    }

    /**
     * Import a single raw EML message into a user's mailbox folder.
     *
     * @param MailAccount $account
     * @param string $folderPathOrName
     * @param string $rawEml
     * @param array $options
     * @return array
     */
    public function importEmlToAccount(MailAccount $account, string $folderPathOrName, string $rawEml, array $options = []): array
    {
        $skipDuplicates = $options['skip_duplicates'] ?? true;
        $defaultIsRead = $options['default_is_read'] ?? true;
        $rawSize = strlen($rawEml);

        $parsed = $this->parseEml($rawEml);

        // Deduplication check
        if ($skipDuplicates) {
            $existing = Email::where('mail_account_id', $account->id)
                ->where('message_id', $parsed['message_id'])
                ->first();

            if ($existing) {
                return [
                    'status' => 'skipped',
                    'reason' => 'Pesan sudah ada (duplikat)',
                    'message_id' => $parsed['message_id'],
                    'subject' => $parsed['subject'],
                ];
            }
        }

        // Resolve or create folder
        $folder = $this->resolveTargetFolder($account, $folderPathOrName);

        // Determine if folder is Drafts or Trash or Spam
        $isDraft = ($folder->slug === 'drafts');
        $isStarred = false;

        // Check Zimbra specific flags if present
        if (!empty($parsed['raw_headers']['x-zcs-flag'])) {
            if (str_contains(strtolower($parsed['raw_headers']['x-zcs-flag']), 'flagged')) {
                $isStarred = true;
            }
        }

        // Create Email model
        $email = new Email();
        $email->mail_account_id = $account->id;
        $email->mail_folder_id = $folder->id;
        $email->message_id = $parsed['message_id'];
        $email->in_reply_to = $parsed['in_reply_to'];
        $email->thread_id = $parsed['thread_id'];
        $email->from_name = $parsed['from_name'];
        $email->from_email = $parsed['from_email'];
        $email->to = $parsed['to'];
        $email->cc = $parsed['cc'];
        $email->bcc = $parsed['bcc'];
        $email->reply_to = $parsed['reply_to'];
        $email->subject = $parsed['subject'];
        $email->snippet = $parsed['snippet'];
        $email->body_text = $parsed['body_text'];
        $email->body_html = $parsed['body_html'];
        $email->is_read = $defaultIsRead;
        $email->is_starred = $isStarred;
        $email->is_draft = $isDraft;
        $email->has_attachments = !empty($parsed['attachments']);
        $email->delivery_status = 'delivered';
        $email->size_bytes = $rawSize;
        $email->sent_at = $parsed['sent_at'];
        $email->received_at = $parsed['received_at'];
        $email->created_at = $parsed['received_at'];
        $email->updated_at = $parsed['received_at'];
        $email->save();

        // Process attachments
        $savedAttachmentsCount = 0;
        if (!empty($parsed['attachments'])) {
            foreach ($parsed['attachments'] as $att) {
                try {
                    $binaryData = base64_decode($att['Content'] ?? '');
                    if ($binaryData === false) {
                        $binaryData = $att['Content'] ?? '';
                    }

                    $cleanFilename = $att['Name'] ?: 'unnamed_attachment';
                    $ext = strtolower(pathinfo($cleanFilename, PATHINFO_EXTENSION));
                    $contentType = trim(explode(';', $att['ContentType'] ?? '')[0]);

                    if (empty($ext)) {
                        $mimeMap = [
                            'application/pdf' => 'pdf',
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp',
                            'application/zip' => 'zip',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                            'application/msword' => 'doc',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                            'application/vnd.ms-excel' => 'xls',
                            'text/plain' => 'txt',
                            'text/html' => 'html',
                        ];
                        if (isset($mimeMap[$contentType])) {
                            $ext = $mimeMap[$contentType];
                            $cleanFilename .= '.' . $ext;
                        } else {
                            $ext = 'bin';
                        }
                    }

                    $storageFilename = 'migrated_' . Str::random(24) . '.' . $ext;
                    $relativeStoragePath = 'mail_attachments/migrated/' . date('Y/m') . '/' . $storageFilename;

                    Storage::disk('public')->put($relativeStoragePath, $binaryData);
                    $absoluteStoragePath = Storage::disk('public')->path($relativeStoragePath);

                    EmailAttachment::create([
                        'email_id' => $email->id,
                        'filename' => $cleanFilename,
                        'content_type' => $contentType ?: 'application/octet-stream',
                        'size_bytes' => strlen($binaryData),
                        'storage_path' => $absoluteStoragePath,
                        'is_inline' => !empty($att['ContentId']),
                        'content_id' => $att['ContentId'] ?? null,
                    ]);

                    $savedAttachmentsCount++;
                } catch (Exception $e) {
                    Log::warning("Migration: Failed saving attachment '{$att['Name']}': " . $e->getMessage());
                }
            }
        }

        // Update account quota used_bytes
        $account->increment('used_bytes', $rawSize);

        // Update unread count if not read
        if (!$defaultIsRead) {
            $folder->increment('unread_count');
        }

        return [
            'status' => 'imported',
            'email_id' => $email->id,
            'message_id' => $email->message_id,
            'subject' => $email->subject,
            'folder' => $folder->name,
            'date' => $email->sent_at->toDateTimeString(),
            'attachments_count' => $savedAttachmentsCount,
            'size_bytes' => $rawSize,
        ];
    }

    /**
     * Import an archive file (.zip, .tgz, .tar.gz, .tar, or single .eml) into an account.
     *
     * @param MailAccount $account
     * @param string $archivePath
     * @param array $options
     * @return array
     */
    public function importArchive(MailAccount $account, string $archivePath, array $options = []): array
    {
        if (!file_exists($archivePath)) {
            throw new Exception("Berkas arsip tidak ditemukan di lokasi: {$archivePath}");
        }

        $extension = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));
        $basename = strtolower(basename($archivePath));

        if ($extension === 'eml') {
            $rawEml = file_get_contents($archivePath);
            $folder = $options['default_folder'] ?? 'inbox';
            $res = $this->importEmlToAccount($account, $folder, $rawEml, $options);
            return [
                'total_files' => 1,
                'imported_count' => ($res['status'] === 'imported') ? 1 : 0,
                'skipped_count' => ($res['status'] === 'skipped') ? 1 : 0,
                'failed_count' => ($res['status'] === 'failed') ? 1 : 0,
                'total_bytes' => filesize($archivePath),
                'folders' => [$folder],
                'details' => [$res],
                'errors' => [],
            ];
        }

        if ($extension === 'zip') {
            return $this->importFromZip($account, $archivePath, $options);
        }

        if ($extension === 'tgz' || $extension === 'tar' || str_ends_with($basename, '.tar.gz')) {
            return $this->importFromTar($account, $archivePath, $options);
        }

        throw new Exception("Format berkas arsip tidak didukung ({$extension}). Gunakan berkas .zip, .tgz, .tar.gz, atau .eml.");
    }

    /**
     * Import messages from a .zip archive.
     */
    protected function importFromZip(MailAccount $account, string $zipPath, array $options = []): array
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath);
        if ($openResult !== true) {
            throw new Exception("Gagal membuka berkas ZIP. Kode kesalahan: {$openResult}");
        }

        $totalFiles = 0;
        $importedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $totalBytes = 0;
        $foldersProcessed = [];
        $errors = [];

        $numFiles = $zip->numFiles;

        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) continue;

            $entryName = $stat['name'];
            $entrySize = $stat['size'];

            // Skip directory entries and hidden system files (e.g. __MACOSX, .DS_Store)
            if (str_ends_with($entryName, '/') || str_contains($entryName, '__MACOSX') || str_contains($entryName, '.DS_Store')) {
                continue;
            }

            // Only process .eml files or zimbra message items
            $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
            if ($ext !== 'eml' && !preg_match('/[0-9]+-[0-9]+\.msg$/i', $entryName)) {
                continue;
            }

            $totalFiles++;

            // Extract folder path from archive entry name
            // e.g. "Inbox/12345.eml" -> "Inbox"
            // "Zimbra Export/Sent/999.eml" -> "Sent"
            $folderPath = $this->determineFolderFromPath($entryName, $options['default_folder'] ?? null);
            $foldersProcessed[$folderPath] = true;

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                $failedCount++;
                $errors[] = "Gagal membaca berkas {$entryName} dari dalam arsip ZIP.";
                continue;
            }

            try {
                $res = $this->importEmlToAccount($account, $folderPath, $content, $options);
                if ($res['status'] === 'imported') {
                    $importedCount++;
                    $totalBytes += $entrySize;
                } elseif ($res['status'] === 'skipped') {
                    $skippedCount++;
                } else {
                    $failedCount++;
                }
            } catch (Exception $e) {
                $failedCount++;
                $errors[] = "Kesalahan pada {$entryName}: " . $e->getMessage();
            }
        }

        $zip->close();

        return [
            'total_files' => $totalFiles,
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'total_bytes' => $totalBytes,
            'folders' => array_keys($foldersProcessed),
            'errors' => $errors,
        ];
    }

    /**
     * Import messages from .tgz / .tar.gz / .tar archive using PharData.
     */
    protected function importFromTar(MailAccount $account, string $tarPath, array $options = []): array
    {
        $tempExtractDir = storage_path('app/temp_migration_' . uniqid('tar_', true));
        File::makeDirectory($tempExtractDir, 0755, true);

        try {
            $phar = new PharData($tarPath);
            $phar->extractTo($tempExtractDir);

            $totalFiles = 0;
            $importedCount = 0;
            $skippedCount = 0;
            $failedCount = 0;
            $totalBytes = 0;
            $foldersProcessed = [];
            $errors = [];

            $files = File::allFiles($tempExtractDir);

            foreach ($files as $file) {
                $entryName = $file->getRelativePathname();
                $ext = strtolower($file->getExtension());

                if (str_contains($entryName, '__MACOSX') || str_contains($entryName, '.DS_Store')) {
                    continue;
                }

                if ($ext !== 'eml' && !preg_match('/[0-9]+-[0-9]+\.msg$/i', $entryName)) {
                    continue;
                }

                $totalFiles++;

                $folderPath = $this->determineFolderFromPath($entryName, $options['default_folder'] ?? null);
                $foldersProcessed[$folderPath] = true;

                $content = file_get_contents($file->getRealPath());
                $fileSize = $file->getSize();

                try {
                    $res = $this->importEmlToAccount($account, $folderPath, $content, $options);
                    if ($res['status'] === 'imported') {
                        $importedCount++;
                        $totalBytes += $fileSize;
                    } elseif ($res['status'] === 'skipped') {
                        $skippedCount++;
                    } else {
                        $failedCount++;
                    }
                } catch (Exception $e) {
                    $failedCount++;
                    $errors[] = "Kesalahan pada {$entryName}: " . $e->getMessage();
                }
            }

            return [
                'total_files' => $totalFiles,
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'total_bytes' => $totalBytes,
                'folders' => array_keys($foldersProcessed),
                'errors' => $errors,
            ];
        } finally {
            if (File::isDirectory($tempExtractDir)) {
                File::deleteDirectory($tempExtractDir);
            }
        }
    }

    /**
     * Determine folder name from relative file path in archive.
     * e.g. "Inbox/12345.eml" -> "Inbox"
     * "user@domain.com/Sent/file.eml" -> "Sent"
     */
    public function determineFolderFromPath(string $relativePath, ?string $fallback = 'inbox'): string
    {
        $normalized = str_replace('\\', '/', trim($relativePath, '/'));
        $segments = explode('/', $normalized);

        // If in root of archive, fallback to default
        if (count($segments) <= 1) {
            return $fallback ?: 'inbox';
        }

        // Pop the filename off
        array_pop($segments);

        // Check if the first segment is an account folder (e.g. "kucing@domain.com" or "export")
        if (count($segments) > 1 && (str_contains($segments[0], '@') || in_array(strtolower($segments[0]), ['export', 'zimbra', 'backup', 'mailbox']))) {
            array_shift($segments);
        }

        return !empty($segments) ? implode('/', $segments) : ($fallback ?: 'inbox');
    }

    /**
     * Resolve target MailFolder for an account, mapping standard folder names and creating custom folders if needed.
     *
     * @param MailAccount $account
     * @param string $folderPath
     * @return MailFolder
     */
    public function resolveTargetFolder(MailAccount $account, string $folderPath): MailFolder
    {
        // Ensure default system folders exist
        if ($account->folders()->count() === 0) {
            $account->createDefaultFolders();
        }

        $cleanPath = trim(str_replace('\\', '/', $folderPath), '/');
        $primaryName = strtolower(explode('/', $cleanPath)[0] ?? 'inbox');

        // Mapping standard names from Zimbra / RFC to ApexMail system slugs
        $slugMap = [
            'inbox' => 'inbox',
            'kotak masuk' => 'inbox',
            'sent' => 'sent',
            'terkirim' => 'sent',
            'sent items' => 'sent',
            'sent messages' => 'sent',
            'drafts' => 'drafts',
            'draft' => 'drafts',
            'draf' => 'drafts',
            'trash' => 'trash',
            'sampah' => 'trash',
            'deleted' => 'trash',
            'deleted items' => 'trash',
            'deleted messages' => 'trash',
            'junk' => 'spam',
            'spam' => 'spam',
            'bulk mail' => 'spam',
            'archive' => 'archive',
            'arsip' => 'archive',
        ];

        // 1. Check if matches standard slug directly
        if (isset($slugMap[$primaryName]) && !str_contains($cleanPath, '/')) {
            $mappedSlug = $slugMap[$primaryName];
            $folder = $account->folders()->where('slug', $mappedSlug)->first();
            if ($folder) {
                return $folder;
            }
        }

        // 2. Custom folder / Subfolder handling
        // If cleanPath has segments (e.g. "Proyek/2024"), create hierarchy
        $segments = explode('/', $cleanPath);
        $parentId = null;
        $currentFolder = null;

        foreach ($segments as $segment) {
            $segmentName = trim($segment);
            if (empty($segmentName)) continue;

            $segmentSlug = Str::slug($segmentName);
            if (empty($segmentSlug)) {
                $segmentSlug = 'folder-' . substr(md5($segmentName), 0, 6);
            }

            // Check if matches standard slug at top level
            if ($parentId === null && isset($slugMap[strtolower($segmentName)])) {
                $mappedSlug = $slugMap[strtolower($segmentName)];
                $standard = $account->folders()->where('slug', $mappedSlug)->first();
                if ($standard) {
                    $currentFolder = $standard;
                    $parentId = $standard->id;
                    continue;
                }
            }

            // Search or create custom folder
            $folderQuery = $account->folders()->where('name', $segmentName);
            if ($parentId !== null) {
                $folderQuery->where('parent_id', $parentId);
            } else {
                $folderQuery->whereNull('parent_id');
            }

            $currentFolder = $folderQuery->first();

            if (!$currentFolder) {
                $currentFolder = $account->folders()->create([
                    'name' => $segmentName,
                    'slug' => $segmentSlug . '-' . Str::random(4),
                    'icon' => 'folder',
                    'color' => '#64748B',
                    'parent_id' => $parentId,
                    'is_system' => false,
                    'unread_count' => 0,
                ]);
            }

            $parentId = $currentFolder->id;
        }

        return $currentFolder ?: $account->folders()->where('slug', 'inbox')->firstOrFail();
    }

    /**
     * Import user accounts from CSV content.
     * CSV columns: email, display_name, password, quota_mb, is_admin
     *
     * @param string $csvContent
     * @param int $defaultDomainId
     * @param array $options
     * @return array
     */
    public function importAccountsFromCsv(string $csvContent, int $defaultDomainId, array $options = []): array
    {
        $defaultPassword = $options['default_password'] ?? 'Password123!';
        $lines = preg_split("/\r?\n/", trim($csvContent));
        if (empty($lines)) {
            throw new Exception("Berkas CSV kosong.");
        }

        $headerLine = array_shift($lines);
        $delimiters = [',', ';', "\t"];
        $detectedDelimiter = ',';
        foreach ($delimiters as $delim) {
            if (substr_count($headerLine, $delim) > substr_count($headerLine, $detectedDelimiter)) {
                $detectedDelimiter = $delim;
            }
        }

        $headers = array_map(function ($h) {
            return strtolower(trim($h, " \t\n\r\0\x0B\"'"));
        }, str_getcsv($headerLine, $detectedDelimiter));

        // Locate columns
        $emailIdx = array_search('email', $headers);
        if ($emailIdx === false) {
            // Try 'alamat email' or 'user' or 'account'
            foreach (['alamat email', 'user', 'account', 'username'] as $alias) {
                $idx = array_search($alias, $headers);
                if ($idx !== false) {
                    $emailIdx = $idx;
                    break;
                }
            }
        }

        if ($emailIdx === false) {
            throw new Exception("Kolom 'email' wajib ada pada baris pertama CSV.");
        }

        $nameIdx = array_search('display_name', $headers);
        if ($nameIdx === false) {
            foreach (['name', 'nama', 'display name'] as $alias) {
                $idx = array_search($alias, $headers);
                if ($idx !== false) {
                    $nameIdx = $idx;
                    break;
                }
            }
        }

        $passwordIdx = array_search('password', $headers);
        if ($passwordIdx === false) {
            foreach (['pass', 'sandi', 'kata sandi'] as $alias) {
                $idx = array_search($alias, $headers);
                if ($idx !== false) {
                    $passwordIdx = $idx;
                    break;
                }
            }
        }

        $quotaIdx = array_search('quota_mb', $headers);
        if ($quotaIdx === false) {
            foreach (['quota', 'kuota', 'quota_bytes'] as $alias) {
                $idx = array_search($alias, $headers);
                if ($idx !== false) {
                    $quotaIdx = $idx;
                    break;
                }
            }
        }

        $adminIdx = array_search('is_admin', $headers);
        if ($adminIdx === false) {
            $adminIdx = array_search('admin', $headers);
        }

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $errors = [];
        $accountsCreated = [];

        foreach ($lines as $lineNum => $line) {
            if (empty(trim($line))) continue;

            $row = str_getcsv($line, $detectedDelimiter);
            $email = trim($row[$emailIdx] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failedCount++;
                $errors[] = "Baris " . ($lineNum + 2) . ": Format email '{$email}' tidak valid.";
                continue;
            }

            $displayName = ($nameIdx !== false && !empty($row[$nameIdx])) ? trim($row[$nameIdx]) : explode('@', $email)[0];
            $password = ($passwordIdx !== false && !empty($row[$passwordIdx])) ? trim($row[$passwordIdx]) : $defaultPassword;
            
            $quotaMb = 1024; // Default 1GB
            if ($quotaIdx !== false && !empty($row[$quotaIdx])) {
                $quotaMb = (int) preg_replace('/[^0-9]/', '', $row[$quotaIdx]);
                if ($quotaMb <= 0) $quotaMb = 1024;
            }
            $quotaBytes = $quotaMb * 1024 * 1024;

            $isAdmin = false;
            if ($adminIdx !== false && !empty($row[$adminIdx])) {
                $val = strtolower(trim($row[$adminIdx]));
                $isAdmin = in_array($val, ['1', 'true', 'yes', 'ya']);
            }

            // Resolve domain
            $domainPart = explode('@', $email)[1] ?? '';
            $domain = Domain::where('name', $domainPart)->first();
            $domainId = $domain ? $domain->id : $defaultDomainId;

            // Check existing
            $existing = MailAccount::where('email', $email)->first();
            if ($existing) {
                if ($options['update_existing'] ?? false) {
                    $existing->display_name = $displayName;
                    $existing->quota_bytes = $quotaBytes;
                    if (!empty($row[$passwordIdx])) {
                        $existing->password = Hash::make($password);
                    }
                    $existing->save();
                    $updatedCount++;
                } else {
                    $skippedCount++;
                }
                continue;
            }

            try {
                $account = MailAccount::create([
                    'domain_id' => $domainId,
                    'username' => explode('@', $email)[0],
                    'email' => $email,
                    'display_name' => $displayName,
                    'password' => Hash::make($password),
                    'quota_bytes' => $quotaBytes,
                    'used_bytes' => 0,
                    'is_admin' => $isAdmin,
                    'is_active' => true,
                ]);

                $account->createDefaultFolders();

                $createdCount++;
                $accountsCreated[] = [
                    'email' => $account->email,
                    'display_name' => $account->display_name,
                    'quota_mb' => $quotaMb,
                ];
            } catch (Exception $e) {
                $failedCount++;
                $errors[] = "Gagal membuat akun {$email}: " . $e->getMessage();
            }
        }

        return [
            'total_rows' => count($lines),
            'created_count' => $createdCount,
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'accounts' => $accountsCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Test live IMAP connection to remote server (e.g. Zimbra port 993/SSL).
     *
     * @param string $host
     * @param int $port
     * @param string $encryption
     * @param string $username
     * @param string $password
     * @return array
     */
    public function testImapConnection(string $host, int $port = 993, string $encryption = 'ssl', string $username = '', string $password = ''): array
    {
        $prefix = ($encryption === 'ssl' || $port === 993) ? 'ssl://' : '';
        $target = $prefix . $host . ':' . $port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $timeout = 10;
        $fp = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$fp) {
            return [
                'success' => false,
                'message' => "Gagal terhubung ke host IMAP {$target}: [{$errno}] {$errstr}",
            ];
        }

        stream_set_timeout($fp, $timeout);

        // Read banner
        $banner = fgets($fp, 2048);
        if (!$banner) {
            fclose($fp);
            return [
                'success' => false,
                'message' => "Tidak menerima sambutan (greeting banner) dari server IMAP.",
            ];
        }

        // Test LOGIN
        $tag = 'A001';
        $cmd = "{$tag} LOGIN " . $this->escapeImapString($username) . " " . $this->escapeImapString($password) . "\r\n";
        fwrite($fp, $cmd);

        $loginResponse = '';
        $loginSuccess = false;

        while ($line = fgets($fp, 2048)) {
            $loginResponse .= $line;
            if (str_starts_with($line, "{$tag} OK")) {
                $loginSuccess = true;
                break;
            }
            if (str_starts_with($line, "{$tag} NO") || str_starts_with($line, "{$tag} BAD")) {
                break;
            }
        }

        // Logout
        fwrite($fp, "A002 LOGOUT\r\n");
        fclose($fp);

        if (!$loginSuccess) {
            return [
                'success' => false,
                'message' => "Koneksi berhasil tetapi autentikasi gagal: " . trim($loginResponse),
            ];
        }

        return [
            'success' => true,
            'message' => "Koneksi dan autentikasi IMAP berhasil ke server {$host}:{$port}.",
            'banner' => trim($banner),
        ];
    }

    /**
     * Escape string for IMAP command.
     */
    protected function escapeImapString(string $str): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $str) . '"';
    }

    /**
     * Parse raw headers string into lowercase associative array.
     */
    protected function parseHeaders(string $headerRaw): array
    {
        $headers = [];
        $lines = preg_split("/\r?\n/", $headerRaw);
        $currentKey = null;

        foreach ($lines as $line) {
            if ($line === '') continue;

            if (($line[0] === ' ' || $line[0] === "\t") && $currentKey !== null) {
                // Header continuation (folding)
                $headers[$currentKey] .= ' ' . trim($line);
            } elseif (preg_match('/^([a-zA-Z0-9_-]+):[ \t]*(.*)$/', $line, $m)) {
                $currentKey = strtolower($m[1]);
                $headers[$currentKey] = trim($m[2]);
            }
        }

        return $headers;
    }

    /**
     * Parse email address string like 'Full Name <email@domain.com>' or 'email@domain.com'.
     */
    protected function parseAddressString(string $str): array
    {
        $str = trim($str);
        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $str, $m)) {
            $name = trim($m[1], " \t\n\r\0\x0B\"'");
            $email = trim($m[2]);
            return [
                'name' => $this->decodeMimeHeader($name),
                'address' => strtolower($email),
            ];
        }

        return [
            'name' => '',
            'address' => strtolower(trim($str, '<> ')),
        ];
    }

    /**
     * Parse comma-separated address list into array of items.
     */
    protected function parseAddressList(string $str): array
    {
        if (empty(trim($str))) return [];

        $addresses = [];
        $tokens = preg_split('/,(?=(?:[^\"]*\"[^\"]*\")*[^\"]*$)/', $str);

        foreach ($tokens as $token) {
            $token = trim($token);
            if (!empty($token)) {
                $addresses[] = $this->parseAddressString($token);
            }
        }

        return $addresses;
    }

    /**
     * Parse MIME part recursively.
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

        // Convert charset to UTF-8
        if (preg_match('/charset=["\']?([^"\'\r\n;]+)["\']?/i', $contentType, $cm)) {
            $charset = trim($cm[1]);
            if (!empty($charset) && strtolower($charset) !== 'utf-8' && strtolower($charset) !== 'utf8') {
                $converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);
                if ($converted !== false && !empty($converted)) {
                    $decoded = $converted;
                }
            }
        }
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
     * Decode MIME header with UTF-8 support.
     */
    protected function decodeMimeHeader(string $str): string
    {
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($str, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($decoded !== false) return $decoded;
        }
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($str);
        }
        return $str;
    }
}
