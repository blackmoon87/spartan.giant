<?php

declare(strict_types=1);

namespace Spartan\Middlewares;

use Spartan\Middleware;
use Spartan\Request;
use Spartan\Response;

/**
 * Security Headers Middleware
 *
 * Emits hardened HTTP response headers to protect against common
 * browser-level attacks (Clickjacking, MIME sniffing, XSS, info leakage).
 *
 * Usage — attach globally by adding to every route file, or per-route:
 *
 *   $app->router->get('/admin', [AdminController::class, 'index'], [
 *       SecurityHeadersMiddleware::class,
 *       AuthMiddleware::class,
 *   ]);
 *
 * To extend the Content-Security-Policy for pages that load CDN assets,
 * override by passing a custom policy string to the constructor:
 *
 *   new SecurityHeadersMiddleware("default-src 'self'; font-src 'self' https://fonts.gstatic.com")
 *
 * Default CSP is intentionally strict (self-only). Loosen only as needed.
 */
class SecurityHeadersMiddleware extends Middleware
{
    private string $csp;

    public function __construct(string $csp = "default-src 'self'")
    {
        $this->csp = $csp;
    }

    public function execute(Request $request, Response $response): void
    {
        if (headers_sent()) {
            return;
        }

        // Prevent the page from being embedded in <iframe> on external sites (Clickjacking)
        header('X-Frame-Options: SAMEORIGIN');

        // Prevent browsers from MIME-sniffing a response away from the declared Content-Type
        header('X-Content-Type-Options: nosniff');

        // Control how much referrer info is sent with requests
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Legacy XSS filter (still respected by older browsers)
        header('X-XSS-Protection: 1; mode=block');

        // Content Security Policy — primary XSS mitigation at the browser level
        header('Content-Security-Policy: ' . $this->csp);
    }
}
