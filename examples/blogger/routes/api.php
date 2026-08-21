<?php

declare(strict_types=1);

use App\Controllers\Api\AnalyticsApiController;
use App\Controllers\Api\PostApiController;
use Spartan\Application;

/** @var Application $app */

// CSRF Exclusion for Stateless API
$app->router->excludeCsrf('/api/*');

// REST API Endpoints
$app->router->get('/api/posts', [PostApiController::class, 'index']);
$app->router->get('/api/posts/{slug}', [PostApiController::class, 'show']);
$app->router->get('/api/analytics/summary', [AnalyticsApiController::class, 'summary']);
