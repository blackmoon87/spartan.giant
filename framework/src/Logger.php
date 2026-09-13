<?php

declare(strict_types=1);

namespace Spartan;

/**
 * PSR-3 inspired Logger with structured output, channels, and correlation IDs.
 *
 * Backward-compatible: `new Logger()` with no arguments produces the same
 * text output as the original implementation. New features are opt-in:
 *
 *   LOG_FORMAT=json         → structured JSON lines (ELK/CloudWatch ready)
 *   LOG_CHANNEL=payments    → separate log file per channel
 *   LOG_LEVEL=WARNING       → suppress DEBUG/INFO/NOTICE output
 *
 * Correlation IDs:
 *   $logger->generateCorrelationId();   // auto per-request
 *   $logger->setCorrelationId('abc');    // manual
 *
 * Extra handlers:
 *   $logger->pushHandler(fn($level, $msg, $ctx, $line) => error_log($line));
 *
 * Child channels:
 *   $payments = $logger->channel('payments');
 *   $payments->info('Payment received');  // writes to payments-2026-08-21.log
 */
class Logger
{
    private string $logPath;
    private string $format;
    private string $channel;
    private ?string $correlationId = null;
    private string $minLevel;

    /** @var list<callable> Extra output handlers */
    private array $handlers = [];

    /**
     * Opt-in write buffering — batches log lines per file and flushes them in
     * one file_put_contents() call instead of one per log entry. Off by
     * default so `new Logger()` keeps its original one-write-per-call
     * behavior; enable it on hot paths that log frequently within a request.
     */
    private bool $bufferEnabled = false;
    private int $bufferThreshold = 100;
    private int $bufferedCount = 0;

    /** @var array<string, string> Pending log text, keyed by target file path */
    private array $buffer = [];

    /**
     * Severity levels ordered by priority (RFC 5424).
     */
    private const LEVELS = [
        'DEBUG'     => 0,
        'INFO'      => 1,
        'NOTICE'    => 2,
        'WARNING'   => 3,
        'ERROR'     => 4,
        'CRITICAL'  => 5,
        'ALERT'     => 6,
        'EMERGENCY' => 7,
    ];

    /**
     * @param string|null $logPath  Directory for log files (default: storage/logs)
     * @param string      $format   'text' (legacy default) or 'json'
     * @param string      $channel  Channel name used in filenames and JSON entries
     */
    public function __construct(?string $logPath = null, string $format = 'text', string $channel = 'app')
    {
        $this->logPath  = $logPath ?: Paths::storage('logs');
        $this->format   = $format;
        $this->channel  = $channel;
        $this->minLevel = 'DEBUG';

        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Correlation ID
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set a correlation ID that appears in every log entry.
     * Useful for tracing a single request across multiple services.
     */
    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    /**
     * Auto-generate a short unique correlation ID for this request.
     * Returns the generated ID so it can be forwarded to downstream services.
     */
    public function generateCorrelationId(): string
    {
        $this->correlationId = substr(bin2hex(random_bytes(8)), 0, 16);
        return $this->correlationId;
    }

    /**
     * Get the current correlation ID (null if not set).
     */
    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Channels and Handlers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a child logger with a different channel name.
     * Shares the same log path, format, min level, and correlation ID.
     */
    public function channel(string $name): self
    {
        $child = new self($this->logPath, $this->format, $name);
        $child->correlationId = $this->correlationId;
        $child->minLevel      = $this->minLevel;
        $child->handlers      = $this->handlers;
        if ($this->bufferEnabled) {
            $child->enableBuffering($this->bufferThreshold);
        }
        return $child;
    }

    /**
     * Add an extra output handler (stderr, syslog, external service, etc.).
     *
     * Handler signature: fn(string $level, string $message, array $context, string $formatted): void
     */
    public function pushHandler(callable $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Enable write buffering: log lines accumulate in memory per target file
     * and are flushed in a single write once $threshold entries are pending,
     * plus automatically on script shutdown so nothing is lost.
     *
     * Extra handlers (pushHandler) still fire immediately per entry — only
     * the file write is batched.
     */
    public function enableBuffering(int $threshold = 100): void
    {
        $this->bufferEnabled = true;
        $this->bufferThreshold = max(1, $threshold);
        register_shutdown_function([$this, 'flush']);
    }

    /**
     * Write all buffered log lines to disk now and clear the buffer.
     * Safe to call even when buffering is disabled (no-op if empty).
     */
    public function flush(): void
    {
        foreach ($this->buffer as $filePath => $lines) {
            if ($lines !== '') {
                file_put_contents($filePath, $lines, FILE_APPEND | LOCK_EX);
            }
        }
        $this->buffer = [];
        $this->bufferedCount = 0;
    }

    /**
     * Set the minimum severity level. Messages below this are discarded.
     */
    public function setMinLevel(string $level): void
    {
        $level = strtoupper($level);
        if (isset(self::LEVELS[$level])) {
            $this->minLevel = $level;
        }
    }

    /**
     * Get the current minimum severity level.
     */
    public function getMinLevel(): string
    {
        return $this->minLevel;
    }

    /**
     * Get the current output format.
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * Get the current channel name.
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PSR-3 Level Methods
    // ─────────────────────────────────────────────────────────────────────────

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core
    // ─────────────────────────────────────────────────────────────────────────

    public function log(string $level, string|\Stringable $message, array $context = []): void
    {
        $level = strtoupper($level);

        // Discard messages below the minimum severity
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->minLevel] ?? 0)) {
            return;
        }

        $message   = (string) $message;
        $message   = $this->interpolate($message, $context);
        $timestamp = date('Y-m-d H:i:s');

        // Format the log line
        if ($this->format === 'json') {
            $entry = [
                'timestamp'      => $timestamp,
                'level'          => $level,
                'channel'        => $this->channel,
                'message'        => $message,
            ];
            if ($this->correlationId !== null) {
                $entry['correlation_id'] = $this->correlationId;
            }
            if (!empty($context)) {
                $entry['context'] = $context;
            }
            $logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        } else {
            // Legacy text format — backward-compatible with the original Logger
            $contextString = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
            $cid = $this->correlationId !== null ? " [{$this->correlationId}]" : '';
            $logLine = "[{$timestamp}] [{$level}]{$cid} {$message}{$contextString}" . PHP_EOL;
        }

        // Write to file
        $filePath = $this->logPath . '/' . $this->channel . '-' . date('Y-m-d') . '.log';

        // Rotate if file exceeds 10 MB
        if (file_exists($filePath) && filesize($filePath) >= 10 * 1024 * 1024) {
            $rotated = $this->logPath . '/' . $this->channel . '-' . date('Y-m-d') . '-' . date('His') . '.log';
            rename($filePath, $rotated);
        }

        if ($this->bufferEnabled) {
            $this->buffer[$filePath] = ($this->buffer[$filePath] ?? '') . $logLine;
            if (++$this->bufferedCount >= $this->bufferThreshold) {
                $this->flush();
            }
        } else {
            file_put_contents($filePath, $logLine, FILE_APPEND);
        }

        // Dispatch to extra handlers
        foreach ($this->handlers as $handler) {
            try {
                $handler($level, $message, $context, $logLine);
            } catch (\Throwable) {
                // Handler failures must never break the application
            }
        }
    }

    /**
     * Interpolate context values into message placeholders.
     */
    private function interpolate(string $message, array &$context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replace);
    }
}
