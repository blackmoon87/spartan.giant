<?php

declare(strict_types=1);

namespace Spartan\CacheDrivers;

use Spartan\CacheDriverInterface;

/**
 * File-based cache driver — zero dependencies, works out of the box.
 * Stores serialized values in the filesystem under storage/cache/.
 *
 * Configure in .env:
 *   CACHE_DRIVER=file
 *   CACHE_PATH=storage/cache
 */
class FileCacheDriver implements CacheDriverInterface
{
    private string $cachePath;

    /**
     * One-entry memo of the last has()/read() result, so an immediate get()
     * for the same key (the Cache::remember() pattern) reuses it instead of
     * reading the file a second time. Cleared on any write to that key.
     */
    private ?string $lastReadKey = null;
    private ?array $lastReadPayload = null;
    private bool $lastReadValid = false;

    public function __construct(array $config)
    {
        $path = $config['path'] ?? \Spartan\Paths::storage('cache');

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $this->cachePath = rtrim($path, '/');
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $expires = $ttl > 0 ? time() + $ttl : 0;
        $payload = serialize(['expires' => $expires, 'value' => $value]);
        file_put_contents($this->filePath($key), $payload, LOCK_EX);

        if ($this->lastReadKey === $key) {
            $this->lastReadValid = false;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->lastReadValid && $this->lastReadKey === $key) {
            $this->lastReadValid = false; // consume — a later get() re-reads
            return $this->lastReadPayload === null ? $default : $this->lastReadPayload['value'];
        }

        $payload = $this->read($this->filePath($key));
        return $payload === null ? $default : $payload['value'];
    }

    public function has(string $key): bool
    {
        // Cached for an immediate get() right after — the Cache::remember()
        // has()-then-get() pattern would otherwise read the same file twice.
        $payload = $this->read($this->filePath($key));
        $this->lastReadKey     = $key;
        $this->lastReadPayload = $payload;
        $this->lastReadValid   = true;
        return $payload !== null;
    }

    /**
     * Atomically increment a counter and return [value, expires_at].
     * Used by the rate limiter — a plain get()/put() pair loses hits under
     * concurrency, letting clients exceed their quota.
     *
     * @return array{0:int,1:int}
     */
    public function increment(string $key, int $ttl, int $by = 1): array
    {
        $file   = $this->filePath($key);
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            // Degrade to a non-atomic path rather than failing the request.
            $payload = $this->read($file);
            $value   = ((int) ($payload['value']['hits'] ?? 0)) + $by;
            $expires = $payload['expires'] ?? (time() + $ttl);
            $this->put($key, ['hits' => $value, 'reset_at' => $expires], max(1, $expires - time()));
            return [$value, $expires];
        }

        try {
            flock($handle, LOCK_EX);

            $raw     = stream_get_contents($handle);
            $payload = $raw === '' || $raw === false ? false : @unserialize($raw);
            $now     = time();

            if (!is_array($payload) || !isset($payload['expires'], $payload['value'])
                || ($payload['expires'] !== 0 && $now > $payload['expires'])) {
                $value   = $by;
                $expires = $now + $ttl;
            } else {
                $value   = ((int) ($payload['value']['hits'] ?? 0)) + $by;
                $expires = (int) $payload['expires'];
            }

            $new = serialize(['expires' => $expires, 'value' => ['hits' => $value, 'reset_at' => $expires]]);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $new);
            fflush($handle);

            if ($this->lastReadKey === $key) {
                $this->lastReadValid = false;
            }

            return [$value, $expires];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Read and validate a cache payload. Returns null when missing, expired,
     * or corrupt (a truncated/garbage file previously raised a warning and
     * was then treated as a live value).
     */
    private function read(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $payload = @unserialize($raw);
        if (!is_array($payload) || !array_key_exists('expires', $payload) || !array_key_exists('value', $payload)) {
            @unlink($file);
            return null;
        }

        if ($payload['expires'] !== 0 && time() > $payload['expires']) {
            @unlink($file);
            return null;
        }

        return $payload;
    }

    public function forget(string $key): void
    {
        $file = $this->filePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
        if ($this->lastReadKey === $key) {
            $this->lastReadValid = false;
        }
    }

    public function flush(): void
    {
        foreach (glob($this->cachePath . '/*.cache') ?: [] as $file) {
            unlink($file);
        }
    }

    private function filePath(string $key): string
    {
        return $this->cachePath . '/' . md5($key) . '.cache';
    }
}
