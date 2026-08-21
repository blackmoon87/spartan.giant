<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ProjectController;
use App\Controllers\TaskController;
use App\Controllers\AdminController;
use Spartan\Application;
use App\Listeners\LogActivityListener;
use App\Listeners\NotifyAssigneeListener;

/** @var Application $app */

// ─── Public Routes ───────────────────────────────────────────────────────────
$app->router->get('/', [DashboardController::class, 'index']);
$app->router->get('/login', [AuthController::class, 'showLogin']);
$app->router->post('/login', [AuthController::class, 'processLogin']);
$app->router->get('/logout', [AuthController::class, 'logout']);

// ─── Authenticated Routes (middleware: 'auth') ──────────────────────────────
$app->router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$app->router->get('/projects', [ProjectController::class, 'index'], ['auth']);
$app->router->post('/projects/store', [ProjectController::class, 'store'], ['auth']);
$app->router->get('/project/{slug}', [ProjectController::class, 'show'], ['auth']);
$app->router->post('/tasks/store', [TaskController::class, 'store'], ['auth']);
$app->router->put('/task/{id}/complete', [TaskController::class, 'complete'], ['auth']);
$app->router->get('/task/{id}/partial', [TaskController::class, 'partialRow'], ['auth']);

// ─── Admin Routes (#[RequireRole('admin')] enforced on controller) ──────────
$app->router->get('/admin', [AdminController::class, 'index'], ['auth']);

// ─── Event Listeners ─────────────────────────────────────────────────────────
$app->events->listen('task.completed', LogActivityListener::class);                             // Sync
$app->events->listen('task.completed', NotifyAssigneeListener::class, async: true, maxAttempts: 3); // Async queued
