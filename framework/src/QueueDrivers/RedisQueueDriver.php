<?php

declare(strict_types=1);

namespace Spartan\QueueDrivers;

use Spartan\QueueDriverInterface;

/**
 * Redis-backed Queue Driver using LIST operations for high throughput.
 *
 * Requires the phpredis extension (ext-redis).
 *
 * Keys:
 *   spartan:queue:pending    — LIST of JSON-encoded job payloads
 *   spartan:queue:processing — HASH of in-flight jobs keyed by job ID
 *   spartan:queue:failed     — LIST of permanently failed jobs
 *   spartan:queue:id         — auto-increment counter for job IDs
 *
 * Configure in .env:
 *   QUEUE_DRIVER=redis
 *   REDIS_HOST=127.0.0.1
 *   REDIS_PORT=6379
 */
class RedisQueueDriver implements QueueDriverInterface
{
    private \Redis $redis;

    private const PREFIX      = 'spartan:queue:';
    private const KEY_PENDING    = self::PREFIX . 'pending';
    private const KEY_PROCESSING = self::PREFIX . 'processing';
    private const KEY_FAILED     = self::PREFIX . 'failed';
    private const KEY_ID         = self::PREFIX . 'id';

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Create a RedisQueueDriver from a config array.
     */
    public static function fromConfig(array $config): self
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException(
                'RedisQueueDriver requires the phpredis extension. '
              . 'Install it or switch to QUEUE_DRIVER=database in your .env.'
            );
        }

        $redis = new \Redis();
        $redis->connect(
            $config['redis_host'] ?? '127.0.0.1',
            (int) ($config['redis_port'] ?? 6379)
        );

        if (!empty($config['redis_password'])) {
            $redis->auth($config['redis_password']);
        }

        $redis->select((int) ($config['redis_db'] ?? 0));

        return new self($redis);
    }

    public function push(string $event, string $listener, mixed $payload, int $maxAttempts = 3, string $onFailure = 'retry'): void
    {
        $id = $this->redis->incr(self::KEY_ID);

        $job = json_encode([
            'id'           => $id,
            'event'        => $event,
            'listener'     => $listener,
            'payload'      => $payload,
            'max_attempts' => $maxAttempts,
            'on_failure'   => $onFailure,
            'attempts'     => 0,
            'status'       => 'pending',
            'created_at'   => date('Y-m-d H:i:s'),
            'run_at'       => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->redis->lPush(self::KEY_PENDING, $job);
    }

    public function fetchPending(int $limit = 50): array
    {
        $jobs = [];

        for ($i = 0; $i < $limit; $i++) {
            // RPOPLPUSH is deprecated in Redis 6.2+; use LMOVE in production.
            // For broad compatibility, we use RPOP + HSET.
            $raw = $this->redis->rPop(self::KEY_PENDING);

            if ($raw === false) {
                break;
            }

            $job = json_decode($raw, true);

            if (!is_array($job) || !isset($job['id'])) {
                continue;
            }

            // Move to processing set with timestamp for stale detection
            $job['status']       = 'processing';
            $job['processing_at'] = time();
            $this->redis->hSet(self::KEY_PROCESSING, (string) $job['id'], json_encode($job));

            $jobs[] = $job;
        }

        return $jobs;
    }

    public function markDone(int|string $id): void
    {
        $this->redis->hDel(self::KEY_PROCESSING, (string) $id);
    }

    public function markFailed(int|string $id, string $error): void
    {
        $raw = $this->redis->hGet(self::KEY_PROCESSING, (string) $id);

        if ($raw !== false) {
            $job = json_decode($raw, true);
            if (is_array($job)) {
                $job['status'] = 'failed';
                $job['error']  = $error;
                $this->redis->lPush(self::KEY_FAILED, json_encode($job));
            }
        }

        $this->redis->hDel(self::KEY_PROCESSING, (string) $id);
    }

    public function scheduleRetry(int|string $id, int $attempts, string $error): void
    {
        $raw = $this->redis->hGet(self::KEY_PROCESSING, (string) $id);

        if ($raw === false) {
            return;
        }

        $job = json_decode($raw, true);
        if (!is_array($job)) {
            return;
        }

        $backoff   = [0, 60, 300];
        $delaySecs = $backoff[min($attempts, count($backoff) - 1)];

        $job['attempts'] = $attempts;
        $job['error']    = $error;
        $job['status']   = 'pending';
        $job['run_at']   = date('Y-m-d H:i:s', time() + $delaySecs);

        // Remove from processing and push back to pending
        $this->redis->hDel(self::KEY_PROCESSING, (string) $id);
        $this->redis->lPush(self::KEY_PENDING, json_encode($job));
    }

    public function reclaimStale(int $staleAfter = 600): int
    {
        $now       = time();
        $reclaimed = 0;
        $all       = $this->redis->hGetAll(self::KEY_PROCESSING);

        foreach ($all as $id => $raw) {
            $job = json_decode($raw, true);

            if (!is_array($job)) {
                $this->redis->hDel(self::KEY_PROCESSING, $id);
                continue;
            }

            $processingAt = $job['processing_at'] ?? 0;

            if (($now - $processingAt) >= $staleAfter) {
                $job['status'] = 'pending';
                $job['error']  = 'Reclaimed: worker did not finish the job.';
                $this->redis->hDel(self::KEY_PROCESSING, $id);
                $this->redis->lPush(self::KEY_PENDING, json_encode($job));
                $reclaimed++;
            }
        }

        return $reclaimed;
    }
}
