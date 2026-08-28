<?php

declare(strict_types=1);

namespace Spartan;

class Session implements SessionInterface
{
    protected const FLASH_KEY = 'flash_messages';

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
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            // Harden the session cookie before starting the session:
            //   HttpOnly  — JavaScript (document.cookie) cannot read the session ID
            //   SameSite  — Lax prevents the cookie being sent on cross-site POST requests
            //   Secure    — Only transmit over HTTPS (auto-detected from the current request)
            $isHttps = false;
            if ($request !== null) {
                $isHttps = $request->isSecure();
            } else {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                        || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
            }

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }

        // Initialize flash storage
        $flashMessages = $_SESSION[self::FLASH_KEY] ?? [];
        foreach ($flashMessages as $key => &$flashMessage) {
            // Mark for removal on the next request cycle
            $flashMessage['remove'] = true;
        }
        $_SESSION[self::FLASH_KEY] = $flashMessages;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function setFlash(string $key, mixed $message): void
    {
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
        session_regenerate_id(true);
    }
}
