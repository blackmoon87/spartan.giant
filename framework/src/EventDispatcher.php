<?php

declare(strict_types=1);

namespace Spartan;

/**
 * Event Dispatcher — synchronous + async Observer pattern.
 *
 * Sync usage (default — no change from before):
 *   Application::$app->events->listen('order.placed', UpdateInventory::class);
 *   Application::$app->events->listen('order.placed', LogOrder::class);
 *
 * Async usage (listener is pushed to the jobs table):
 *   Application::$app->events->listen('order.placed', SendOrderSms::class,
 *       async: true, maxAttempts: 3, onFailure: 'retry'
 *   );
 *   Application::$app->events->listen('order.placed', SendEmail::class,
 *       async: true, maxAttempts: 1, onFailure: 'stop'
 *   );
 *
 * Dispatching — identical regardless of sync/async:
 *   Application::$app->events->dispatch('order.placed', $orderData);
 *   // OR from a Controller:
 *   $this->event('order.placed', $orderData);
 *
 * Async listeners are queued in the `jobs` DB table and executed by worker.php.
 * Sync listeners execute immediately in the current request.
 * Callable listeners are always sync (async requires a class name).
 *
 * Prerequisites for async:
 *   - Run storage/jobs.sql to create the jobs table.
 *   - Run worker.php (via cron or as a daemon).
 */
class EventDispatcher
{
    /**
     * @var array<string, list<array{
     *   listener: string|callable,
     *   async: bool,
     *   maxAttempts: int,
     *   onFailure: string
     * }>>
     */
    private array $listeners = [];

    // ─────────────────────────────────────────────────────────────────────────
    // Registration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Register a listener for the given event.
     *
     * @param string          $event       Event name (e.g. 'order.placed')
     * @param string|callable $listener    Class name with handle() method, or any callable
     * @param bool            $async       Push to job queue instead of executing immediately
     * @param int             $maxAttempts Max retry attempts (only applies when async=true)
     * @param string          $onFailure   'retry' (exponential backoff) | 'stop' (fail immediately)
     */
    public function listen(
        string          $event,
        string|callable $listener,
        bool            $async       = false,
        int             $maxAttempts = 3,
        string          $onFailure   = 'retry'
    ): void {
        // Callables are always sync — async requires a resolvable class name
        if ($async && !is_string($listener)) {
            throw new \InvalidArgumentException(
                "Async listeners must be class name strings, not callables. Register [{$event}] with a class."
            );
        }

        $this->listeners[$event][] = [
            'listener'    => $listener,
            'async'       => $async,
            'maxAttempts' => max(1, $maxAttempts),
            'onFailure'   => in_array($onFailure, ['retry', 'stop'], true) ? $onFailure : 'retry',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dispatch
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Dispatch an event to all registered listeners.
     *
     * - Sync listeners execute immediately in the current request.
     * - Async listeners are pushed to the jobs table for the worker to process.
     *
     * @param string $event   Event name
     * @param mixed  $payload Data to pass to each listener
     */
    public function dispatch(string $event, mixed $payload = null): void
    {
        foreach ($this->listeners[$event] ?? [] as $entry) {
            if ($entry['async']) {
                $this->pushToQueue($event, $entry, $payload);
            } else {
                $this->executeSync($event, $entry['listener'], $payload);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check if any listeners are registered for an event.
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * Remove all listeners for an event (useful in tests).
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Remove all registered listeners (full reset, useful in tests).
     */
    public function flush(): void
    {
        $this->listeners = [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────────

    protected function executeSync(string $event, string|callable $listener, mixed $payload): void
    {
        if (is_callable($listener)) {
            $listener($payload);
            return;
        }

        if (class_exists($listener)) {
            $instance = new $listener();
            if (!method_exists($instance, 'handle')) {
                throw new \BadMethodCallException(
                    "Event listener [{$listener}] must implement a handle() method."
                );
            }
            $instance->handle($payload);
            return;
        }

        throw new \InvalidArgumentException(
            "Event listener for [{$event}] must be a callable or a class name with a handle() method."
        );
    }

    protected function pushToQueue(string $event, array $entry, mixed $payload): void
    {
        $db = Application::$app->db ?? null;

        if ($db === null) {
            // No DB connection — fall back to synchronous execution with a warning
            error_log(
                "[EventDispatcher] Async listener [{$entry['listener']}] for [{$event}] "
                . "could not be queued (no DB connection). Executing synchronously."
            );
            $this->executeSync($event, $entry['listener'], $payload);
            return;
        }

        try {
            $queue = new JobQueue($db);
            $queue->push(
                event:       $event,
                listener:    $entry['listener'],
                payload:     $payload,
                maxAttempts: $entry['maxAttempts'],
                onFailure:   $entry['onFailure'],
            );
        } catch (\Throwable $e) {
            // Queue insert failed — fall back to sync and log
            error_log(
                "[EventDispatcher] Failed to queue listener [{$entry['listener']}] "
                . "for [{$event}]: {$e->getMessage()}. Executing synchronously."
            );
            $this->executeSync($event, $entry['listener'], $payload);
        }
    }
}
