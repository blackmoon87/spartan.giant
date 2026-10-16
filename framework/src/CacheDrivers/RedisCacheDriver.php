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
    /**
     * Every cache key lives under this prefix so flush() can clear the cache
     * without FLUSHDB — which also wiped queues, sessions and anything else
     * sharing the Redis database.
     */
    private const PREFIX = 'spartan:cache:';

    /**
     * INCRBY + EXPIRE + TTL in one atomic round-trip. The TTL is (re)applied
     * whenever the key has none, so a process dying between INCR and EXPIRE
     * can no longer leave a counter that never resets (permanent lockout).
     */
    private const INCREMENT_SCRIPT = <<<'LUA'
local hits = redis.call('INCRBY', KEYS[1], ARGV[1])
if redis.call('TTL', KEYS[1]) < 0 then
    redis.call('EXPIRE', KEYS[1], ARGV[2])
end
return {hits, redis.call('TTL', KEYS[1])}
LUA;

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
            $this->redis->setEx(self::PREFIX . $key, $ttl, $serialized);
        } else {
            $this->redis->set(self::PREFIX . $key, $serialized);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get(self::PREFIX . $key);
        return $value !== false ? unserialize($value) : $default;
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists(self::PREFIX . $key);
    }

    public function forget(string $key): void
    {
        $this->redis->del(self::PREFIX . $key);
    }

    public function flush(): void
    {
        $iterator = null;
        while (($keys = $this->redis->scan($iterator, self::PREFIX . '*', 1000)) !== false) {
            if ($keys !== []) {
                $this->redis->del($keys);
            }
        }
    }

    /**
     * Atomic counter increment backed by Redis INCR.
     *
     * @return array{0:int,1:int} [hits, reset_at]
     */
    public function increment(string $key, int $ttl, int $by = 1): array
    {
        [$hits, $remaining] = $this->redis->eval(
            self::INCREMENT_SCRIPT,
            [self::PREFIX . $key . ':counter', $by, max(1, $ttl)],
            1
        );

        return [(int) $hits, time() + max(1, (int) $remaining)];
    }
}
