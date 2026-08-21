<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\Cache;
use Spartan\Tests\TestCase;

final class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_put_and_get_round_trip(): void
    {
        Cache::put('key', ['x' => 1], 60);

        $this->assertSame(['x' => 1], Cache::get('key'));
    }

    public function test_get_returns_the_default_on_a_miss(): void
    {
        $this->assertSame('dflt', Cache::get('absent', 'dflt'));
        $this->assertNull(Cache::get('absent'));
    }

    public function test_has_reports_presence(): void
    {
        Cache::put('key', 'v', 60);

        $this->assertTrue(Cache::has('key'));
        $this->assertFalse(Cache::has('absent'));
    }

    public function test_forget_removes_a_key(): void
    {
        Cache::put('key', 'v', 60);
        Cache::forget('key');

        $this->assertFalse(Cache::has('key'));
    }

    public function test_objects_survive_serialization(): void
    {
        Cache::put('obj', (object) ['a' => [1, 2, 3]], 60);

        $this->assertSame([1, 2, 3], Cache::get('obj')->a);
    }

    public function test_zero_ttl_stores_indefinitely(): void
    {
        Cache::put('forever', 'v', 0);

        $this->assertSame('v', Cache::get('forever'));
    }

    /** @group slow */
    public function test_entries_expire_after_their_ttl(): void
    {
        Cache::put('ttl', 'v', 1);
        sleep(2);

        $this->assertSame('gone', Cache::get('ttl', 'gone'));
    }

    public function test_remember_computes_once_then_serves_the_cache(): void
    {
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;
            return 'computed';
        };

        $this->assertSame('computed', Cache::remember('memo', 60, $compute));
        $this->assertSame('computed', Cache::remember('memo', 60, $compute));
        $this->assertSame(1, $calls);
    }

    public function test_increment_returns_hits_and_reset_time(): void
    {
        [$hits, $resetAt] = Cache::increment('counter', 60);

        $this->assertSame(1, $hits);
        $this->assertGreaterThan(time(), $resetAt);
    }

    public function test_increment_keeps_the_window_fixed(): void
    {
        [, $first]  = Cache::increment('counter', 60);
        [, $second] = Cache::increment('counter', 60);

        $this->assertSame($first, $second);
    }

    public function test_a_corrupt_payload_degrades_to_a_miss(): void
    {
        file_put_contents($this->work . '/cache/' . md5('corrupt') . '.cache', 'garbage-not-serialized');

        $this->assertSame('fallback', Cache::get('corrupt', 'fallback'));
        $this->assertFalse(Cache::has('corrupt'));
    }

    public function test_an_empty_cache_file_degrades_to_a_miss(): void
    {
        file_put_contents($this->work . '/cache/' . md5('empty') . '.cache', '');

        $this->assertSame('fallback', Cache::get('empty', 'fallback'));
    }

    public function test_flush_clears_everything(): void
    {
        Cache::put('a', 1, 60);
        Cache::put('b', 2, 60);
        Cache::flush();

        $this->assertFalse(Cache::has('a'));
        $this->assertFalse(Cache::has('b'));
    }

    public function test_an_unbooted_driver_raises(): void
    {
        $property = new \ReflectionProperty(Cache::class, 'driver');
        $property->setAccessible(true);
        $driver = $property->getValue();
        $property->setValue(null, null);

        try {
            $this->expectException(\RuntimeException::class);
            Cache::get('x');
        } finally {
            $property->setValue(null, $driver);
        }
    }
}
