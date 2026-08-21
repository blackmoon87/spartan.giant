<?php

declare(strict_types=1);

namespace Spartan;

class ExceptionHandler
{
    /**
     * Process global application exceptions.
     */
    public function handle(\Throwable $e, Request $request, Response $response, array $config): void
    {
        $response->setStatusCode(500);

        if (isset(Application::$app->logger)) {
            Application::$app->logger->error(
                "Uncaught Exception: {message} in {file}:{line}\nStack trace:\n{trace}",
                [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

        if ($request->isAjax()) {
            $response->json([
                'error' => '500 Internal Server Error',
                'message' => ($config['app']['debug'] ?? false) ? $e->getMessage() : 'An unexpected error occurred.'
            ], 500);
            $response->send();
            return;
        }

        $response->send();

        if ($config['app']['debug'] ?? false) {
            echo "<h1>500 Internal Server Error</h1>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            try {
                echo Application::$app->view->render('error_500', ['message' => 'An unexpected error occurred.']);
            } catch (\Throwable $err) {
                echo "<h1>500 Internal Server Error</h1><p>An unexpected error occurred.</p>";
            }
        }
    }
}
