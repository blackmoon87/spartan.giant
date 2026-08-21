<?php

declare(strict_types=1);

namespace Spartan;

/**
 * Configuration manager with dot-notation access, environment-specific
 * overrides, and production caching.
 *
 * Usage:
 *   $config = new Config(require 'config/config.php');
 *   $host   = $config->get('db.host', '127.0.0.1');
 *   $config->set('app.debug', false);
 *
 * Environment overrides:
 *   $config->mergeFrom('config/production.php');
 *
 * Production caching:
 *   php spartan config:cache      → writes storage/cache/config.php
 *   php spartan config:clear      → deletes the cache
 *
 *   $cached = Config::loadCached('storage/cache/config.php');
 *   if ($cached) { /* use $cached instead of rebuilding * / }
 */
class Config
{
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get a configuration value using dot notation.
     *
     *   $config->get('db.host')           → $items['db']['host']
     *   $config->get('app.name', 'Spartan') → default if missing
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current  = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Set a configuration value using dot notation.
     *
     *   $config->set('db.host', 'replica.example.com');
     */
    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current  = &$this->items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Check if a key exists (even if its value is null).
     */
    public function has(string $key): bool
    {
        $segments = explode('.', $key);
        $current  = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }

    /**
     * Merge an environment-specific override file into the configuration.
     * Values from the override file take precedence.
     *
     *   $config->mergeFrom('/path/to/production.php');
     *
     * The file must return a PHP array.
     */
    public function mergeFrom(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $overrides = require $path;

        if (is_array($overrides)) {
            $this->items = $this->arrayMergeDeep($this->items, $overrides);
        }
    }

    /**
     * Export the full configuration to a cached PHP file.
     * The cached file is a plain `return [...]` and loads instantly
     * with no .env parsing or file merging.
     *
     * @return bool True if the cache was written successfully
     */
    public function cache(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php\n\n// Auto-generated config cache — " . date('Y-m-d H:i:s') . "\nreturn "
                 . var_export($this->items, true) . ";\n";

        // Atomic write: temp file + rename prevents serving a half-written cache.
        $tmp = $path . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }

        return true;
    }

    /**
     * Load configuration from a cached file.
     * Returns null if the cache does not exist.
     */
    public static function loadCached(string $path): ?self
    {
        if (!file_exists($path)) {
            return null;
        }

        $data = require $path;

        if (!is_array($data)) {
            return null;
        }

        return new self($data);
    }

    /**
     * Return the entire configuration array.
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Deep-merge two arrays. Values from $override take precedence.
     * Numeric keys are appended; string keys are recursively merged.
     */
    private function arrayMergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_int($key)) {
                $base[] = $value;
            } elseif (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = $this->arrayMergeDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
