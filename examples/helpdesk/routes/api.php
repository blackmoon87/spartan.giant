<?php

declare(strict_types=1);

use App\Controllers\Api\TicketApiController;
use App\Controllers\Api\TokenController;

// Token-authenticated JSON API. Clients send `Authorization: Bearer <token>`
// and `Content-Type: application/json`; there is no session, so no CSRF.
$app->router->excludeCsrf('/api/*');

$app->router->post('/api/v1/tokens', [TokenController::class, 'issue'], ['throttle:10,60']);
$app->router->delete('/api/v1/tokens/current', [TokenController::class, 'revokeCurrent'], ['api.auth']);

$app->router->get('/api/v1/tickets', [TicketApiController::class, 'index'], ['api.auth', 'throttle:120,60']);
$app->router->post('/api/v1/tickets', [TicketApiController::class, 'store'], ['api.auth', 'throttle:30,60']);
$app->router->get('/api/v1/tickets/{id}', [TicketApiController::class, 'show'], ['api.auth', 'throttle:120,60']);
