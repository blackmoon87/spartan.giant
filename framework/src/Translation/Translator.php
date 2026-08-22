<?php

declare(strict_types=1);

namespace Spartan\Translation;

use Spartan\Application;

/**
 * Translator — Lightweight i18n/l10n engine for Spartan.
 *
 * Resolves dot-notation translation keys against PHP language files,
 * supports parameter replacement, locale fallback, and lazy file loading.
 *
 * Usage:
 *   $translator = Translator::getInstance();
 *   $translator->setLocale('ar');
 *   echo $translator->get('app.welcome', ['name' => 'Ahmad']);
 *
 * Or via global helpers:
 *   echo trans('app.welcome', ['name' => 'Ahmad']);
 *   echo __('app.welcome', ['name' => 'Ahmad']);
 *
 * Language files live under the project's `lang/` directory:
 *   lang/en/app.php   → return ['welcome' => 'Hello, :name!', ...]
 *   lang/ar/app.php   → return ['welcome' => 'أهلاً :name!', ...]
 *
 * Keys use dot notation: 'filename.nested.key'
 *   trans('app.notifications.title') → lang/{locale}/app.php['notifications']['title']
 *
 * Parameter replacement:
 *   ':param'   → replaced with the value
 *   '(:param)' → replaced with the value (parenthesized variant)
 */
class Translator
{
    private static ?self $instance = null;

    /** Current active locale (e.g. 'en', 'ar') */
    private string $locale = 'en';

    /** Fallback locale when a key is missing in the active locale */
    private string $fallback = 'en';

    /** Base path to the `lang/` directory */
    private string $langPath;

    /**
     * Loaded translation arrays, keyed by "{locale}.{group}".
     * Lazy-loaded: a file is only read when a key from that group is first requested.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $loaded = [];

    /**
     * Locales considered right-to-left.
     * Configurable via config('locale.rtl').
     *
     * @var list<string>
     */
    private array $rtlLocales = ['ar', 'he', 'fa', 'ur'];

    public function __construct(?string $langPath = null)
    {
        $this->langPath = $langPath ?: $this->resolveLangPath();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Singleton
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get or create the singleton Translator instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Replace the singleton instance (useful in tests).
     */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Locale Management
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set the active locale.
     *
     * @param string $locale ISO 639-1 code (e.g. 'en', 'ar', 'fr')
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Get the current active locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set the fallback locale.
     */
    public function setFallback(string $locale): void
    {
        $this->fallback = $locale;
    }

    /**
     * Get the fallback locale.
     */
    public function getFallback(): string
    {
        return $this->fallback;
    }

    /**
     * Set the list of RTL locales.
     *
     * @param list<string> $locales
     */
    public function setRtlLocales(array $locales): void
    {
        $this->rtlLocales = $locales;
    }

    /**
     * Check if the given (or current) locale is right-to-left.
     */
    public function isRtl(?string $locale = null): bool
    {
        return in_array($locale ?? $this->locale, $this->rtlLocales, true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Translation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Translate a dot-notation key with optional parameter replacement.
     *
     * Resolution order:
     *   1. Look up in the active locale
     *   2. Look up in the fallback locale (if different)
     *   3. Return the raw key as-is
     *
     * @param string      $key     Dot-notation key: 'group.nested.key'
     * @param array       $replace Parameters to substitute: [':name' => 'Ahmad'] or ['name' => 'Ahmad']
     * @param string|null $locale  Override locale for this call only
     * @return string
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        // Try active locale
        $line = $this->resolve($key, $locale);

        // Fallback locale
        if ($line === null && $locale !== $this->fallback) {
            $line = $this->resolve($key, $this->fallback);
        }

        // Key not found in any locale — return the raw key
        if ($line === null) {
            return $key;
        }

        // Parameter replacement
        if (!empty($replace)) {
            $line = $this->replaceParams($line, $replace);
        }

        return $line;
    }

    /**
     * Check if a translation key exists.
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->locale;
        return $this->resolve($key, $locale) !== null;
    }

    /**
     * Get all loaded translations for a group and locale.
     * Useful for debugging and the lang:check CLI command.
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $group, string $locale): array
    {
        $this->loadGroup($group, $locale);
        return $this->loaded["{$locale}.{$group}"] ?? [];
    }

    /**
     * Get a list of available locales by scanning the lang directory.
     *
     * @return list<string>
     */
    public function availableLocales(): array
    {
        if (!is_dir($this->langPath)) {
            return [];
        }

        $locales = [];
        foreach (scandir($this->langPath) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($this->langPath . '/' . $entry)) {
                $locales[] = $entry;
            }
        }
        sort($locales);
        return $locales;
    }

    /**
     * Reset loaded translations (useful in worker mode between requests).
     */
    public function resetState(): void
    {
        $this->loaded = [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal — Resolution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a dot-notation key to a string value in the given locale.
     * Returns null if the key is not found.
     *
     * Key format: 'group.segment1.segment2.leaf'
     *   - 'group' maps to the file: lang/{locale}/{group}.php
     *   - The remaining segments navigate the returned array
     */
    private function resolve(string $key, string $locale): ?string
    {
        // Split on the first dot to get the group (file name)
        $dotPos = strpos($key, '.');
        if ($dotPos === false) {
            // No dot — treat entire key as group with no sub-key
            return null;
        }

        $group = substr($key, 0, $dotPos);
        $rest  = substr($key, $dotPos + 1);

        // Lazy-load the language file
        $this->loadGroup($group, $locale);

        $cacheKey = "{$locale}.{$group}";
        if (!isset($this->loaded[$cacheKey])) {
            return null;
        }

        // Navigate the nested array
        $value = $this->loaded[$cacheKey];
        foreach (explode('.', $rest) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        // Only return strings — arrays (sub-groups) are not valid translation values
        return is_string($value) ? $value : null;
    }

    /**
     * Load a language group file if not already loaded.
     * File path: {langPath}/{locale}/{group}.php
     *
     * The file must return a PHP array.
     */
    private function loadGroup(string $group, string $locale): void
    {
        $cacheKey = "{$locale}.{$group}";
        if (isset($this->loaded[$cacheKey])) {
            return;
        }

        // Sanitize to prevent directory traversal
        if (!preg_match('#^[a-zA-Z0-9_-]+$#', $group) || !preg_match('#^[a-zA-Z0-9_-]+$#', $locale)) {
            $this->loaded[$cacheKey] = [];
            return;
        }

        $filePath = $this->langPath . '/' . $locale . '/' . $group . '.php';

        if (!file_exists($filePath)) {
            $this->loaded[$cacheKey] = [];
            return;
        }

        $data = require $filePath;
        $this->loaded[$cacheKey] = is_array($data) ? $data : [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal — Parameter Replacement
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Replace parameters in a translation string.
     *
     * Supports two formats:
     *   ':param'   → replaced with value   (Laravel-style)
     *   '(:param)' → replaced with value   (parenthesized)
     *
     * Parameters can be passed with or without the colon prefix:
     *   ['name' => 'Ahmad'] or [':name' => 'Ahmad'] — both work.
     */
    private function replaceParams(string $line, array $replace): string
    {
        // Sort by key length descending to prevent partial matches.
        // Without this, ':to' would replace the ':to' inside ':total',
        // producing '10tal' instead of '250'.
        $normalized = [];
        foreach ($replace as $key => $value) {
            $cleanKey = ltrim((string) $key, ':');
            $normalized[$cleanKey] = (string) $value;
        }
        uksort($normalized, fn(string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($normalized as $cleanKey => $strValue) {
            // Replace (:key) pattern
            $line = str_replace('(:' . $cleanKey . ')', $strValue, $line);

            // Replace :key pattern
            $line = str_replace(':' . $cleanKey, $strValue, $line);
        }

        return $line;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal — Path Resolution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the language files directory.
     * Looks for `lang/` relative to the application base path.
     */
    private function resolveLangPath(): string
    {
        if (isset(Application::$app)) {
            $basePath = Application::$app->config['base_path'] ?? '';
            if ($basePath !== '') {
                return rtrim($basePath, '/') . '/lang';
            }
        }

        // Fallback: assume framework is at project_root/framework/src/
        return dirname(__DIR__, 2) . '/lang';
    }
}
