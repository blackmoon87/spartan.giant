<?php

declare(strict_types=1);

namespace App;

use Spartan\Application;
use Spartan\ExceptionHandler;
use Spartan\Request;
use Spartan\Response;

/**
 * Uncaught errors under /api/ answer JSON whatever headers the client sent;
 * every other path keeps the framework's HTML error page.
 */
class ApiAwareExceptionHandler extends ExceptionHandler
{
    public function handle(\Throwable $e, Request $request, Response $response, array $config): void
    {
        if (!str_starts_with($request->getPath(), '/api/')) {
            parent::handle($e, $request, $response, $config);
            return;
        }

        Application::$app->logger->error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        $response->reset();
        $response->json([
            'error' => ($config['app']['debug'] ?? false) ? $e->getMessage() : trans('api.server_error'),
        ], 500);
        $response->send();
    }
}
