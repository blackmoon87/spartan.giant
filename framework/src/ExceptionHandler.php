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
        // Drop whatever the failed request had prepared — a Location header or
        // a half-built body must not be sent alongside the 500 page.
        $response->reset();
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
                'error' => trans('spartan.http.server_error'),
                'message' => ($config['app']['debug'] ?? false) ? $e->getMessage() : trans('spartan.http.server_error_page')
            ], 500);
            $response->send();
            return;
        }

        $response->send();

        if ($config['app']['debug'] ?? false) {
            echo "<h1>" . htmlspecialchars(trans('spartan.http.title_500'), ENT_QUOTES, 'UTF-8') . "</h1>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            try {
                echo Application::$app->view->render('error_500', ['message' => trans('spartan.http.server_error_page')]);
            } catch (\Throwable $err) {
                echo Router::plainErrorPage('spartan.http.title_500', 'spartan.http.server_error_page');
            }
        }
    }
}
