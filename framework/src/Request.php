<?php

declare(strict_types=1);

namespace Spartan;

class Request
{
    protected ?array $jsonParams = null;

    /**
     * HTTP methods that mutate state and therefore require CSRF verification.
     */
    private const STATE_CHANGING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Proxy IPs / CIDR ranges allowed to override the client IP via
     * X-Forwarded-For or Client-IP headers. Empty (the default) means those
     * headers are IGNORED — REMOTE_ADDR is authoritative.
     *
     * Configure with TRUSTED_PROXIES in .env (comma separated), e.g.
     *   TRUSTED_PROXIES=10.0.0.0/8,172.16.0.1
     *   TRUSTED_PROXIES=*        # trust every upstream (only behind a private LB)
     *
     * @var list<string>
     */
    protected static array $trustedProxies = [];

    /**
     * Register the trusted proxy list. Called by Application on boot.
     */
    public static function setTrustedProxies(array $proxies): void
    {
        self::$trustedProxies = array_values(array_filter(array_map('trim', $proxies), fn($p) => $p !== ''));
    }

    /**
     * Reset per-request memoised state (worker mode: FrankenPHP / RoadRunner).
     */
    public function resetState(): void
    {
        $this->jsonParams = null;
    }

    /**
     * Get request URI path, stripping query parameters and base subdirectory path.
     */
    public function getPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');
        if ($position !== false) {
            $path = substr($path, 0, $position);
        }

        $basePath = $this->getBasePath();
        if ($basePath !== '') {
            if (strpos($path, $basePath) === 0) {
                $path = substr($path, strlen($basePath));
            }
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * Get base directory path if application is running in a subdirectory.
     */
    public function getBasePath(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $requestUri = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

        // Walk up the script's directory tree to find the prefix that matches the request URI.
        // Fixes subdirectory deployments (e.g. Laragon: /spartan/public/index.php → base = /spartan).
        $dir = dirname($scriptName);
        while ($dir !== '/' && $dir !== '.' && $dir !== '') {
            if (str_starts_with($requestUri, rtrim($dir, '/') . '/') || $requestUri === rtrim($dir, '/')) {
                return rtrim($dir, '/');
            }
            $dir = dirname($dir);
        }

        return '';
    }

    /**
     * Get request HTTP method (GET, POST, etc.) with support for spoofed methods.
     */
    public function getMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST') {
            $spoofed = $_POST['_method'] ?? $this->getJsonParams()['_method'] ?? null;
            if ($spoofed && in_array(strtoupper($spoofed), ['PUT', 'PATCH', 'DELETE'], true)) {
                return strtoupper($spoofed);
            }
        }
        return $method;
    }

    /**
     * Get the real underlying request HTTP method without method spoofing.
     */
    public function getRealMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Check if the request is secure (HTTPS).
     */
    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
            || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    /**
     * Check if request is GET
     */
    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * Check if request is POST
     */
    public function isPost(): bool
    {
        return $this->getRealMethod() === 'POST';
    }

    /**
     * Retrieve a parameter from the query string ($_GET).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Retrieve a parameter from the request body ($_POST or JSON payload).
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $this->getJsonParams()[$key] ?? $default;
    }

    /**
     * Retrieve a parameter from either POST, JSON payload, or GET inputs.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $this->getJsonParams()[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * Unified parameter retrieval from GET, POST, or JSON body.
     * Alias for input() — added for compatibility with application-layer code.
     */
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->input($key, $default);
    }

    /**
     * Retrieve all inputs for the current request method as a raw associative array.
     */
    public function getBody(): array
    {
        $body = [];
        
        foreach ($_GET as $key => $value) {
            $body[$key] = $value;
        }

        foreach ($_POST as $key => $value) {
            $body[$key] = $value;
        }

        foreach ($this->getJsonParams() as $key => $value) {
            $body[$key] = $value;
        }

        return $body;
    }

    /**
     * Retrieve a request header value.
     */
    public function header(string $key, ?string $default = null): ?string
    {
        $normalizedKey = str_replace('-', '_', strtoupper($key));
        
        $serverKey = 'HTTP_' . $normalizedKey;
        if (isset($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }

        if (isset($_SERVER[$normalizedKey])) {
            return $_SERVER[$normalizedKey];
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $hKey => $hVal) {
                if (strcasecmp($hKey, $key) === 0) {
                    return $hVal;
                }
            }
        }

        return $default;
    }

    /**
     * Parse and retrieve the JSON request body parameters.
     */
    protected function getJsonParams(): array
    {
        if ($this->jsonParams === null) {
            $this->jsonParams = [];
            $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $raw = file_get_contents('php://input');
                $decoded = json_decode($raw ?: '', true);
                if (is_array($decoded)) {
                    $this->jsonParams = $decoded;
                }
            }
        }
        return $this->jsonParams;
    }

    /**
     * Validate the CSRF token for state-changing POST requests.
     * Handles both form-encoded bodies ($_POST) and JSON bodies (php://input).
     */
    public function validateCsrf(): bool
    {
        // Covers real POST plus spoofed and native PUT / PATCH / DELETE.
        if (!in_array($this->getMethod(), self::STATE_CHANGING, true)
            && !in_array($this->getRealMethod(), self::STATE_CHANGING, true)) {
            return true;
        }

        // 1. Check standard header sent by AJAX/fetch clients
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($headerToken !== null) {
            $sessionToken = Application::$app->session->get('_csrf_token');
            return hash_equals((string) $sessionToken, (string) $headerToken);
        }

        // 2. Check form-encoded POST body
        if (isset($_POST['_csrf'])) {
            $sessionToken = Application::$app->session->get('_csrf_token');
            return hash_equals((string) $sessionToken, (string) $_POST['_csrf']);
        }

        // 3. Check JSON body (Content-Type: application/json).
        // Reuses the memoised parse — php://input is only read once per request.
        $jsonToken = $this->getJsonParams()['_csrf'] ?? null;
        if ($jsonToken !== null) {
            $sessionToken = Application::$app->session->get('_csrf_token');
            return hash_equals((string) $sessionToken, (string) $jsonToken);
        }

        // No token found by any method
        return false;
    }

    /**
     * Determine if the request is an AJAX call.
     */
    public function isAjax(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               isset($_SERVER['HTTP_HX_REQUEST']);
    }

    /**
     * Get the client's IP address.
     */
    public function getIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Forwarded headers are client-controlled: honour them ONLY when the
        // immediate peer is a configured trusted proxy. Without this check any
        // caller could forge an IP and bypass IP-based rate limiting.
        if (self::$trustedProxies === [] || !self::isTrustedProxy($remote)) {
            return $remote;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? '';
        foreach (explode(',', $forwarded) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate; // left-most entry = original client
            }
        }

        return $remote;
    }

    /**
     * Check an IP against the trusted proxy list (exact match, CIDR, or '*').
     */
    private static function isTrustedProxy(string $ip): bool
    {
        foreach (self::$trustedProxies as $proxy) {
            if ($proxy === '*' || $proxy === $ip) {
                return true;
            }
            if (str_contains($proxy, '/') && self::ipInCidr($ip, $proxy)) {
                return true;
            }
        }
        return false;
    }

    /**
     * IPv4 / IPv6 CIDR membership test.
     */
    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $maskBits] = explode('/', $cidr, 2);
        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maskBits = (int) $maskBits;
        $bytes    = intdiv($maskBits, 8);
        $rest     = $maskBits % 8;

        if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }
        if ($rest === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $rest)) & 0xFF);
        return (($ipBin[$bytes] & $mask) === ($subnetBin[$bytes] & $mask));
    }

    /**
     * Retrieve uploaded file metadata from $_FILES.
     * Returns the file array or null if it doesn't exist.
     */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * Get all uploaded files.
     */
    public function getFiles(): array
    {
        return $_FILES;
    }
}
