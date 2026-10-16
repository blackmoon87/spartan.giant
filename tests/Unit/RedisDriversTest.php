<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\CacheDrivers\RedisCacheDriver;
use Spartan\QueueDrivers\RedisQueueDriver;
use Spartan\Tests\TestCase;

/**
 * Runs against a real Redis server (database 15, flushed per test). Skipped
 * when ext-redis or a server is unavailable. Port: SPARTAN_REDIS_PORT (6379).
 */
final class RedisDriversTest extends TestCase
{
    private \Redis $redis;
    private int $port;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is not installed.');
        }

        $this->port  = (int) (getenv('SPARTAN_REDIS_PORT') ?: 6379);
        $this->redis = new \Redis();
        try {
            if (!@$this->redis->connect('127.0.0.1', $this->port, 0.5)) {
                $this->markTestSkipped('No Redis server on port ' . $this->port . '.');
            }
        } catch (\RedisException) {
            $this->markTestSkipped('No Redis server on port ' . $this->port . '.');
        }
        $this->redis->select(15);
        $this->redis->flushDb();
    }

    protected function tearDown(): void
    {
        if (isset($this->redis) && $this->redis->isConnected()) {
            $this->redis->flushDb();
        }
        parent::tearDown();
    }

    private function cache(): RedisCacheDriver
    {
        return new RedisCacheDriver(['redis_host' => '127.0.0.1', 'redis_port' => $this->port, 'redis_db' => 15]);
    }

    public function test_cache_flush_keeps_keys_it_does_not_own(): void
    {
        $this->redis->set('spartan:queue:pending', 'job');
        $cache = $this->cache();
        $cache->put('a', ['x' => 1], 60);
        $cache->put('b', null, 0);

        $this->assertSame(['x' => 1], $cache->get('a'));
        $this->assertTrue($cache->has('b'));

        $cache->flush();

        $this->assertFalse($cache->has('a'));
        $this->assertSame('job', $this->redis->get('spartan:queue:pending'));
    }

    public function test_rate_limit_counter_recovers_a_lost_ttl(): void
    {
        $cache = $this->cache();
        [$first]  = $cache->increment('rl', 30);
        [$second] = $cache->increment('rl', 30);
        $this->assertSame([1, 2], [$first, $second]);

        $this->redis->persist('spartan:cache:rl:counter'); // crash between INCR and EXPIRE
        $cache->increment('rl', 30);

        $this->assertGreaterThan(0, $this->redis->ttl('spartan:cache:rl:counter'));
    }

    public function test_retries_wait_out_their_backoff(): void
    {
        $queue = new RedisQueueDriver($this->redis);
        $queue->push('e', 'L', ['p' => 1]);
        $id = $queue->fetchPending()[0]['id'];

        $queue->scheduleRetry($id, 1, 'boom'); // 60 s backoff
        $this->assertSame([], $queue->fetchPending());

        $member = $this->redis->zRange('spartan:queue:delayed', 0, 0)[0];
        $this->redis->zAdd('spartan:queue:delayed', ['XX'], time() - 1, $member);

        $due = $queue->fetchPending();
        $this->assertCount(1, $due);
        $this->assertSame(1, $due[0]['attempts']);
    }

    public function test_claiming_is_atomic_and_keeps_the_payload_intact(): void
    {
        $queue = new RedisQueueDriver($this->redis);
        $queue->push('e', 'L', ['big' => 12345678901, 'empty' => [], 'f' => 1.5]);

        $job = $queue->fetchPending()[0];

        $this->assertSame(['big' => 12345678901, 'empty' => [], 'f' => 1.5], $job['payload']);
        $this->assertSame(1, $this->redis->hLen('spartan:queue:processing'));
        $this->assertNotFalse($this->redis->zScore('spartan:queue:claimed_at', (string) $job['id']));

        $queue->markDone($job['id']);
        $this->assertSame(0, $this->redis->hLen('spartan:queue:processing'));
        $this->assertSame(0, $this->redis->zCard('spartan:queue:claimed_at'));
    }

    public function test_reclaim_counts_attempts_and_touch_protects_live_claims(): void
    {
        $queue = new RedisQueueDriver($this->redis);
        $queue->push('e', 'L', [], 2);
        $id = $queue->fetchPending()[0]['id'];

        $queue->touch([$id]);
        $this->assertSame(0, $queue->reclaimStale(600), 'a refreshed claim is not stale');

        $this->redis->zAdd('spartan:queue:claimed_at', ['XX'], 0, (string) $id);
        $this->assertSame(1, $queue->reclaimStale(600));
        $this->assertSame(1, json_decode($this->redis->lIndex('spartan:queue:pending', 0), true)['attempts']);

        $queue->fetchPending();
        $this->redis->zAdd('spartan:queue:claimed_at', ['XX'], 0, (string) $id);
        $queue->reclaimStale(600);
        $this->assertSame(1, $this->redis->lLen('spartan:queue:failed'), 'out of attempts: failed, not requeued');
    }

    public function test_jobs_claimed_by_the_previous_driver_version_are_still_reclaimed(): void
    {
        $this->redis->hSet('spartan:queue:processing', '7', json_encode([
            'id' => 7, 'listener' => 'L', 'payload' => [], 'attempts' => 0, 'max_attempts' => 3, 'processing_at' => 0,
        ]));

        $this->assertSame(1, (new RedisQueueDriver($this->redis))->reclaimStale(600));
    }
}
