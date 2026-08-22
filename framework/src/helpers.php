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

// ─── Translation Helpers ────────────────────────────────────────────────────

if (!function_exists('trans')) {
    /**
     * Translate a dot-notation key with optional parameter replacement.
     *
     * Usage:
     *   trans('app.welcome', ['name' => 'Ahmad'])
     *   trans('validation.required', ['field' => 'email'], 'ar')
     *
     * @param string      $key     Dot-notation key (e.g. 'app.notifications.title')
     * @param array       $replace Parameters to substitute (e.g. ['name' => 'Ahmad'])
     * @param string|null $locale  Override locale for this call only
     * @return string
     */
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return \Spartan\Translation\Translator::getInstance()->get($key, $replace, $locale);
    }
}

if (!function_exists('__')) {
    /**
     * Alias for trans().
     */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return trans($key, $replace, $locale);
    }
}

if (!function_exists('current_locale')) {
    /**
     * Get the current active locale.
     */
    function current_locale(): string
    {
        return \Spartan\Translation\Translator::getInstance()->getLocale();
    }
}

if (!function_exists('is_rtl')) {
    /**
     * Check if the current (or given) locale is right-to-left.
     */
    function is_rtl(?string $locale = null): bool
    {
        return \Spartan\Translation\Translator::getInstance()->isRtl($locale);
    }
}
