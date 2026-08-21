<?php

declare(strict_types=1);

namespace Spartan;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Lightweight Job Queue — async task processor.
 *
 * Now supports pluggable backends via QueueDriverInterface:
 *   - PDO   → DatabaseQueueDriver (default, existing behavior)
 *   - Redis → RedisQueueDriver (high throughput, no DB polling)
 *
 * Backward-compatible: `new JobQueue($pdo)` works exactly as before.
 *
 * Retry strategy — Exponential Backoff:
 *   Attempt 1 → immediate
 *   Attempt 2 → +1 minute
 *   Attempt 3 → +5 minutes
 *   Attempt N → +5 minutes (capped)
 */
class JobQueue
{
    private QueueDriverInterface $driver;

    /**
     * Accepts either a PDO instance (backward-compat) or a QueueDriverInterface.
     */
    public function __construct(PDO|QueueDriverInterface $backend)
    {
        if ($backend instanceof QueueDriverInterface) {
            $this->driver = $backend;
        } else {
            $this->driver = new QueueDrivers\DatabaseQueueDriver($backend);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Push (called by EventDispatcher on async listeners)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Insert a new job into the queue.
     *
     * @param string $event       Event name (for traceability)
     * @param string $listener    Fully-qualified listener class name
     * @param mixed  $payload     Event payload (must be JSON-serialisable)
     * @param int    $maxAttempts Maximum retries before marking failed
     * @param string $onFailure   'retry' | 'stop'
     */
    public function push(
        string $event,
        string $listener,
        mixed  $payload,
        int    $maxAttempts = 3,
        string $onFailure   = 'retry'
    ): void {
        $this->driver->push($event, $listener, $payload, $maxAttempts, $onFailure);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Worker Loop (called by worker.php)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch and process all pending jobs whose run_at <= NOW().
     * Returns the number of jobs processed in this pass.
     */
    public function processPending(): int
    {
        $this->reclaimStale();

        $jobs = $this->driver->fetchPending();

        foreach ($jobs as $job) {
            $this->runJob($job);
        }

        return count($jobs);
    }

    /**
     * Return jobs abandoned by a dead worker to the pending queue.
     */
    public function reclaimStale(int $staleAfter = 600): int
    {
        return $this->driver->reclaimStale($staleAfter);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Execute a single job and handle success / failure.
     */
    private function runJob(array $job): void
    {
        $listenerClass = $job['listener'];
        $payload       = is_string($job['payload']) ? json_decode($job['payload'], true) : $job['payload'];
        $attempts      = (int) ($job['attempts'] ?? 0) + 1;

        try {
            if (!class_exists($listenerClass)) {
                throw new RuntimeException("Listener class [{$listenerClass}] not found.");
            }

            $instance = new $listenerClass();

            if (!method_exists($instance, 'handle')) {
                throw new RuntimeException("Listener [{$listenerClass}] must implement handle().");
            }

            $instance->handle($payload);

            $this->driver->markDone($job['id']);

        } catch (Throwable $e) {
            $error = $e->getMessage();
            error_log("[JobQueue] Job #{$job['id']} ({$listenerClass}) failed: {$error}");

            $onFailure = $job['on_failure'] ?? 'retry';
            $shouldRetry = $onFailure === 'retry'
                        && $attempts < (int) ($job['max_attempts'] ?? 3);

            if ($shouldRetry) {
                $this->driver->scheduleRetry($job['id'], $attempts, $error);
            } else {
                $this->driver->markFailed($job['id'], $error);
            }
        }
    }
}
