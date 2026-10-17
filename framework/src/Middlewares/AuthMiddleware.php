<?php

declare(strict_types=1);

namespace Spartan\Middlewares;

use Spartan\Middleware;
use Spartan\Request;
use Spartan\Response;
use Spartan\Application;

/**
 * Example Auth Middleware — blocks unauthenticated access to protected routes.
 *
 * Register in public/index.php:
 *   $app->router->get('/admin', [AdminController::class, 'index'], [AuthMiddleware::class]);
 */
class AuthMiddleware extends Middleware
{
    public function execute(Request $request, Response $response): void
    {
        $userId = Application::$app->session->get('user_id');

        if (!$userId) {
            // AJAX/JSON callers get a 401 they can act on, not a redirect to
            // an HTML login page (same as the #[RequiresRole] attribute check).
            if ($request->isAjax()) {
                $response->json(['error' => trans('spartan.http.unauthorized')], 401);
                return;
            }

            Application::$app->session->setFlash('error', trans('spartan.http.login_required'));
            $response->redirect('/login');
        }
    }
}
