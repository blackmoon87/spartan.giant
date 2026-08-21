<?php

declare(strict_types=1);

use App\Controllers\Api\TaskApiController;
use Spartan\Application;

/** @var Application $app */

// Exclude API routes from CSRF protection
$app->router->excludeCsrf('/api/*');

// Stateless REST API endpoints
$app->router->get('/api/tasks', [TaskApiController::class, 'index']);
$app->router->get('/api/tasks/{id}', [TaskApiController::class, 'show']);
