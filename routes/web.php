<?php

declare(strict_types=1);

use App\Controllers\HomeController;

/**
 * Web Routes — public-facing pages.
 * Register all routes accessible to unauthenticated visitors here.
 */

$app->router->get('/', [HomeController::class, 'index']);
$app->router->get('/contact', [HomeController::class, 'contact']);
$app->router->post('/contact', [HomeController::class, 'sendContact']);
$app->router->get('/profile/{id}', [HomeController::class, 'profile']);
