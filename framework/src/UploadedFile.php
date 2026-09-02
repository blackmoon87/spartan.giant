<?php

declare(strict_types=1);

namespace Spartan;

/**
 * Lightweight file upload value object.
 *
 * Wraps a single entry from PHP's $_FILES superglobal, providing safe
 * Unicode/Arabic filename handling, hash-based naming, and atomic storage.
 *
 * Usage:
 *   $file = $this->request->upload('document');
 *   if ($file && $file->isValid()) {
 *       $path = $file->store(Paths::storage('uploads'), $file->hashName());
 *   }
 */
class UploadedFile
{
    private string $originalName;
    private string $mimeType;
    private string $tmpPath;
    private int $error;
    private int $size;

    /**
     * @param array $fileData A single $_FILES entry (name, type, tmp_name, error, size)
     */
    public function __construct(array $fileData)
    {
        $this->originalName = (string) ($fileData['name'] ?? '');
        $this->mimeType     = (string) ($fileData['type'] ?? 'application/octet-stream');
        $this->tmpPath      = (string) ($fileData['tmp_name'] ?? '');
        $this->error        = (int) ($fileData['error'] ?? UPLOAD_ERR_NO_FILE);
        $this->size         = (int) ($fileData['size'] ?? 0);
    }

    /**
     * Get the original filename as sent by the browser.
     * Preserves full UTF-8/Arabic/Unicode characters.
     */
    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Get the file extension from the original name (lowercase).
     */
    public function getExtension(): string
    {
        $ext = pathinfo($this->originalName, PATHINFO_EXTENSION);
        return strtolower($ext);
    }

    /**
     * Get the MIME type as reported by the browser.
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get the file size in bytes.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the raw PHP upload error code.
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Check if the file was uploaded successfully (no errors).
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && $this->tmpPath !== '' && is_uploaded_file($this->tmpPath);
    }

    /**
     * Generate a cryptographically secure random filename with the original extension.
     * Example: "a3f7c91b2e4d...8f1a.pdf"
     */
    public function hashName(?string $extension = null): string
    {
        $ext = $extension ?? $this->getExtension();
        $hash = bin2hex(random_bytes(16));
        return $ext !== '' ? $hash . '.' . $ext : $hash;
    }

    /**
     * Sanitize the original filename for safe filesystem storage.
     *
     * Removes control characters, null bytes, and directory traversal sequences
     * while preserving Arabic, CJK, Cyrillic, and all valid UTF-8 alphabets.
     */
    public function sanitizedName(): string
    {
        $name = $this->originalName;

        // Remove null bytes and directory traversal
        $name = str_replace(["\0", '..'], '', $name);

        // Remove control characters and filesystem-unsafe chars, keep Unicode letters
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]/', '_', $name) ?? $name;

        // Collapse multiple underscores
        $name = preg_replace('/_+/', '_', $name) ?? $name;

        return trim($name, '_ ');
    }

    /**
     * Move the uploaded file to the target directory.
     *
     * @param  string      $directory  Target directory path (must exist or will be created)
     * @param  string|null $filename   Filename to save as (default: hashName())
     * @return string|false            Full path on success, false on failure
     */
    public function store(string $directory, ?string $filename = null): string|false
    {
        if (!$this->isValid()) {
            return false;
        }

        $directory = rtrim($directory, '/\\');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $filename ?? $this->hashName();
        $target = $directory . DIRECTORY_SEPARATOR . $filename;

        if (move_uploaded_file($this->tmpPath, $target)) {
            return $target;
        }

        return false;
    }
}
