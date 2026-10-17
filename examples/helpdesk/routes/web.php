<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\AttachmentController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\NotificationController;
use App\Controllers\TicketController;

$app->router->get('/', [DashboardController::class, 'home']);

// Auth — guards redirect guests to /login, so keep that path.
$app->router->get('/login', [AuthController::class, 'showLogin']);
$app->router->post('/login', [AuthController::class, 'login'], ['throttle:5,60']);
$app->router->get('/register', [AuthController::class, 'showRegister']);
$app->router->post('/register', [AuthController::class, 'register'], ['throttle:5,60']);
$app->router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$app->router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

// Tickets. /tickets/create is registered before /tickets/{id}.
$app->router->get('/tickets', [TicketController::class, 'index'], ['auth']);
$app->router->get('/tickets/create', [TicketController::class, 'create'], ['auth']);
$app->router->post('/tickets', [TicketController::class, 'store'], ['auth', 'throttle:20,60']);
$app->router->get('/tickets/{id}', [TicketController::class, 'show'], ['auth']);
$app->router->patch('/tickets/{id}', [TicketController::class, 'update'], ['auth']);
$app->router->post('/tickets/{id}/replies', [TicketController::class, 'reply'], ['auth', 'throttle:30,60']);
$app->router->get('/attachments/{id}', [AttachmentController::class, 'download'], ['auth']);

$app->router->get('/notifications', [NotificationController::class, 'index'], ['auth']);
$app->router->post('/notifications/read', [NotificationController::class, 'markRead'], ['auth']);

$app->router->get('/account/tokens', [AccountController::class, 'tokens'], ['auth']);
$app->router->post('/account/tokens', [AccountController::class, 'createToken'], ['auth']);
$app->router->delete('/account/tokens/{id}', [AccountController::class, 'revokeToken'], ['auth']);

// Admin — AdminController carries #[RequireRole('admin')].
$app->router->get('/admin/users', [AdminController::class, 'users'], ['auth']);
$app->router->patch('/admin/users/{id}', [AdminController::class, 'updateRole'], ['auth']);
