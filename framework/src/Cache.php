<?php

declare(strict_types=1);

namespace Spartan;

/**
 * Cache Facade — static access to the active cache driver.
 *
 * The driver is configured via CACHE_DRIVER in .env (default: file).
 *
 * Usage:
 *   Cache::put('key', $value, 3600);
 *   $value = Cache::get('key', 'default');
 *   Cache::forget('key');
 *
 *   // Fetch or store pattern (most common)
 *   $products = Cache::remember('top_products', 3600, fn() => $model->table()->get());
 *
 *   Cache::flush(); // clear all
 */
class Cache
{
    private static ?CacheDriverInterface $driver = null;

    /**
     * Boot the cache driver from config.
     * Called once by Application on boot.
     */
    public static function boot(array $config): void
    {
        $driverName = $config['driver'] ?? 'file';

        self::$driver = match ($driverName) {
            'redis' => new CacheDrivers\RedisCacheDriver($config),
            default => new CacheDrivers\FileCacheDriver($config),
        };
    }

    /**
     * Store an item in the cache.
     *
     * @param string $key     Cache key
     * @param mixed  $value   Value to store (will be serialized)
     * @param int    $ttl     Time-to-live in seconds (0 = forever)
     */
    public static function put(string $key, mixed $value, int $ttl = 3600): void
    {
        self::driver()->put($key, $value, $ttl);
    }

    /**
     * Retrieve an item from the cache.
     * Returns $default if the key is missing or expired.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::driver()->get($key, $default);
    }

    /**
     * Check if a key exists and has not expired.
     */
    public static function has(string $key): bool
    {
        return self::driver()->has($key);
    }

    /**
     * Remove a specific key from the cache.
     */
    public static function forget(string $key): void
    {
        self::driver()->forget($key);
    }

    /**
     * Clear all cached items.
     */
    public static function flush(): void
    {
        self::driver()->flush();
    }

    /**
     * Fetch from cache, or execute the callback and store the result.
     * This is the recommended pattern to avoid redundant DB queries.
     *
     * @param string   $key      Cache key
     * @param int      $ttl      Seconds to cache (0 = forever)
     * @param callable $callback Executed only on cache miss — must return the value
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (self::has($key)) {
            return self::get($key);
        }

        $value = $callback();
        self::put($key, $value, $ttl);
        return $value;
    }

    /**
     * Atomically increment a fixed-window counter.
     * Returns [hits, reset_at]. Drivers that implement increment() do this
     * atomically; others fall back to read-modify-write.
     *
     * @return array{0:int,1:int}
     */
    public static function increment(string $key, int $ttl, int $by = 1): array
    {
        $driver = self::driver();

        if (method_exists($driver, 'increment')) {
            return $driver->increment($key, $ttl, $by);
        }

        $now  = time();
        $data = $driver->get($key);

        if (!is_array($data) || !isset($data['hits'], $data['reset_at']) || $now >= $data['reset_at']) {
            $hits    = $by;
            $resetAt = $now + $ttl;
        } else {
            $hits    = (int) $data['hits'] + $by;
            $resetAt = (int) $data['reset_at'];
        }

        $driver->put($key, ['hits' => $hits, 'reset_at' => $resetAt], max(1, $resetAt - $now));
        return [$hits, $resetAt];
    }

    /**
     * Get a tag-aware cache instance for group invalidation.
     *
     *   Cache::tags(['products', 'featured'])->put('top_10', $data, 3600);
     *   Cache::tags(['products'])->flush();  // invalidates all tagged entries
     *
     * @param list<string> $tags One or more tag names
     */
    public static function tags(array $tags): TaggedCache
    {
        return new TaggedCache(self::driver(), $tags);
    }

    private static function driver(): CacheDriverInterface
    {
        if (self::$driver === null) {
            throw new \RuntimeException(
                'Cache driver is not initialized. Ensure Cache::boot() is called during Application boot.'
            );
        }
        return self::$driver;
    }
}
