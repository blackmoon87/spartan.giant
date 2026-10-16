<?php

declare(strict_types=1);

namespace Spartan\QueueDrivers;

use PDO;
use Spartan\QueueDriverInterface;
use Throwable;

/**
 * PDO-backed Queue Driver — extracted from the original JobQueue class.
 *
 * Stores jobs in the `jobs` SQL table. Uses FOR UPDATE locking to prevent
 * double-execution in concurrent environments.
 */
class DatabaseQueueDriver implements QueueDriverInterface
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function push(string $event, string $listener, mixed $payload, int $maxAttempts = 3, string $onFailure = 'retry'): void
    {
        // The jobs table declares on_failure ENUM('retry','stop'): 'discard'
        // and 'bury' (both "do not retry") made the INSERT fail under MySQL
        // strict mode. Unknown values keep falling back to 'retry'.
        $onFailure = in_array($onFailure, ['stop', 'discard', 'bury'], true) ? 'stop' : 'retry';

        // run_at is stamped by PHP, like every comparison against it in this
        // driver. The column default (the database's CURRENT_TIMESTAMP) uses
        // the DB server's timezone, which delayed or advanced new jobs
        // whenever it differed from PHP's.
        $stmt = $this->db->prepare(
            "INSERT INTO jobs (event, listener, payload, max_attempts, on_failure, run_at)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $event,
            $listener,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $maxAttempts,
            $onFailure,
            date('Y-m-d H:i:s'),
        ]);
    }

    public function fetchPending(int $limit = 50): array
    {
        $this->db->beginTransaction();

        try {
            $driver    = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $forUpdate = $driver === 'sqlite' ? '' : ' FOR UPDATE';
            $now       = date('Y-m-d H:i:s');

            $stmt = $this->db->prepare(
                "SELECT * FROM jobs
                  WHERE status = 'pending'
                    AND run_at <= ?
                  ORDER BY run_at ASC
                  LIMIT " . (int) $limit . $forUpdate
            );
            $stmt->execute([$now]);
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($jobs)) {
                $ids          = array_column($jobs, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $this->db->prepare(
                    "UPDATE jobs SET status = 'processing', run_at = ? WHERE id IN ({$placeholders})"
                )->execute([$now, ...$ids]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[DatabaseQueueDriver] fetchPending failed: ' . $e->getMessage());
            return [];
        }

        return $jobs;
    }

    public function markDone(int|string $id): void
    {
        $this->db->prepare(
            "UPDATE jobs SET status = 'done', error = NULL WHERE id = ?"
        )->execute([$id]);
    }

    public function markFailed(int|string $id, string $error): void
    {
        $this->db->prepare(
            "UPDATE jobs SET status = 'failed', error = ? WHERE id = ?"
        )->execute([$error, $id]);
    }

    public function scheduleRetry(int|string $id, int $attempts, string $error): void
    {
        $backoff   = [0, 60, 300];
        $delaySecs = $backoff[min($attempts, count($backoff) - 1)];
        $runAt     = date('Y-m-d H:i:s', time() + $delaySecs);

        $this->db->prepare(
            "UPDATE jobs
                SET status   = 'pending',
                    attempts = ?,
                    run_at   = ?,
                    error    = ?
              WHERE id = ?"
        )->execute([$attempts, $runAt, $error, $id]);
    }

    /**
     * Refresh the claim on jobs this worker holds but has not started yet.
     *
     * @param list<int|string> $ids
     */
    public function touch(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db->prepare(
            "UPDATE jobs SET run_at = ? WHERE status = 'processing' AND id IN ({$placeholders})"
        )->execute([date('Y-m-d H:i:s'), ...$ids]);
    }

    public function reclaimStale(int $staleAfter = 600): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(1, $staleAfter));

        try {
            // A reclaim counts as an attempt: a job that kills its worker
            // (fatal error, OOM) otherwise looped forever. `status` is
            // assigned before `attempts` because MySQL evaluates SET
            // left-to-right with already-updated values.
            $stmt = $this->db->prepare(
                "UPDATE jobs
                    SET status   = CASE WHEN attempts + 1 >= max_attempts THEN 'failed' ELSE 'pending' END,
                        attempts = attempts + 1,
                        error    = 'Reclaimed: worker did not finish the job.'
                  WHERE status = 'processing'
                    AND run_at <= ?"
            );
            $stmt->execute([$cutoff]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('[DatabaseQueueDriver] reclaimStale failed: ' . $e->getMessage());
            return 0;
        }
    }
}
