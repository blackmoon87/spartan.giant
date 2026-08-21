<?php

declare(strict_types=1);

namespace Spartan;

/**
 * System Health Check — reports the operational status of all subsystems.
 *
 * Usage (CLI):
 *   php spartan health
 *
 * Usage (HTTP — register as a route):
 *   $router->get('/health', fn() => (new HealthCheck())->run());
 *
 * Returns a structured array suitable for JSON output:
 *   {
 *     "status": "healthy" | "degraded",
 *     "timestamp": "2026-08-21 08:30:00",
 *     "checks": {
 *       "php":      { "status": "ok", "version": "8.4.23" },
 *       "database": { "status": "ok", "driver": "mysql", "latency_ms": 1.2 },
 *       "cache":    { "status": "ok", "driver": "file" },
 *       "storage":  { "status": "ok", "writable": true },
 *       "memory":   { "usage_mb": 4.5, "peak_mb": 12.0 }
 *     }
 *   }
 */
class HealthCheck
{
    /**
     * Run all health checks and return a structured result.
     *
     * @return array{status: string, timestamp: string, checks: array}
     */
    public function run(): array
    {
        $checks = [
            'php'      => $this->checkPhp(),
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'storage'  => $this->checkStorage(),
            'memory'   => $this->checkMemory(),
        ];

        $allOk = true;
        foreach ($checks as $check) {
            if (($check['status'] ?? 'ok') === 'fail') {
                $allOk = false;
            }
        }

        return [
            'status'    => $allOk ? 'healthy' : 'degraded',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks'    => $checks,
        ];
    }

    /**
     * PHP runtime information.
     */
    private function checkPhp(): array
    {
        return [
            'status'     => 'ok',
            'version'    => PHP_VERSION,
            'sapi'       => PHP_SAPI,
            'extensions' => [
                'pdo'   => extension_loaded('pdo'),
                'redis' => extension_loaded('redis'),
                'pcntl' => extension_loaded('pcntl'),
            ],
        ];
    }

    /**
     * Database connectivity and latency.
     */
    private function checkDatabase(): array
    {
        if (!isset(Application::$app) || Application::$app->db === null) {
            return ['status' => 'fail', 'error' => 'No database connection configured'];
        }

        try {
            $start = hrtime(true);
            Application::$app->db->query('SELECT 1');
            $elapsed = (hrtime(true) - $start) / 1e6; // nanoseconds → milliseconds

            $driver = Application::$app->db->getAttribute(\PDO::ATTR_DRIVER_NAME);

            return [
                'status'     => 'ok',
                'driver'     => $driver,
                'latency_ms' => round($elapsed, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Cache driver operational check.
     */
    private function checkCache(): array
    {
        try {
            $testKey = '__health_check_' . time();
            Cache::put($testKey, 'ok', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            $driver = Application::$app->config['cache']['driver'] ?? 'file';

            return [
                'status' => $value === 'ok' ? 'ok' : 'fail',
                'driver' => $driver,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Storage directory writability check.
     */
    private function checkStorage(): array
    {
        $storagePath = Paths::storage();
        $writable    = is_writable($storagePath);

        return [
            'status'   => $writable ? 'ok' : 'fail',
            'path'     => $storagePath,
            'writable' => $writable,
        ];
    }

    /**
     * Memory usage metrics.
     */
    private function checkMemory(): array
    {
        return [
            'status'   => 'ok',
            'usage_mb' => round(memory_get_usage(true) / 1048576, 2),
            'peak_mb'  => round(memory_get_peak_usage(true) / 1048576, 2),
        ];
    }
}
