<?php

declare(strict_types=1);

namespace Spartan\Tests\Unit;

use PDO;
use Spartan\JobQueue;
use Spartan\Tests\Fixtures\AsyncListener;
use Spartan\Tests\Fixtures\FailingListener;
use Spartan\Tests\Fixtures\Trace;
use Spartan\Tests\TestCase;

final class JobQueueTest extends TestCase
{
    private JobQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();
        Trace::reset();
        $this->queue = new JobQueue($this->app->db);
    }

    private function lastJob(): array
    {
        return $this->app->db->query("SELECT * FROM jobs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }

    public function test_push_inserts_a_pending_job_with_a_real_id(): void
    {
        $this->queue->push('audit.job', AsyncListener::class, ['n' => 1]);
        $job = $this->lastJob();

        $this->assertSame('pending', $job['status']);
        $this->assertSame('audit.job', $job['event']);
        $this->assertNotNull($job['id'], 'the migration must produce an autoincrement primary key');
    }

    public function test_processing_runs_the_listener_and_marks_it_done(): void
    {
        $this->queue->push('audit.job', AsyncListener::class, ['n' => 1]);

        $processed = $this->queue->processPending();

        $this->assertSame(1, $processed);
        $this->assertSame(['async:1'], Trace::$log);
        $this->assertSame('done', $this->lastJob()['status']);
    }

    public function test_a_failing_job_is_scheduled_for_retry(): void
    {
        $this->queue->push('audit.fail', FailingListener::class, ['n' => 1], 3, 'retry');
        $this->queue->processPending();
        $job = $this->lastJob();

        $this->assertSame('pending', $job['status']);
        $this->assertSame(1, (int) $job['attempts']);
        $this->assertStringContainsString('listener exploded', (string) $job['error']);
    }

    public function test_retries_back_off_exponentially(): void
    {
        $this->queue->push('audit.fail', FailingListener::class, ['n' => 1], 3, 'retry');
        $this->queue->processPending();

        $this->assertGreaterThanOrEqual(time() + 55, strtotime($this->lastJob()['run_at']));
    }

    public function test_a_job_fails_permanently_after_max_attempts(): void
    {
        $this->queue->push('audit.fail', FailingListener::class, ['n' => 1], 1, 'retry');
        $this->queue->processPending();

        $this->assertSame('failed', $this->lastJob()['status']);
    }

    public function test_on_failure_stop_skips_retries(): void
    {
        $this->queue->push('audit.stop', FailingListener::class, ['n' => 1], 5, 'stop');
        $this->queue->processPending();

        $this->assertSame('failed', $this->lastJob()['status'], 'stop must win over the remaining attempts');
    }

    public function test_a_missing_listener_class_fails_the_job(): void
    {
        $this->queue->push('audit.ghost', 'No\\Such\\Listener', [], 1, 'stop');
        $this->queue->processPending();
        $job = $this->lastJob();

        $this->assertSame('failed', $job['status']);
        $this->assertStringContainsString('not found', (string) $job['error']);
    }

    public function test_a_future_job_is_not_picked_up(): void
    {
        $this->app->db->prepare("INSERT INTO jobs (event,listener,payload,run_at) VALUES (?,?,?,?)")
            ->execute(['audit.future', AsyncListener::class, '{}', date('Y-m-d H:i:s', time() + 3600)]);

        $this->assertSame(0, $this->queue->processPending());
    }

    public function test_abandoned_jobs_are_reclaimed(): void
    {
        $this->app->db->prepare("INSERT INTO jobs (event,listener,payload,status,run_at) VALUES (?,?,?,'processing',?)")
            ->execute(['audit.stale', AsyncListener::class, '{}', date('Y-m-d H:i:s', time() - 7200)]);

        $reclaimed = $this->queue->reclaimStale(600);

        $this->assertSame(1, $reclaimed);
        $this->assertSame('pending', $this->lastJob()['status'], 'a crashed worker must not strand jobs forever');
    }

    public function test_in_flight_jobs_are_not_reclaimed(): void
    {
        $this->app->db->prepare("INSERT INTO jobs (event,listener,payload,status,run_at) VALUES (?,?,?,'processing',?)")
            ->execute(['audit.fresh', AsyncListener::class, '{}', date('Y-m-d H:i:s')]);

        $this->assertSame(0, $this->queue->reclaimStale(600));
        $this->assertSame('processing', $this->lastJob()['status']);
    }

    public function test_jobs_are_claimed_inside_a_transaction(): void
    {
        // Transaction logic was extracted to the DatabaseQueueDriver
        $source = file_get_contents(__DIR__ . '/../../framework/src/QueueDrivers/DatabaseQueueDriver.php');

        $this->assertStringContainsString('beginTransaction', $source);
        $this->assertStringContainsString("status = 'processing'", $source);
    }

    public function test_an_invalid_failure_mode_falls_back_to_retry(): void
    {
        $this->queue->push('audit.mode', AsyncListener::class, ['n' => 1], 3, 'nonsense');

        $this->assertSame('retry', $this->lastJob()['on_failure']);
    }
}
