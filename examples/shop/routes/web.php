<?php

declare(strict_types=1);

use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\ShopController;
use Spartan\Application;
use App\Listeners\NotifyAdminListener;
use App\Listeners\SendOrderEmailListener;
use App\Listeners\UpdateStockListener;

/** @var Application $app */

// Storefront Web Routes
$app->router->get('/', [ShopController::class, 'index']);
$app->router->get('/catalog', [ShopController::class, 'catalog']);
$app->router->post('/shop/search', [ShopController::class, 'search']);
$app->router->get('/product/{slug}', [ShopController::class, 'show']);

// Cart Routes
$app->router->get('/cart', [CartController::class, 'index']);
$app->router->post('/cart/add', [CartController::class, 'add']);

// Checkout Routes
$app->router->get('/checkout', [CheckoutController::class, 'show']);
$app->router->post('/checkout/process', [CheckoutController::class, 'process']);
$app->router->get('/order/success/{id}', [CheckoutController::class, 'success']);

// Event & Listener Registration
$app->events->listen('order.placed', UpdateStockListener::class); // Sync listener
$app->events->listen('order.placed', SendOrderEmailListener::class, async: true, maxAttempts: 3, onFailure: 'retry'); // Async queued listener
$app->events->listen('order.placed', NotifyAdminListener::class, async: true, maxAttempts: 3, onFailure: 'retry'); // Async queued listener
