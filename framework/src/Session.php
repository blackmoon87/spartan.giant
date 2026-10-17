<?php

declare(strict_types=1);

namespace Spartan;

/**
 * Lazy session: a request without a session cookie gets no session (and no
 * session file) until something is written to it — a login, a flash message,
 * a form's CSRF token. Bots, health checks, API clients and plain pages
 * therefore cost no session I/O and leave nothing on disk. A request that
 * carries the cookie resumes its session as before.
 */
class Session implements SessionInterface
{
    protected const FLASH_KEY = 'flash_messages';

    /** Secure-cookie flag for a session started later in this request. */
    private bool $secure = false;

    /** The "cannot start" warning was logged for this request. */
    private bool $warned = false;

    public function __construct(?Request $request = null)
    {
        $this->start($request);
    }

    /**
     * Start (or resume) the session for the current request.
     * Idempotent — safe to call once per request in worker mode, where a single
     * Session object is reused across many requests.
     */
    public function start(?Request $request = null): void
    {
        // A previous request's session in this long-lived worker process: its
        // data must never reach the current request — not even when headers
        // are already out and a new session cannot be started.
        if (session_status() === PHP_SESSION_NONE && session_id() !== '') {
            $_SESSION = [];
        }

        $this->warned = false;

        if ($request !== null) {
            $this->secure = $request->isSecure();
        } else {
            $this->secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                         || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
        }

        if (session_status() === PHP_SESSION_NONE) {
            $cookie = $_COOKIE[session_name()] ?? null;
            if (!is_string($cookie) || $cookie === '') {
                // No session yet: start one only when something is written.
                // Reads see an empty session; nothing of a previous worker
                // request may remain.
                $_SESSION = [];
                return;
            }
            $this->begin();
        }

        // Initialize flash storage
        $flashMessages = $_SESSION[self::FLASH_KEY] ?? [];
        foreach ($flashMessages as $key => &$flashMessage) {
            // Mark for removal on the next request cycle
            $flashMessage['remove'] = true;
        }
        $_SESSION[self::FLASH_KEY] = $flashMessages;
    }

    /** True once a PHP session is active for this request. */
    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function setFlash(string $key, mixed $message): void
    {
        $this->ensureStarted();
        $_SESSION[self::FLASH_KEY][$key] = [
            'remove' => false,
            'value' => $message
        ];
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::FLASH_KEY][$key]['value'] ?? $default;
    }


    /**
     * Persist and close the session, releasing its lock.
     * Required in worker mode so the next request re-reads its own session
     * instead of inheriting the previous one.
     */
    public function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Clear marked flash messages at the end of request lifecycle.
     */
    public function removeFlashMessages(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_SESSION[self::FLASH_KEY])) {
            return;
        }
        $flashMessages = $_SESSION[self::FLASH_KEY] ?? [];
        foreach ($flashMessages as $key => $flashMessage) {
            if (!empty($flashMessage['remove'])) {
                unset($flashMessages[$key]);
            }
        }
        $_SESSION[self::FLASH_KEY] = $flashMessages;
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;                      // never started: no cookie, no file
        }
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Regenerate the session ID to prevent session fixation attacks.
     * MUST be called immediately after any privilege escalation (login, role change).
     * Passing true deletes the old session file from the server.
     */
    public function regenerate(): void
    {
        $this->ensureStarted();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Start the session now if this request has none yet (first write).
     * Values written before that are kept.
     */
    private function ensureStarted(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        if (headers_sent($file, $line)) {
            if ($this->warned) {
                return;
            }
            $this->warned = true;
            // Too late to send the cookie: the value lives for this request
            // only (as when a session could not start at boot). Say so — a
            // controller echoing output before setting a flash or a form
            // token is the usual cause.
            error_log("[Session] cannot start a session: output already sent at {$file}:{$line}. "
                . 'Session data written in this request will not persist.');
            return;
        }
        $pending = $_SESSION ?? [];
        $this->begin();
        $_SESSION = array_replace($_SESSION ?? [], $pending);
    }

    /** Open the PHP session with hardened cookie parameters. */
    private function begin(): void
    {
        if (headers_sent()) {
            return;
        }

        // PHP keeps the previous session ID after session_write_close().
        // In a long-lived worker that ID would be resumed by the NEXT
        // request — even one with no cookie — handing it someone else's
        // session. Clearing it makes session_start() read this request's cookie.
        if (session_id() !== '') {
            session_id('');
        }

        // Harden the session cookie before starting the session:
        //   HttpOnly  — JavaScript (document.cookie) cannot read the session ID
        //   SameSite  — Lax prevents the cookie being sent on cross-site POST requests
        //   Secure    — Only transmit over HTTPS (auto-detected from the current request)
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}
