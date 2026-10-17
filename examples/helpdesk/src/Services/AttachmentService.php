<?php

declare(strict_types=1);

namespace App\Services;

use Spartan\UploadedFile;

/**
 * Stores ticket attachments outside the web root (config storage.uploads).
 * The stored file gets a random name without an extension, and its real type
 * is read from the bytes with finfo — the browser's MIME type and file name
 * are never trusted.
 */
class AttachmentService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const ALLOWED_TYPES = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain',
    ];

    /**
     * @return array{original_name:string, stored_name:string, mime:string, size:int}
     * @throws AttachmentRejected
     */
    public function store(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new AttachmentRejected('tickets.upload.failed');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new AttachmentRejected('tickets.upload.too_large');
        }

        $storedName = $file->hashName('');
        $path = $file->store($this->directory(), $storedName);
        if ($path === false) {
            throw new AttachmentRejected('tickets.upload.failed');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, self::ALLOWED_TYPES, true)) {
            unlink($path);
            throw new AttachmentRejected('tickets.upload.bad_type');
        }

        return [
            'original_name' => mb_substr($file->sanitizedName(), 0, 255),
            'stored_name'   => $storedName,
            'mime'          => $mime,
            'size'          => $file->getSize(),
        ];
    }

    /** Remove a stored file whose database row could not be written. */
    public function discard(string $storedName): void
    {
        $path = $this->path($storedName);
        if ($path !== null) {
            unlink($path);
        }
    }

    /** Absolute path of a stored file, or null when it is missing. */
    public function path(string $storedName): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $storedName)) {
            return null;                       // never build a path from anything else
        }
        $path = $this->directory() . '/' . $storedName;
        return is_file($path) ? $path : null;
    }

    private function directory(): string
    {
        return rtrim((string) config('storage.uploads'), '/');
    }
}
