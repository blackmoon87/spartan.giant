<?php

declare(strict_types=1);

namespace Spartan;

/**
 * Queue Driver Interface — abstracts the storage backend for async jobs.
 *
 * Implementations:
 *   - DatabaseQueueDriver: PDO-backed (existing behavior, extracted)
 *   - RedisQueueDriver:    Redis RPOPLPUSH-backed (high throughput)
 */
interface QueueDriverInterface
{
    /**
     * Push a new job onto the queue.
     *
     * @param string $event       Event name that triggered this job
     * @param string $listener    Fully-qualified listener class name
     * @param mixed  $payload     Serializable data for the listener
     * @param int    $maxAttempts Maximum retry attempts before permanent failure
     * @param string $onFailure   'retry' | 'discard' | 'bury'
     */
    public function push(string $event, string $listener, mixed $payload, int $maxAttempts = 3, string $onFailure = 'retry'): void;

    /**
     * Fetch pending jobs, marking them as processing.
     *
     * @param  int   $limit Max number of jobs to fetch
     * @return array List of job records (structure is driver-specific)
     */
    public function fetchPending(int $limit = 50): array;

    /**
     * Mark a job as successfully completed.
     *
     * @param int|string $id Job identifier
     */
    public function markDone(int|string $id): void;

    /**
     * Mark a job as permanently failed.
     *
     * @param int|string $id    Job identifier
     * @param string     $error Error message
     */
    public function markFailed(int|string $id, string $error): void;

    /**
     * Schedule a job for retry with exponential backoff.
     *
     * @param int|string $id       Job identifier
     * @param int        $attempts Current attempt count
     * @param string     $error    Error message from the last attempt
     */
    public function scheduleRetry(int|string $id, int $attempts, string $error): void;

    /**
     * Reclaim jobs that were locked by a dead worker.
     *
     * @param  int $staleAfter Seconds after which a processing job is considered stale
     * @return int Number of jobs reclaimed
     */
    public function reclaimStale(int $staleAfter = 600): int;
}
