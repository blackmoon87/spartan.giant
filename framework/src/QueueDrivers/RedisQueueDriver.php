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
 *   spartan:queue:claimed_at — ZSET of in-flight job IDs scored by claim time
 *   spartan:queue:delayed    — ZSET of retries waiting out their backoff, scored by run time
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
    private const KEY_DELAYED    = self::PREFIX . 'delayed';
    private const KEY_CLAIMED_AT = self::PREFIX . 'claimed_at';

    /**
     * Pop a job and record it as in-flight in ONE atomic step. RPOP followed
     * by a separate HSET lost the job outright if the worker died between
     * the two calls. The payload is stored byte-for-byte (Lua only reads the
     * id; re-encoding with cjson could alter numbers or empty arrays).
     * Returns the raw job, '' for a malformed entry (dropped), or nil.
     */
    private const CLAIM_SCRIPT = <<<'LUA'
local raw = redis.call('RPOP', KEYS[1])
if not raw then return false end
local ok, job = pcall(cjson.decode, raw)
if not ok or type(job) ~= 'table' or job['id'] == nil then return '' end
local id = tostring(job['id'])
redis.call('HSET', KEYS[2], id, raw)
redis.call('ZADD', KEYS[3], ARGV[1], id)
return raw
LUA;
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

        $this->promoteDueJobs($limit);

        for ($i = 0; $i < $limit; $i++) {
            $now = time();
            $raw = $this->redis->eval(self::CLAIM_SCRIPT, [self::KEY_PENDING, self::KEY_PROCESSING, self::KEY_CLAIMED_AT, $now], 3);

            if (!is_string($raw)) {
                break; // queue empty
            }

            $job = $raw === '' ? null : json_decode($raw, true);
            if (!is_array($job) || !isset($job['id'])) {
                continue;
            }

            $job['status']        = 'processing';
            $job['processing_at'] = $now;
            $jobs[] = $job;
        }

        return $jobs;
    }

    public function markDone(int|string $id): void
    {
        $this->release($id);
    }

    /**
     * Drop a job from the in-flight structures. Returns true for the one
     * caller that actually removed it.
     */
    private function release(int|string $id): bool
    {
        $this->redis->zRem(self::KEY_CLAIMED_AT, (string) $id);
        return $this->redis->hDel(self::KEY_PROCESSING, (string) $id) === 1;
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

        $this->release($id);
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

        // Remove from processing; a delayed retry waits in the delayed set
        // until its backoff has elapsed (it was previously re-queued at once,
        // so the documented backoff never happened).
        $this->release($id);
        if ($delaySecs > 0) {
            $this->redis->zAdd(self::KEY_DELAYED, time() + $delaySecs, json_encode($job));
        } else {
            $this->redis->lPush(self::KEY_PENDING, json_encode($job));
        }
    }

    /**
     * Move delayed retries whose run time has arrived onto the pending list.
     * zRem() returns 1 for exactly one caller, so concurrent workers never
     * promote the same job twice.
     */
    private function promoteDueJobs(int $limit): void
    {
        $due = $this->redis->zRangeByScore(self::KEY_DELAYED, '-inf', (string) time(), ['limit' => [0, $limit]]);
        foreach ($due ?: [] as $raw) {
            if ($this->redis->zRem(self::KEY_DELAYED, $raw) === 1) {
                $this->redis->lPush(self::KEY_PENDING, $raw);
            }
        }
    }

    /**
     * Refresh the claim on jobs this worker holds but has not started yet.
     *
     * @param list<int|string> $ids
     */
    public function touch(array $ids): void
    {
        $args = [];
        foreach ($ids as $id) {
            $args[] = time();
            $args[] = (string) $id;
        }
        if ($args !== []) {
            // XX: only refresh jobs still in flight, never resurrect finished ones.
            $this->redis->zAdd(self::KEY_CLAIMED_AT, ['XX'], ...$args);
        }
    }

    public function reclaimStale(int $staleAfter = 600): int
    {
        $now       = time();
        $reclaimed = 0;
        $all       = $this->redis->hGetAll(self::KEY_PROCESSING);
        $claimedAt = $this->redis->zRange(self::KEY_CLAIMED_AT, 0, -1, ['withscores' => true]) ?: [];

        foreach ($all as $id => $raw) {
            $job = json_decode($raw, true);

            if (!is_array($job)) {
                $this->release($id);
                continue;
            }

            // Claim time from the sorted set; jobs claimed before this
            // driver version carry it inside the payload instead.
            $processingAt = (int) ($claimedAt[(string) $id] ?? $job['processing_at'] ?? 0);

            if (($now - $processingAt) >= $staleAfter) {
                // Counts as an attempt, so a job that kills its worker stops eventually.
                $job['attempts'] = (int) ($job['attempts'] ?? 0) + 1;
                $job['error']    = 'Reclaimed: worker did not finish the job.';
                if (!$this->release($id)) {
                    continue; // finished or reclaimed by another worker meanwhile
                }
                if ($job['attempts'] >= (int) ($job['max_attempts'] ?? 3)) {
                    $job['status'] = 'failed';
                    $this->redis->lPush(self::KEY_FAILED, json_encode($job));
                } else {
                    $job['status'] = 'pending';
                    $this->redis->lPush(self::KEY_PENDING, json_encode($job));
                }
                $reclaimed++;
            }
        }

        return $reclaimed;
    }
}
