<?php

declare(strict_types=1);

namespace Spartan;

class Response
{
    protected int $statusCode = 200;
    protected array $headers = [];
    protected ?string $content = null;
    protected ?string $redirectUrl = null;

    /**
     * Set the HTTP response status code.
     */
    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    /**
     * Get the current HTTP response status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set a custom header.
     */
    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    /**
     * Get all custom headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the response content.
     */
     public function getContent(): ?string
     {
         return $this->content;
     }

    /**
     * Set the response content.
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * True once the request has been answered — a redirect, a body, or an
     * error status — so the route callback must not run.
     */
    public function isTerminated(): bool
    {
        return $this->redirectUrl !== null || $this->content !== null || $this->statusCode >= 400;
    }

    /**
     * Get the redirect URL.
     */
    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    /**
     * Redirect the client to another URL.
     * Only relative paths or URLs matching the configured app base URL
     * are allowed to prevent Open Redirect attacks.
     */
    public function redirect(string $url): void
    {
        // Refuse external redirects — fall back to home
        if (!self::isSafeRedirect($url)) {
            $url = '/';
        }

        // Prepend base path if relative URL and running in a subdirectory
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $basePath = Application::$app->request->getBasePath();
            if ($basePath !== '' && !str_starts_with($url, $basePath)) {
                $url = $basePath . $url;
            }
        }

        $this->redirectUrl = $url;
        $this->setHeader('Location', $url);
        if ($this->statusCode < 300 || $this->statusCode >= 400) {
            $this->statusCode = 302;
        }
    }

    /**
     * Whether a redirect target stays on this application.
     *
     * Browsers treat "//host", "/\host" and "\\host" as scheme-relative URLs
     * to another host, so only a single leading slash counts as local. An
     * absolute URL must match the configured app URL's scheme, host and port
     * exactly — a string-prefix test let "https://app.com.evil.com" and
     * "https://app.com@evil.com" through.
     */
    private static function isSafeRedirect(string $url): bool
    {
        // Control characters (tab/CR/LF) are stripped by browsers and can
        // smuggle a different URL shape past the checks below.
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return !isset($url[1]) || ($url[1] !== '/' && $url[1] !== '\\');
        }

        // Relative path ("login", "../x"): no scheme and no leading backslash.
        if (!str_starts_with($url, '\\') && !preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
            return true;
        }

        $appUrl = Application::$app->config['app']['url'] ?? '';
        $target = parse_url($url);
        $app    = $appUrl !== '' ? parse_url($appUrl) : false;
        if ($target === false || $app === false || isset($target['user']) || isset($target['pass'])) {
            return false;
        }

        $scheme = strtolower($target['scheme'] ?? '');

        return ($scheme === 'http' || $scheme === 'https')
            && $scheme === strtolower($app['scheme'] ?? '')
            && strtolower($target['host'] ?? '') === strtolower($app['host'] ?? '')
            && ($target['port'] ?? null) === ($app['port'] ?? null);
    }

    /**
     * Send a JSON response.
     */
    public function json(mixed $data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->content = json_encode($data);
    }

    /**
     * Send the status code, headers, and body.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        if ($this->content !== null) {
            echo $this->content;
        }
    }

    /**
     * Reset response properties (useful for testing).
     */
    public function reset(): void
    {
        $this->statusCode = 200;
        $this->headers = [];
        $this->content = null;
        $this->redirectUrl = null;
    }
}
