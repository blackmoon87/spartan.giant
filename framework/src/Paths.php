<?php

declare(strict_types=1);

namespace Spartan;

/**
 * Project path resolver.
 *
 * The framework ships as a library and may live anywhere — typically
 * `vendor/spartan/framework/src`. It therefore cannot infer the project root
 * from its own `__DIR__`. The application declares the root once (the
 * skeleton's `public/index.php` and CLI runner do this via `base_path` in
 * config) and every framework default is resolved relative to it.
 */
final class Paths
{
    private static ?string $base = null;

    /**
     * Declare the project root directory.
     */
    public static function setBase(string $path): void
    {
        self::$base = rtrim($path, '/\\');
    }

    /**
     * Project root. Falls back to the current working directory, which is the
     * right answer for CLI runners and test suites started from the project.
     */
    public static function base(string $append = ''): string
    {
        if (self::$base === null) {
            self::$base = rtrim((string) (getcwd() ?: '.'), '/\\');
        }

        return $append === '' ? self::$base : self::$base . '/' . ltrim($append, '/\\');
    }

    /**
     * Storage directory (logs, cache, compiled views).
     */
    public static function storage(string $append = ''): string
    {
        return self::base('storage' . ($append === '' ? '' : '/' . ltrim($append, '/\\')));
    }

    /**
     * Reset the resolver (tests).
     */
    public static function reset(): void
    {
        self::$base = null;
    }
}
