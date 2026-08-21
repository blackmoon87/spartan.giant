<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AuthorPostController;
use App\Controllers\BlogController;
use App\Controllers\CommentController;
use App\Controllers\LikeController;
use App\Controllers\NewsletterController;
use Spartan\Application;
use App\Listeners\NotifySubscribersListener;
use App\Listeners\PingSearchEnginesListener;
use App\Listeners\UpdatePostMetricsListener;

/** @var Application $app */

// Authentication Routes
$app->router->get('/login', [AuthController::class, 'showLogin']);
$app->router->post('/login', [AuthController::class, 'processLogin']);
$app->router->get('/logout', [AuthController::class, 'logout']);

// Public Blog Routes
$app->router->get('/', [BlogController::class, 'index']);
$app->router->get('/category/{slug}', [BlogController::class, 'category']);
$app->router->post('/blog/search', [BlogController::class, 'search']);
$app->router->get('/post/{slug}', [BlogController::class, 'show']);
$app->router->post('/comment/store', [CommentController::class, 'store']);
$app->router->post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
$app->router->post('/like/toggle', [LikeController::class, 'toggle']);

// Author Publishing Routes
$app->router->get('/author/posts', [AuthorPostController::class, 'index'], ['auth']);
$app->router->post('/author/posts/store', [AuthorPostController::class, 'store'], ['auth']);

// Event & Listener Registration
$app->events->listen('post.published', UpdatePostMetricsListener::class); // Sync listener
$app->events->listen('post.published', NotifySubscribersListener::class, async: true, maxAttempts: 3); // Async queued listener
$app->events->listen('post.published', PingSearchEnginesListener::class, async: true, maxAttempts: 3); // Async queued listener
