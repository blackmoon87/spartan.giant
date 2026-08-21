<?php

declare(strict_types=1);

use App\Controllers\CssShowcaseController;
use Spartan\Application;

/** @var Application $app */

$app->router->get('/', [CssShowcaseController::class, 'index']);
$app->router->get('/tailwind', [CssShowcaseController::class, 'tailwind']);
$app->router->get('/openprops', [CssShowcaseController::class, 'openprops']);
$app->router->get('/vanilla', [CssShowcaseController::class, 'vanilla']);
$app->router->get('/interactive', [CssShowcaseController::class, 'interactive']);
