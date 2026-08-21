<?php

declare(strict_types=1);

namespace Spartan;

/**
 * Tag-aware cache wrapper — groups cache entries under named tags
 * so they can be invalidated together.
 *
 * Usage:
 *   // Store with tags
 *   Cache::tags(['products', 'featured'])->put('top_10', $data, 3600);
 *   Cache::tags(['products'])->remember('all', 3600, fn() => Product::all());
 *
 *   // Invalidate all entries tagged 'products'
 *   Cache::tags(['products'])->flush();
 *
 * Implementation:
 *   Each tag maintains a reverse index of cache keys stored under it.
 *   Tag index: "tag:{tagname}" → serialized array of keys.
 *   Flushing a tag iterates its index and forgets each key, then the index.
 */
class TaggedCache
{
    /** @var CacheDriverInterface */
    private CacheDriverInterface $driver;

    /** @var list<string> */
    private array $tags;

    /**
     * @param CacheDriverInterface $driver The underlying cache driver
     * @param list<string>         $tags   One or more tag names
     */
    public function __construct(CacheDriverInterface $driver, array $tags)
    {
        $this->driver = $driver;
        $this->tags   = $tags;
    }

    /**
     * Store a value under the given key, associated with all current tags.
     */
    public function put(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->driver->put($key, $value, $ttl);
        $this->indexKey($key);
    }

    /**
     * Retrieve a value from the cache.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    /**
     * Remove a specific key and de-index it from all current tags.
     */
    public function forget(string $key): void
    {
        $this->driver->forget($key);
        $this->deindexKey($key);
    }

    /**
     * Fetch from cache or execute the callback and store the result
     * under the current tags.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if ($this->driver->has($key)) {
            return $this->driver->get($key);
        }

        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    /**
     * Flush all cache entries associated with the current tags.
     * Each tag's key index is iterated and each key is forgotten.
     */
    public function flush(): void
    {
        foreach ($this->tags as $tag) {
            $indexKey = $this->tagIndexKey($tag);
            $keys     = $this->driver->get($indexKey, []);

            if (is_array($keys)) {
                foreach ($keys as $key) {
                    $this->driver->forget($key);
                }
            }

            $this->driver->forget($indexKey);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal — Tag Index Management
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Add a cache key to the index of every current tag.
     */
    private function indexKey(string $key): void
    {
        foreach ($this->tags as $tag) {
            $indexKey = $this->tagIndexKey($tag);
            $keys     = $this->driver->get($indexKey, []);

            if (!is_array($keys)) {
                $keys = [];
            }

            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
                // Tag index never expires — it lives until explicitly flushed
                $this->driver->put($indexKey, $keys, 0);
            }
        }
    }

    /**
     * Remove a cache key from the index of every current tag.
     */
    private function deindexKey(string $key): void
    {
        foreach ($this->tags as $tag) {
            $indexKey = $this->tagIndexKey($tag);
            $keys     = $this->driver->get($indexKey, []);

            if (is_array($keys)) {
                $keys = array_values(array_filter($keys, fn($k) => $k !== $key));
                if (empty($keys)) {
                    $this->driver->forget($indexKey);
                } else {
                    $this->driver->put($indexKey, $keys, 0);
                }
            }
        }
    }

    /**
     * Generate the internal cache key for a tag's index.
     */
    private function tagIndexKey(string $tag): string
    {
        return 'spartan_tag_index:' . $tag;
    }
}
