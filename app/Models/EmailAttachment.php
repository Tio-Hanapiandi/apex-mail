<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'filename',
        'content_type',
        'size_bytes',
        'storage_path',
        'is_inline',
        'content_id',
    ];

    protected $casts = [
        'is_inline' => 'boolean',
        'size_bytes' => 'integer',
    ];

    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * Resolve the absolute physical file path on server storage.
     */
    public function getRealPathAttribute(): ?string
    {
        $rawPath = $this->storage_path;
        if (empty($rawPath)) {
            return null;
        }

        // 1. Direct absolute path or relative from current directory
        if (file_exists($rawPath)) {
            return realpath($rawPath) ?: $rawPath;
        }

        // Normalize slashes
        $normalized = str_replace('\\', '/', $rawPath);

        // 2. Check via Storage disks (handles both real storage and fake disks in tests)
        try {
            $pubDiskPath = \Illuminate\Support\Facades\Storage::disk('public')->path(ltrim($normalized, '/'));
            if (file_exists($pubDiskPath)) {
                return $pubDiskPath;
            }
        } catch (\Throwable $e) {}

        try {
            $locDiskPath = \Illuminate\Support\Facades\Storage::disk('local')->path(ltrim($normalized, '/'));
            if (file_exists($locDiskPath)) {
                return $locDiskPath;
            }
        } catch (\Throwable $e) {}

        // 3. Check storage/app/public/...
        $appPublic = storage_path('app/public/' . ltrim($normalized, '/'));
        if (file_exists($appPublic)) {
            return $appPublic;
        }

        // 4. Check storage/app/...
        $appLocal = storage_path('app/' . ltrim($normalized, '/'));
        if (file_exists($appLocal)) {
            return $appLocal;
        }

        // 5. Check public/storage/...
        $pubStorage = public_path('storage/' . ltrim($normalized, '/'));
        if (file_exists($pubStorage)) {
            return $pubStorage;
        }

        // 6. Check storage/...
        $generalStorage = storage_path(ltrim($normalized, '/'));
        if (file_exists($generalStorage)) {
            return $generalStorage;
        }

        return null;
    }

    /**
     * Check if the attachment physical file exists on disk.
     */
    public function existsOnDisk(): bool
    {
        return $this->real_path !== null && file_exists($this->real_path);
    }

    /**
     * Get safe and proper download filename with appropriate extension.
     */
    public function getDownloadFilenameAttribute(): string
    {
        $filename = trim($this->filename ?: 'lampiran');
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

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

            $mime = strtolower(explode(';', $this->content_type ?? '')[0]);
            if (isset($mimeMap[$mime])) {
                $filename .= '.' . $mimeMap[$mime];
            }
        }

        return $filename;
    }
}
