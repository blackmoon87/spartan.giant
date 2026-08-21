<?php

declare(strict_types=1);

namespace Spartan\CacheDrivers;

use Spartan\CacheDriverInterface;

/**
 * Redis cache driver — requires either the phpredis extension or predis/predis package.
 *
 * Configure in .env:
 *   CACHE_DRIVER=redis
 *   REDIS_HOST=127.0.0.1
 *   REDIS_PORT=6379
 *   REDIS_PASSWORD=
 *   REDIS_DB=0
 */
class RedisCacheDriver implements CacheDriverInterface
{
    private \Redis $redis;

    public function __construct(array $config)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException(
                'RedisCacheDriver requires the phpredis extension. '
              . 'Install it or switch to CACHE_DRIVER=file in your .env.'
            );
        }

        $this->redis = new \Redis();
        $this->redis->connect(
            $config['redis_host'] ?? '127.0.0.1',
            (int) ($config['redis_port'] ?? 6379)
        );

        if (!empty($config['redis_password'])) {
            $this->redis->auth($config['redis_password']);
        }

        $this->redis->select((int) ($config['redis_db'] ?? 0));
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $serialized = serialize($value);
        if ($ttl > 0) {
            $this->redis->setEx($key, $ttl, $serialized);
        } else {
            $this->redis->set($key, $serialized);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : $default;
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }

    public function forget(string $key): void
    {
        $this->redis->del($key);
    }

    public function flush(): void
    {
        $this->redis->flushDb();
    }

    /**
     * Atomic counter increment backed by Redis INCR.
     *
     * @return array{0:int,1:int} [hits, reset_at]
     */
    public function increment(string $key, int $ttl, int $by = 1): array
    {
        $counter = $key . ':counter';
        $hits    = (int) $this->redis->incrBy($counter, $by);

        if ($hits === $by) {
            // First hit in this window — start the expiry clock.
            $this->redis->expire($counter, $ttl);
            $resetAt = time() + $ttl;
            $this->redis->setEx($key . ':reset', $ttl, (string) $resetAt);
        } else {
            $stored  = $this->redis->get($key . ':reset');
            $resetAt = $stored !== false ? (int) $stored : time() + max(1, (int) $this->redis->ttl($counter));
        }

        return [$hits, $resetAt];
    }
}
