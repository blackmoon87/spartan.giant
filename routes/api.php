<?php

declare(strict_types=1);

/**
 * API Routes — JSON endpoints.
 * All responses from these routes should use $this->json([...]).
 * CSRF is validated on every state-changing request by the global
 * CsrfMiddleware registered in public/index.php. For stateless API tokens,
 * implement a TokenMiddleware, attach it here and exclude the path:
 *   $app->router->excludeCsrf('/api/*');
 *
 * Example:
 *   $app->router->get('/api/products', [ProductApiController::class, 'index']);
 *   $app->router->post('/api/orders', [OrderApiController::class, 'store']);
 */

// Define API routes here
// $app->router->get('/api/products', [ProductApiController::class, 'index']);
