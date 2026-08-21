<?php

declare(strict_types=1);

if (!function_exists('url')) {
    function url(string $path): string
    {
        $basePath = \Spartan\Application::$app->request->getBasePath();
        return $basePath . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url($path);
    }
}

if (!function_exists('auth')) {
    function auth(): \Spartan\AuthInterface
    {
        return \Spartan\Application::$app->auth;
    }
}

if (!function_exists('env')) {
    /**
     * Read an environment variable, with type coercion for the usual literals.
     *
     * Looks in $_ENV and $_SERVER (populated by the config loader) before
     * falling back to getenv(). Returns $default when the key is absent.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        // getenv() returns false for an unset variable.
        if ($value === false) {
            return $default;
        }

        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Read a config value with dot notation: config('db.database').
     */
    function config(string $key, mixed $default = null): mixed
    {
        if (!isset(\Spartan\Application::$app)) {
            return $default;
        }

        $value = \Spartan\Application::$app->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
