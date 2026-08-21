<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use Spartan\EventDispatcher;
use Spartan\Tests\Fixtures\AsyncListener;
use Spartan\Tests\Fixtures\SyncListener;
use Spartan\Tests\Fixtures\Trace;
use Spartan\Tests\TestCase;

final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();
        Trace::reset();
        $this->events = new EventDispatcher();
    }

    public function test_a_class_listener_runs_immediately(): void
    {
        $this->events->listen('order.placed', SyncListener::class);
        $this->events->dispatch('order.placed', ['v' => 1]);

        $this->assertSame(['sync:1'], Trace::$log);
    }

    public function test_a_callable_listener_runs(): void
    {
        $this->events->listen('ping', function ($payload) {
            Trace::$log[] = 'callable:' . $payload;
        });
        $this->events->dispatch('ping', 'x');

        $this->assertSame(['callable:x'], Trace::$log);
    }

    public function test_listeners_run_in_registration_order(): void
    {
        $this->events->listen('multi', fn() => Trace::$log[] = 'first');
        $this->events->listen('multi', fn() => Trace::$log[] = 'second');
        $this->events->dispatch('multi');

        $this->assertSame(['first', 'second'], Trace::$log);
    }

    public function test_has_listeners_reports_registration(): void
    {
        $this->events->listen('known', fn() => null);

        $this->assertTrue($this->events->hasListeners('known'));
        $this->assertFalse($this->events->hasListeners('unknown'));
    }

    public function test_forget_removes_one_event(): void
    {
        $this->events->listen('temp', fn() => null);
        $this->events->forget('temp');

        $this->assertFalse($this->events->hasListeners('temp'));
    }

    public function test_flush_removes_every_event(): void
    {
        $this->events->listen('a', fn() => null);
        $this->events->listen('b', fn() => null);
        $this->events->flush();

        $this->assertFalse($this->events->hasListeners('a'));
        $this->assertFalse($this->events->hasListeners('b'));
    }

    public function test_dispatching_without_listeners_is_a_noop(): void
    {
        $this->events->dispatch('nobody-listens', 'x');

        $this->assertSame([], Trace::$log);
    }

    public function test_async_listeners_must_be_class_names(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->events->listen('bad', fn() => null, async: true);
    }

    public function test_an_async_listener_is_queued_not_executed(): void
    {
        $this->events->listen('queued', AsyncListener::class, async: true, maxAttempts: 2);
        $this->events->dispatch('queued', ['n' => 7]);

        $pending = (int) $this->app->db->query("SELECT COUNT(*) FROM jobs WHERE status='pending'")->fetchColumn();

        $this->assertSame(1, $pending);
        $this->assertSame([], Trace::$log, 'async work must not run inline');
    }

    public function test_the_queued_job_records_its_listener_and_payload(): void
    {
        $this->events->listen('queued', AsyncListener::class, async: true);
        $this->events->dispatch('queued', ['n' => 7]);

        $job = $this->app->db->query("SELECT * FROM jobs ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('queued', $job['event']);
        $this->assertSame(AsyncListener::class, $job['listener']);
        $this->assertSame(['n' => 7], json_decode($job['payload'], true));
    }

    public function test_an_invalid_listener_is_rejected_on_dispatch(): void
    {
        $this->events->listen('broken', 'No\\Such\\Listener');

        $this->expectException(\Throwable::class);
        $this->events->dispatch('broken');
    }
}
