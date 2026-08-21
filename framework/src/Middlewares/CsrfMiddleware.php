<?php

declare(strict_types=1);

namespace Spartan\Middlewares;

use Spartan\Middleware;
use Spartan\Request;
use Spartan\Response;
use Spartan\Application;

class CsrfMiddleware extends Middleware
{
    /**
     * Every verb that mutates state must carry a token — not just POST.
     * A native PUT/PATCH/DELETE previously slipped through unverified.
     */
    private const STATE_CHANGING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Compiled exclusion patterns, memoised per process (worker-mode friendly). */
    private static array $compiledExclusions = [];

    public function execute(Request $request, Response $response): void
    {
        $method = $request->getMethod();
        if (!in_array($method, self::STATE_CHANGING, true)) {
            $method = $request->getRealMethod();
        }
        $path = $request->getPath();

        // Check if the request is a state-changing method
        if (in_array($method, self::STATE_CHANGING, true)) {
            $isExcluded = false;
            
            // Access CSRF exclusions from the router
            $exclusions = Application::$app->router->getCsrfExclusions();
            
            foreach ($exclusions as $excludedPath) {
                $pattern = self::$compiledExclusions[$excludedPath]
                    ??= '#^' . str_replace('\*', '.*', preg_quote($excludedPath, '#')) . '$#';
                if (preg_match($pattern, $path)) {
                    $isExcluded = true;
                    break;
                }
            }

            if (!$isExcluded && !$request->validateCsrf()) {
                $response->setStatusCode(403);

                if ($request->isAjax()) {
                    $response->json(['error' => 'Invalid or expired CSRF token.'], 403);
                    return;
                }

                // A rejected token is a client error, not a server fault.
                // Throwing here previously surfaced as a 500 page.
                $response->setHeader('Content-Type', 'text/html; charset=utf-8');
                try {
                    $response->setContent(Application::$app->view->render('error_403', [
                        'message' => 'Invalid or expired security token. Please reload the page and try again.',
                    ]));
                } catch (\Throwable $e) {
                    $response->setContent(
                        '<h1>403 Forbidden</h1><p>Invalid or expired security token. '
                        . 'Please reload the page and try again.</p>'
                    );
                }
                return;
            }
        }
    }
}
