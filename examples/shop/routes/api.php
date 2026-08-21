<?php

declare(strict_types=1);

use App\Controllers\Api\ProductApiController;
use Spartan\Application;

/** @var Application $app */

// CSRF Exclusion for Stateless API
$app->router->excludeCsrf('/api/*');

// REST API Endpoints
$app->router->get('/api/products', [ProductApiController::class, 'index']);
$app->router->get('/api/products/{id}', [ProductApiController::class, 'show']);
